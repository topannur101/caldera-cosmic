<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\Models\InsClmRecord;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;

new class extends Component {

    use HasDateRangeFilter;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public string $location = '';

    public array $locations = [];
    public array $detailedStats = [];
    public array $hourlyPatterns = [];
    public array $correlationData = [];
    public int $progress = 0;

    public function mount()
    {
        if(!$this->start_at || !$this->end_at) {
            $this->setThisWeek();
        }

        // Get available locations (currently only 'ip')
        $this->locations = InsClmRecord::distinct()
            ->whereNotNull('location')
            ->pluck('location')
            ->toArray();
    }

    #[On('update')]
    public function updated()
    {
        $this->progress = 0;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        // Phase 1: Fetch data (0-49%)
        $this->progress = 10;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        $records = $this->getRecordsData();

        $this->progress = 49;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        // Phase 2: Calculate detailed metrics (49-98%)
        $this->progress = 60;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        $this->calculateDetailedMetrics($records);

        $this->progress = 98;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        // Phase 3: Render charts (98-100%)
        $this->renderCharts($records);

        $this->progress = 100;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );
    }

    private function getRecordsData()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsClmRecord::whereBetween('created_at', [$start, $end]);

        if ($this->location) {
            $query->where('location', $this->location);
        }

        return $query->orderBy('created_at')->get();
    }

    private function calculateDetailedMetrics($records)
    {
        if ($records->isEmpty()) {
            $this->detailedStats = [
                'temperature' => ['std_dev' => 0, 'variance' => 0, 'median' => 0],
                'humidity' => ['std_dev' => 0, 'variance' => 0, 'median' => 0],
                'correlation' => 0
            ];
            $this->hourlyPatterns = [];
            $this->correlationData = [];
            return;
        }

        $temperatures = $records->pluck('temperature')->toArray();
        $humidities = $records->pluck('humidity')->toArray();

        // Calculate detailed statistics
        $this->detailedStats = [
            'temperature' => [
                'std_dev' => round($this->calculateStandardDeviation($temperatures), 2),
                'variance' => round($this->calculateVariance($temperatures), 2),
                'median' => $this->calculateMedian($temperatures)
            ],
            'humidity' => [
                'std_dev' => round($this->calculateStandardDeviation($humidities), 2),
                'variance' => round($this->calculateVariance($humidities), 2),
                'median' => $this->calculateMedian($humidities)
            ],
            'correlation' => round($this->calculateCorrelation($temperatures, $humidities), 3)
        ];

        // Calculate hourly patterns
        $this->calculateHourlyPatterns($records);

        // Prepare correlation data for scatter plot
        $this->correlationData = $records->map(function($record) {
            return [
                'temperature' => $record->temperature,
                'humidity' => $record->humidity,
                'created_at' => $record->created_at
            ];
        })->toArray();
    }

    private function calculateHourlyPatterns($records)
    {
        $hourlyData = $records->groupBy(function($record) {
            return Carbon::parse($record->created_at)->format('H');
        });

        $this->hourlyPatterns = [];
        
        for ($hour = 0; $hour < 24; $hour++) {
            $hourKey = sprintf('%02d', $hour);
            $hourRecords = $hourlyData->get($hourKey, collect());
            
            if ($hourRecords->isNotEmpty()) {
                $this->hourlyPatterns[$hour] = [
                    'hour' => $hour,
                    'temperature_avg' => round($hourRecords->avg('temperature'), 1),
                    'humidity_avg' => round($hourRecords->avg('humidity'), 1),
                    'count' => $hourRecords->count()
                ];
            } else {
                $this->hourlyPatterns[$hour] = [
                    'hour' => $hour,
                    'temperature_avg' => 0,
                    'humidity_avg' => 0,
                    'count' => 0
                ];
            }
        }
    }

    private function calculateStandardDeviation($values)
    {
        $count = count($values);
        if ($count < 2) return 0;
        
        $mean = array_sum($values) / $count;
        $squareDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return sqrt(array_sum($squareDiffs) / ($count - 1));
    }

    private function calculateVariance($values)
    {
        $count = count($values);
        if ($count < 2) return 0;
        
        $mean = array_sum($values) / $count;
        $squareDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return array_sum($squareDiffs) / ($count - 1);
    }

    private function calculateMedian($values)
    {
        sort($values);
        $count = count($values);
        if ($count === 0) return 0;
        
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }

    private function calculateCorrelation($x, $y)
    {
        $n = count($x);
        if ($n < 2) return 0;
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }
        
        $numerator = $n * $sumXY - $sumX * $sumY;
        $denominator = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));
        
        return $denominator != 0 ? $numerator / $denominator : 0;
    }

    private function renderCharts($records)
    {
        if ($records->isEmpty()) {
            $this->js("
                ['temperature-pattern-chart', 'humidity-pattern-chart', 'correlation-chart'].forEach(id => {
                    const container = document.getElementById(id);
                    if (container) container.innerHTML = '<div class=\"flex items-center justify-center h-full text-neutral-500\">" . __('Data tidak tersedia') . "</div>';
                });
            ");
            return;
        }

        // Temperature hourly pattern chart
        $tempPatternOptions = $this->getHourlyPatternChartOptions('temperature');
        
        // Humidity hourly pattern chart
        $humidityPatternOptions = $this->getHourlyPatternChartOptions('humidity');
        
        // Correlation scatter plot
        $correlationOptions = $this->getCorrelationChartOptions();

        $this->js("
            (function() {
                // Temperature pattern chart
                const tempCtx = document.getElementById('temperature-pattern-chart');
                if (window.tempPatternChart) window.tempPatternChart.destroy();
                if (tempCtx) {
                    window.tempPatternChart = new Chart(tempCtx, " . json_encode($tempPatternOptions) . ");
                }

                // Humidity pattern chart
                const humidityCtx = document.getElementById('humidity-pattern-chart');
                if (window.humidityPatternChart) window.humidityPatternChart.destroy();
                if (humidityCtx) {
                    window.humidityPatternChart = new Chart(humidityCtx, " . json_encode($humidityPatternOptions) . ");
                }

                // Correlation chart
                const correlationCtx = document.getElementById('correlation-chart');
                if (window.correlationChart) window.correlationChart.destroy();
                if (correlationCtx) {
                    window.correlationChart = new Chart(correlationCtx, " . json_encode($correlationOptions) . ");
                }
            })();
        ");
    }

    private function getHourlyPatternChartOptions($type)
    {
        $dataKey = $type . '_avg';
        $color = $type === 'temperature' ? 'rgba(220, 38, 127, 0.8)' : 'rgba(59, 130, 246, 0.8)';
        $borderColor = $type === 'temperature' ? 'rgba(220, 38, 127, 1)' : 'rgba(59, 130, 246, 1)';
        $unit = $type === 'temperature' ? '°C' : '%';
        $title = $type === 'temperature' ? __('Pola Suhu per Jam') : __('Pola Kelembaban per Jam');

        return [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(fn($hour) => sprintf('%02d:00', $hour), range(0, 23)),
                'datasets' => [[
                    'label' => $type === 'temperature' ? __('Rata-rata Suhu') : __('Rata-rata Kelembaban'),
                    'data' => array_column($this->hourlyPatterns, $dataKey),
                    'backgroundColor' => $color,
                    'borderColor' => $borderColor,
                    'borderWidth' => 1
                ]]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => $title,
                        'color' => session('bg') === 'dark' ? '#e5e5e5' : '#404040'
                    ],
                    'legend' => [
                        'display' => false
                    ]
                ],
                'scales' => [
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Jam'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ]
                    ],
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => $unit,
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ]
                    ]
                ]
            ]
        ];
    }

    private function getCorrelationChartOptions()
    {
        $scatterData = array_map(function($record) {
            return [
                'x' => $record['temperature'],
                'y' => $record['humidity']
            ];
        }, $this->correlationData);

        return [
            'type' => 'scatter',
            'data' => [
                'datasets' => [[
                    'label' => __('Suhu vs Kelembaban'),
                    'data' => $scatterData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderWidth' => 1
                ]]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Korelasi Suhu dan Kelembaban') . ' (r = ' . $this->detailedStats['correlation'] . ')',
                        'color' => session('bg') === 'dark' ? '#e5e5e5' : '#404040'
                    ],
                    'legend' => [
                        'display' => false
                    ],
                    'datalabels' => [
                        'display' => false
                    ]
                ],
                'scales' => [
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Suhu (°C)'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ]
                    ],
                    'y' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Kelembaban (%)'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ]
                    ]
                ]
            ]
        ];
    }
};

?>

<div>
    <!-- Filters -->
    <div class="p-0 sm:p-1 mb-6">
        <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <!-- Date Range -->
            <div>
                <div class="flex mb-2 text-xs text-neutral-500">
                    <div class="flex">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <x-text-button class="uppercase ml-3">{{ __('Rentang') }}<i class="icon-chevron-down ms-1"></i></x-text-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setToday">{{ __('Hari ini') }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">{{ __('Kemarin') }}</x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">{{ __('Minggu ini') }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">{{ __('Minggu lalu') }}</x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">{{ __('Bulan ini') }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">{{ __('Bulan lalu') }}</x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at" id="cal-date-end" type="date"></x-text-input>
                </div>
            </div>

            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>

            <!-- Location Filter -->
            <div>
                <label for="location-filter" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Lokasi') }}</label>
                <x-select class="w-32" id="location-filter" wire:model.live="location" disabled>
                    @foreach($locations as $loc)
                    <option value="{{ $loc }}" {{ $loc === 'ip' ? 'selected' : '' }}>{{ strtoupper($loc) }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>

            <!-- Loading indicator -->
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="hidden">
                    <x-progress-bar :$progress>                       
                        <span x-text="
                        progress < 49 ? '{{ __('Mengambil data...') }}' : 
                        progress < 98 ? '{{ __('Menghitung analisis...') }}' : 
                        '{{ __('Merender grafik...') }}'
                        "></span>
                    </x-progress-bar>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistical Analysis Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        <!-- Temperature Statistics -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center mb-4">
                <i class="icon-thermometer text-2xl text-pink-600 mr-3"></i>
                <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Analisis Suhu') }}</h3>
            </div>
            <div>
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Median') }}:</span>
                    <span class="font-medium">{{ $detailedStats['temperature']['median'] ?? 0 }}°C</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Std Deviasi') }}:</span>
                    <span class="font-medium">{{ $detailedStats['temperature']['std_dev'] ?? 0 }}°C</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Variansi') }}:</span>
                    <span class="font-medium">{{ $detailedStats['temperature']['variance'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <!-- Humidity Statistics -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center mb-4">
                <i class="icon-droplet text-2xl text-blue-600 mr-3"></i>
                <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Analisis Kelembaban') }}</h3>
            </div>
            <div>
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Median') }}:</span>
                    <span class="font-medium">{{ $detailedStats['humidity']['median'] ?? 0 }}%</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Std Deviasi') }}:</span>
                    <span class="font-medium">{{ $detailedStats['humidity']['std_dev'] ?? 0 }}%</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Variansi') }}:</span>
                    <span class="font-medium">{{ $detailedStats['humidity']['variance'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <!-- Correlation Analysis -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center mb-4">
                <i class="icon-link-2 text-2xl text-green-600 mr-3"></i>
                <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Korelasi') }}</h3>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold {{ abs($detailedStats['correlation'] ?? 0) > 0.7 ? 'text-green-500' : (abs($detailedStats['correlation'] ?? 0) > 0.3 ? 'text-yellow-500' : 'text-red-500') }}">
                    {{ $detailedStats['correlation'] ?? 0 }}
                </div>
                <div class="text-sm text-neutral-600 dark:text-neutral-400 mt-2">
                    @php
                        $corr = abs($detailedStats['correlation'] ?? 0);
                    @endphp
                    @if($corr > 0.7)
                        {{ __('Korelasi Kuat') }}
                    @elseif($corr > 0.3)
                        {{ __('Korelasi Sedang') }}
                    @else
                        {{ __('Korelasi Lemah') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Temperature Hourly Pattern -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80" wire:ignore>
                <canvas id="temperature-pattern-chart"></canvas>
            </div>
        </div>

        <!-- Humidity Hourly Pattern -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80" wire:ignore>
                <canvas id="humidity-pattern-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Correlation Scatter Plot -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-8">
        <div class="h-96" wire:ignore>
            <canvas id="correlation-chart"></canvas>
        </div>
    </div>

    <!-- Hourly Patterns Table -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Pola Jam-an') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-sm text-sm w-full">
                <thead>
                    <tr class="text-xs uppercase text-neutral-500 border-b">
                        <th class="px-4 py-3">{{ __('Jam') }}</th>
                        <th class="px-4 py-3">{{ __('Rata-rata Suhu (°C)') }}</th>
                        <th class="px-4 py-3">{{ __('Rata-rata Kelembaban (%)') }}</th>
                        <th class="px-4 py-3">{{ __('Jumlah Data') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hourlyPatterns as $pattern)
                    <tr class="border-b border-neutral-100 dark:border-neutral-700">
                        <td class="px-4 py-3 font-mono">{{ sprintf('%02d:00', $pattern['hour']) }}</td>
                        <td class="px-4 py-3 {{ $pattern['temperature_avg'] > 0 ? '' : 'text-neutral-400' }}">
                            {{ $pattern['temperature_avg'] > 0 ? $pattern['temperature_avg'] : '-' }}
                        </td>
                        <td class="px-4 py-3 {{ $pattern['humidity_avg'] > 0 ? '' : 'text-neutral-400' }}">
                            {{ $pattern['humidity_avg'] > 0 ? $pattern['humidity_avg'] : '-' }}
                        </td>
                        <td class="px-4 py-3">{{ $pattern['count'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript