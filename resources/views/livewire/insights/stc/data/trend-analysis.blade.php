<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsStc;
use App\Models\InsStcDSum;
use App\Models\InsStcDLog;
use App\Models\InsStcMachine;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

new class extends Component {

    use HasDateRangeFilter;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public $line;

    #[Url]
    public string $position = '';

    #[Url]
    public string $trend_period = 'daily'; // daily, weekly, monthly

    #[Url]
    public string $trend_metric = 'average'; // average, median, deviation

    public array $lines = [];
    public array $trend_stats = [];

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisMonth(); // Default to monthly view for trends
        }

        $this->lines = InsStcMachine::orderBy('line')->get()->pluck('line')->toArray();
    }

    #[On('update')]
    public function update()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        // Build query for temperature data with filters
        $query = InsStcDLog::join('ins_stc_d_sums', 'ins_stc_d_logs.ins_stc_d_sum_id', '=', 'ins_stc_d_sums.id')
            ->join('ins_stc_machines', 'ins_stc_d_sums.ins_stc_machine_id', '=', 'ins_stc_machines.id')
            ->select(
                'ins_stc_d_logs.*',
                'ins_stc_machines.line',
                'ins_stc_d_sums.position',
                DB::raw('DATE(ins_stc_d_logs.taken_at) as log_date'),
                DB::raw('WEEK(ins_stc_d_logs.taken_at) as log_week'),
                DB::raw('MONTH(ins_stc_d_logs.taken_at) as log_month'),
                DB::raw('YEAR(ins_stc_d_logs.taken_at) as log_year')
            )
            ->whereBetween('ins_stc_d_logs.taken_at', [$start, $end]);

        // Apply filters
        if ($this->line) {
            $query->where('ins_stc_machines.line', $this->line);
        }

        if ($this->position) {
            $query->where('ins_stc_d_sums.position', $this->position);
        }

        // Group data by trend period
        $groupBy = match($this->trend_period) {
            'daily' => ['log_date', 'line', 'position'],
            'weekly' => ['log_year', 'log_week', 'line', 'position'],
            'monthly' => ['log_year', 'log_month', 'line', 'position'],
            default => ['log_date', 'line', 'position']
        };

        $trendData = $query->get()
            ->groupBy(function($item) {
                return match($this->trend_period) {
                    'daily' => $item->log_date . '_' . $item->line . '_' . $item->position,
                    'weekly' => $item->log_year . 'W' . sprintf('%02d', $item->log_week) . '_' . $item->line . '_' . $item->position,
                    'monthly' => $item->log_year . 'M' . sprintf('%02d', $item->log_month) . '_' . $item->line . '_' . $item->position,
                    default => $item->log_date . '_' . $item->line . '_' . $item->position
                };
            })
            ->map(function($group) {
                $first = $group->first();
                $temps = $group->pluck('temp')->toArray();
                
                $periodLabel = match($this->trend_period) {
                    'daily' => $first->log_date,
                    'weekly' => $first->log_year . '-W' . sprintf('%02d', $first->log_week),
                    'monthly' => $first->log_year . '-' . sprintf('%02d', $first->log_month),
                    default => $first->log_date
                };

                return [
                    'period' => $periodLabel,
                    'line' => $first->line,
                    'position' => $first->position,
                    'data_points' => count($temps),
                    'average' => round(array_sum($temps) / count($temps), 2),
                    'median' => $this->calculateMedian($temps),
                    'min' => min($temps),
                    'max' => max($temps),
                    'std_dev' => $this->calculateStdDev($temps),
                    'range' => max($temps) - min($temps)
                ];
            });

        // Calculate trend statistics
        $this->trend_stats = $this->calculateTrendStats($trendData);

        // Prepare chart data
        $chartData = $this->prepareChartData($trendData);

        $this->js("
         (function() {
               const options = " . json_encode($chartData) . ";
            
               // Render trend chart
               const trendChartContainer = \$wire.\$el.querySelector('#trend-chart-container');
               trendChartContainer.innerHTML = '';
               const trendCanvas = document.createElement('canvas');
               trendCanvas.id = 'trend-chart';
               trendChartContainer.appendChild(trendCanvas);
               new Chart(trendCanvas, options);
         })();
      ");
    }

    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }

    private function calculateStdDev(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);
        
        return round(sqrt($variance), 2);
    }

    private function calculateTrendStats($trendData): array
    {
        $stats = [
            'total_periods' => $trendData->count(),
            'avg_temperature' => 0,
            'temp_trend' => 'stable',
            'highest_deviation' => 0,
            'most_consistent_line' => null,
            'alerts' => []
        ];

        if ($trendData->count() > 0) {
            // Calculate overall average
            $allAverages = $trendData->pluck('average');
            $stats['avg_temperature'] = round($allAverages->avg(), 2);

            // Calculate trend direction
            $chronologicalData = $trendData->sortBy('period');
            $firstHalf = $chronologicalData->take(ceil($chronologicalData->count() / 2))->pluck('average')->avg();
            $secondHalf = $chronologicalData->skip(floor($chronologicalData->count() / 2))->pluck('average')->avg();
            
            $tempDiff = $secondHalf - $firstHalf;
            if ($tempDiff > 2) {
                $stats['temp_trend'] = 'increasing';
            } elseif ($tempDiff < -2) {
                $stats['temp_trend'] = 'decreasing';
            }

            // Find highest deviation
            $stats['highest_deviation'] = round($trendData->max('std_dev'), 2);

            // Find most consistent line (lowest std dev)
            $lineConsistency = $trendData->groupBy(function($item) {
                return $item['line'] . '_' . $item['position'];
            })->map(function($group) {
                return $group->avg('std_dev');
            });
            
            $stats['most_consistent_line'] = $lineConsistency->keys()->first();

            // Generate alerts
            foreach ($trendData as $data) {
                if ($data['std_dev'] > 5) {
                    $stats['alerts'][] = "High variation in Line {$data['line']} {$data['position']} - StdDev: {$data['std_dev']}°C";
                }
                if ($data['range'] > 15) {
                    $stats['alerts'][] = "Wide temperature range in Line {$data['line']} {$data['position']} - Range: {$data['range']}°C";
                }
            }
        }

        return $stats;
    }

    private function prepareChartData($trendData): array
    {
        // Group by line and position for separate datasets
        $datasets = [];
        $labels = $trendData->pluck('period')->unique()->sort()->values()->toArray();

        $groupedData = $trendData->groupBy(function($item) {
            return $item['line'] . '_' . $item['position'];
        });

        $colors = [
            '#D64550', '#36A2EB', '#FFCE56', '#4BC0C0', 
            '#9966FF', '#FF9F40', '#C9CBCF', '#4BC0C0'
        ];
        $colorIndex = 0;

        foreach ($groupedData as $key => $group) {
            [$line, $position] = explode('_', $key);
            $positionSymbol = $position === 'upper' ? '△' : '▽';
            
            $dataPoints = [];
            foreach ($labels as $period) {
                $dataPoint = $group->where('period', $period)->first();
                $value = $dataPoint ? $dataPoint[$this->trend_metric] : null;
                $dataPoints[] = $value;
            }

            $datasets[] = [
                'label' => "Line {$line} {$positionSymbol}",
                'data' => $dataPoints,
                'borderColor' => $colors[$colorIndex % count($colors)],
                'backgroundColor' => $colors[$colorIndex % count($colors)] . '20',
                'tension' => 0.4,
                'fill' => false
            ];
            $colorIndex++;
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => $datasets
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'interaction' => [
                    'intersect' => false,
                    'mode' => 'index'
                ],
                'scales' => [
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => ucfirst($this->trend_period) . ' Period',
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ]
                    ],
                    'y' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Temperature (°C)',
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ]
                    ]
                ],
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => ucfirst($this->trend_metric) . ' Temperature Trends',
                        'color' => session('bg') === 'dark' ? '#e5e5e5' : '#404040'
                    ],
                    'legend' => [
                        'display' => true,
                        'position' => 'top'
                    ]
                ]
            ]
        ];
    }

    public function updated()
    {
        $this->update();
    }
};

?>

<div>
    <div class="p-0 sm:p-1 mb-6">
        <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div>
                <div class="flex mb-2 text-xs text-neutral-500">
                    <div class="flex">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <x-text-button class="uppercase ml-3">{{ __('Rentang') }}<i class="icon-chevron-down ms-1"></i></x-text-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                    {{ __('Minggu ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                    {{ __('Minggu lalu') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                    {{ __('Bulan ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                    {{ __('Bulan lalu') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisQuarter">
                                    {{ __('Kuartal ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastQuarter">
                                    {{ __('Kuartal lalu') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" id="trend-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at" id="trend-date-end" type="date"></x-text-input>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grid grid-cols-2 lg:flex gap-3">
                <div>
                    <label for="trend-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-select class="w-full lg:w-auto" id="trend-line" wire:model.live="line">
                        <option value=""></option>
                        @foreach($lines as $line)
                        <option value="{{ $line }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label for="trend-position" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                    <x-select class="w-full lg:w-auto" id="trend-position" wire:model.live="position">
                        <option value=""></option>
                        <option value="upper">{{ __('Atas') }}</option>
                        <option value="lower">{{ __('Bawah') }}</option>
                    </x-select>
                </div>
                <div>
                    <label for="trend-period" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Periode') }}</label>
                    <x-select class="w-full lg:w-auto" id="trend-period" wire:model.live="trend_period">
                        <option value="daily">{{ __('Harian') }}</option>
                        <option value="weekly">{{ __('Mingguan') }}</option>
                        <option value="monthly">{{ __('Bulanan') }}</option>
                    </x-select>
                </div>
                <div>
                    <label for="trend-metric" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Metrik') }}</label>
                    <x-select class="w-full lg:w-auto" id="trend-metric" wire:model.live="trend_metric">
                        <option value="average">{{ __('Rata-rata') }}</option>
                        <option value="median">{{ __('Median') }}</option>
                        <option value="std_dev">{{ __('Std Dev') }}</option>
                        <option value="range">{{ __('Range') }}</option>
                    </x-select>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                    <div class="relative w-3">
                        <x-spinner class="sm mono"></x-spinner>
                    </div>
                    <div>{{ __('Memuat...') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Summary -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Periode Total') }}</div>
            <div class="text-2xl font-bold">{{ $trend_stats['total_periods'] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Rata-rata Suhu') }}</div>
            <div class="text-2xl font-bold">{{ ($trend_stats['avg_temperature'] ?? 0) }}°C</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Tren Suhu') }}</div>
            <div class="text-lg">
                @switch($trend_stats['temp_trend'] ?? 'stable')
                    @case('increasing')
                        <i class="icon-trending-up text-red-500"></i> {{ __('Meningkat') }}
                        @break
                    @case('decreasing')
                        <i class="icon-trending-down text-blue-500"></i> {{ __('Menurun') }}
                        @break
                    @default
                        <i class="icon-minus text-green-500"></i> {{ __('Stabil') }}
                @endswitch
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Deviasi Tertinggi') }}</div>
            <div class="text-2xl font-bold">{{ ($trend_stats['highest_deviation'] ?? 0) }}°C</div>
        </div>
    </div>

    <!-- Alerts Section -->
    @if(!empty($trend_stats['alerts']))
    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
        <div class="flex items-center mb-2">
            <i class="icon-alert-triangle text-yellow-600 dark:text-yellow-400 mr-2"></i>
            <h3 class="font-medium text-yellow-800 dark:text-yellow-200">{{ __('Peringatan Tren') }}</h3>
        </div>
        <ul class="list-disc list-inside text-sm text-yellow-700 dark:text-yellow-300 h-96 overflow-y-auto">
            @foreach($trend_stats['alerts'] as $alert)
            <li>{{ $alert }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Main Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
        <div class="h-96" id="trend-chart-container" wire:key="trend-chart-container" wire:ignore></div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript