<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsStc;
use App\Models\InsStcDSum;
use App\Models\InsStcMachine;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

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
    public string $view_mode = 'zones';

    public array $lines = [];
    public array $zoneVariance = [];
    public array $lineVariance = [];
    public array $overallStats = [];

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisWeek();
        }

        $this->lines = InsStcMachine::orderBy('line')->get()->pluck('line')->toArray();
    }

    #[On('update')]
    public function update()
    {
        $this->calculateVarianceAnalysis();
        $this->renderCharts();
    }

    private function calculateVarianceAnalysis()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();
        $targets = InsStc::$target_values; // [75, 73, 68, 63, 58, 53, 43, 43]

        $query = InsStcDSum::with(['ins_stc_machine'])
            ->when($this->line, function (Builder $query) {
                $query->whereHas('ins_stc_machine', function (Builder $query) {
                    $query->where('line', $this->line);
                });
            })
            ->when($this->position, function (Builder $query) {
                $query->where('position', $this->position);
            })
            ->whereBetween('created_at', [$start, $end]);

        $dSums = $query->get();

        // Initialize zone data
        $zoneData = [];
        $lineData = [];
        
        for ($zone = 1; $zone <= 8; $zone++) {
            $zoneData[$zone] = [
                'target' => $targets[$zone - 1],
                'measurements' => [],
                'deviations' => []
            ];
        }

        // Collect data
        foreach ($dSums as $dSum) {
            $hbValues = json_decode($dSum->hb_values, true) ?? [];
            $line = $dSum->ins_stc_machine->line;

            if (!isset($lineData[$line])) {
                $lineData[$line] = [
                    'measurements' => [],
                    'deviations' => [],
                    'zone_stats' => []
                ];
            }

            for ($zone = 1; $zone <= 8; $zone++) {
                if (isset($hbValues[$zone - 1])) {
                    $measurement = $hbValues[$zone - 1];
                    $target = $targets[$zone - 1];
                    $deviation = $measurement - $target;

                    $zoneData[$zone]['measurements'][] = $measurement;
                    $zoneData[$zone]['deviations'][] = $deviation;

                    $lineData[$line]['measurements'][] = $measurement;
                    $lineData[$line]['deviations'][] = $deviation;

                    if (!isset($lineData[$line]['zone_stats'][$zone])) {
                        $lineData[$line]['zone_stats'][$zone] = [
                            'measurements' => [],
                            'deviations' => []
                        ];
                    }
                    $lineData[$line]['zone_stats'][$zone]['measurements'][] = $measurement;
                    $lineData[$line]['zone_stats'][$zone]['deviations'][] = $deviation;
                }
            }
        }

        // Calculate zone statistics
        $this->zoneVariance = [];
        foreach ($zoneData as $zone => $data) {
            if (count($data['measurements']) > 0) {
                $this->zoneVariance[$zone] = [
                    'zone' => $zone,
                    'target' => $data['target'],
                    'count' => count($data['measurements']),
                    'avg_actual' => round(array_sum($data['measurements']) / count($data['measurements']), 2),
                    'avg_deviation' => round(array_sum($data['deviations']) / count($data['deviations']), 2),
                    'std_deviation' => round($this->calculateStandardDeviation($data['deviations']), 2),
                    'min_temp' => min($data['measurements']),
                    'max_temp' => max($data['measurements']),
                    'range' => max($data['measurements']) - min($data['measurements']),
                    'within_1c' => count(array_filter($data['deviations'], fn($d) => abs($d) <= 1)),
                    'within_3c' => count(array_filter($data['deviations'], fn($d) => abs($d) <= 3)),
                    'within_5c' => count(array_filter($data['deviations'], fn($d) => abs($d) <= 5))
                ];

                // Calculate percentages
                $total = $this->zoneVariance[$zone]['count'];
                $this->zoneVariance[$zone]['within_1c_pct'] = round(($this->zoneVariance[$zone]['within_1c'] / $total) * 100, 1);
                $this->zoneVariance[$zone]['within_3c_pct'] = round(($this->zoneVariance[$zone]['within_3c'] / $total) * 100, 1);
                $this->zoneVariance[$zone]['within_5c_pct'] = round(($this->zoneVariance[$zone]['within_5c'] / $total) * 100, 1);
            }
        }

        // Calculate line statistics
        $this->lineVariance = [];
        foreach ($lineData as $line => $data) {
            if (count($data['measurements']) > 0) {
                $this->lineVariance[$line] = [
                    'line' => $line,
                    'count' => count($data['measurements']),
                    'avg_deviation' => round(array_sum($data['deviations']) / count($data['deviations']), 2),
                    'std_deviation' => round($this->calculateStandardDeviation($data['deviations']), 2),
                    'within_1c' => count(array_filter($data['deviations'], fn($d) => abs($d) <= 1)),
                    'within_3c' => count(array_filter($data['deviations'], fn($d) => abs($d) <= 3)),
                    'within_5c' => count(array_filter($data['deviations'], fn($d) => abs($d) <= 5))
                ];

                $total = $this->lineVariance[$line]['count'];
                $this->lineVariance[$line]['within_1c_pct'] = round(($this->lineVariance[$line]['within_1c'] / $total) * 100, 1);
                $this->lineVariance[$line]['within_3c_pct'] = round(($this->lineVariance[$line]['within_3c'] / $total) * 100, 1);
                $this->lineVariance[$line]['within_5c_pct'] = round(($this->lineVariance[$line]['within_5c'] / $total) * 100, 1);
            }
        }

        // Calculate overall statistics
        $allDeviations = [];
        foreach ($lineData as $data) {
            $allDeviations = array_merge($allDeviations, $data['deviations']);
        }

        if (count($allDeviations) > 0) {
            $this->overallStats = [
                'total_measurements' => count($allDeviations),
                'avg_deviation' => round(array_sum($allDeviations) / count($allDeviations), 2),
                'std_deviation' => round($this->calculateStandardDeviation($allDeviations), 2),
                'within_1c_pct' => round((count(array_filter($allDeviations, fn($d) => abs($d) <= 1)) / count($allDeviations)) * 100, 1),
                'within_3c_pct' => round((count(array_filter($allDeviations, fn($d) => abs($d) <= 3)) / count($allDeviations)) * 100, 1),
                'within_5c_pct' => round((count(array_filter($allDeviations, fn($d) => abs($d) <= 5)) / count($allDeviations)) * 100, 1)
            ];
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

    private function renderCharts()
    {
        if ($this->view_mode === 'zones') {
            $this->renderZoneCharts();
        } else {
            $this->renderLineCharts();
        }
    }

    private function renderZoneCharts()
    {
        // Zone accuracy chart
        $zoneLabels = array_map(fn($zone) => "Zone $zone", array_keys($this->zoneVariance));
        $within1c = array_column($this->zoneVariance, 'within_1c_pct');
        $within3c = array_column($this->zoneVariance, 'within_3c_pct');
        $within5c = array_column($this->zoneVariance, 'within_5c_pct');

        $accuracyData = [
            'labels' => $zoneLabels,
            'datasets' => [
                [
                    'label' => '±1°C',
                    'data' => $within1c,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)'
                ],
                [
                    'label' => '±3°C',
                    'data' => $within3c,
                    'backgroundColor' => 'rgba(234, 179, 8, 0.8)'
                ],
                [
                    'label' => '±5°C',
                    'data' => $within5c,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)'
                ]
            ]
        ];

        // Zone deviation chart
        $avgDeviations = array_column($this->zoneVariance, 'avg_deviation');
        $stdDeviations = array_column($this->zoneVariance, 'std_deviation');

        $deviationData = [
            'labels' => $zoneLabels,
            'datasets' => [
                [
                    'label' => __('Rata-rata Deviasi'),
                    'data' => $avgDeviations,
                    'backgroundColor' => 'rgba(214, 69, 80, 0.8)',
                    'yAxisID' => 'y'
                ],
                [
                    'label' => __('Standar Deviasi'),
                    'data' => $stdDeviations,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'yAxisID' => 'y1'
                ]
            ]
        ];

        $this->js("
            (function() {
               // Accuracy Chart
               const accuracyCtx = document.getElementById('accuracy-chart');
               if (window.accuracyChart) window.accuracyChart.destroy();
               window.accuracyChart = new Chart(accuracyCtx, {
                     type: 'bar',
                     data: " . json_encode($accuracyData) . ",
                     options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                           title: {
                                 display: true,
                                 text: '" . __('Akurasi Target per Zone') . "'
                           }
                        },
                        scales: {
                           y: {
                                 beginAtZero: true,
                                 max: 100,
                                 ticks: {
                                    callback: function(value) {
                                       return value + '%';
                                    }
                                 }
                           }
                        }
                     }
               });

               // Deviation Chart
               const deviationCtx = document.getElementById('deviation-chart');
               if (window.deviationChart) window.deviationChart.destroy();
               window.deviationChart = new Chart(deviationCtx, {
                     type: 'bar',
                     data: " . json_encode($deviationData) . ",
                     options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                           title: {
                                 display: true,
                                 text: '" . __('Deviasi per Zone') . "'
                           }
                        },
                        scales: {
                           y: {
                                 type: 'linear',
                                 display: true,
                                 position: 'left',
                                 title: {
                                    display: true,
                                    text: '" . __('Rata-rata Deviasi (°C)') . "'
                                 }
                           },
                           y1: {
                                 type: 'linear',
                                 display: true,
                                 position: 'right',
                                 title: {
                                    display: true,
                                    text: '" . __('Standar Deviasi (°C)') . "'
                                 },
                                 grid: {
                                    drawOnChartArea: false,
                                 },
                           }
                        }
                     }
               });
            })();
         ");
    }

    private function renderLineCharts()
    {
        $lineLabels = array_map(fn($line) => "Line " . sprintf('%02d', $line), array_keys($this->lineVariance));
        $within1c = array_column($this->lineVariance, 'within_1c_pct');
        $avgDeviations = array_column($this->lineVariance, 'avg_deviation');

        $lineAccuracyData = [
            'labels' => $lineLabels,
            'datasets' => [[
                'label' => '±1°C (%)',
                'data' => $within1c,
                'backgroundColor' => 'rgba(34, 197, 94, 0.8)'
            ]]
        ];

        $lineDeviationData = [
            'labels' => $lineLabels,
            'datasets' => [[
                'label' => __('Rata-rata Deviasi (°C)'),
                'data' => $avgDeviations,
                'backgroundColor' => 'rgba(214, 69, 80, 0.8)'
            ]]
        ];

        $this->js("
            (function() {
                  // Line Accuracy Chart
                  const accuracyCtx = document.getElementById('accuracy-chart');
                  if (window.accuracyChart) window.accuracyChart.destroy();
                  window.accuracyChart = new Chart(accuracyCtx, {
                     type: 'bar',
                     data: " . json_encode($lineAccuracyData) . ",
                     options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                              title: {
                                 display: true,
                                 text: '" . __('Akurasi Target per Line') . "'
                              }
                        },
                        scales: {
                              x: {
                                 beginAtZero: true,
                                 max: 100
                              }
                        }
                     }
                  });
                  
                  // Line Deviation Chart
                  const deviationCtx = document.getElementById('deviation-chart');
                  if (window.deviationChart) window.deviationChart.destroy();
                  window.deviationChart = new Chart(deviationCtx, {
                     type: 'bar',
                     data: " . json_encode($lineDeviationData) . ",
                     options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                              title: {
                                 display: true,
                                 text: '" . __('Rata-rata Deviasi per Line') . "'
                              }
                        }
                     }
                  });
            })();
         ");
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
            <div class="flex gap-3">
                <div>
                    <label for="device-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-select id="device-line" wire:model.live="line">
                        <option value=""></option>
                        @foreach($lines as $line)
                        <option value="{{ $line }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label for="device-position" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                    <x-select id="device-position" wire:model.live="position">
                        <option value=""></option>
                        <option value="upper">{{ __('Atas') }}</option>
                        <option value="lower">{{ __('Bawah') }}</option>
                    </x-select>
                </div>
                <div>
                    <label for="view-mode" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tampilan') }}</label>
                    <x-select id="view-mode" wire:model.live="view_mode">
                        <option value="zones">{{ __('Per Zone') }}</option>
                        <option value="lines">{{ __('Per Line') }}</option>
                    </x-select>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                    <div class="relative w-3"><x-spinner class="sm mono"></x-spinner></div>
                    <div>{{ __('Memuat...') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Statistics -->
    @if(!empty($overallStats))
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Rata-rata Deviasi') }}</div>
            <div class="text-2xl font-bold {{ abs($overallStats['avg_deviation']) <= 1 ? 'text-green-500' : (abs($overallStats['avg_deviation']) <= 3 ? 'text-yellow-500' : 'text-red-500') }}">
                {{ $overallStats['avg_deviation'] > 0 ? '+' : '' }}{{ $overallStats['avg_deviation'] }}°C
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Standar Deviasi') }}</div>
            <div class="text-2xl font-bold {{ $overallStats['std_deviation'] <= 2 ? 'text-green-500' : ($overallStats['std_deviation'] <= 4 ? 'text-yellow-500' : 'text-red-500') }}">
                {{ $overallStats['std_deviation'] }}°C
            </div>
        </div>
    </div>
    @endif

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="accuracy-chart"></canvas>
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="deviation-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    @if($view_mode === 'zones')
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium">{{ __('Analisis per Zone') }}</h3>
        </div>
        <table class="table table-sm text-sm w-full">
            <thead>
                <tr class="text-xs uppercase text-neutral-500 border-b">
                    <th class="px-4 py-3">{{ __('Zone') }}</th>
                    <th class="px-4 py-3">{{ __('Target') }}</th>
                    <th class="px-4 py-3">{{ __('Rata-rata') }}</th>
                    <th class="px-4 py-3">{{ __('Deviasi') }}</th>
                    <th class="px-4 py-3">{{ __('Std Dev') }}</th>
                    <th class="px-4 py-3">{{ __('Range') }}</th>
                    <th class="px-4 py-3">{{ __('±1°C') }}</th>
                    <th class="px-4 py-3">{{ __('±3°C') }}</th>
                    <th class="px-4 py-3">{{ __('±5°C') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($zoneVariance as $zone)
                <tr class="border-b border-neutral-100 dark:border-neutral-700">
                    <td class="px-4 py-3 font-mono font-bold">{{ $zone['zone'] }}</td>
                    <td class="px-4 py-3">{{ $zone['target'] }}°C</td>
                    <td class="px-4 py-3">{{ $zone['avg_actual'] }}°C</td>
                    <td class="px-4 py-3">
                        <span class="{{ abs($zone['avg_deviation']) <= 1 ? 'text-green-600' : (abs($zone['avg_deviation']) <= 3 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ $zone['avg_deviation'] > 0 ? '+' : '' }}{{ $zone['avg_deviation'] }}°C
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $zone['std_deviation'] }}°C</td>
                    <td class="px-4 py-3">{{ $zone['range'] }}°C</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="text-sm {{ $zone['within_1c_pct'] >= 80 ? 'text-green-600' : ($zone['within_1c_pct'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $zone['within_1c_pct'] }}%
                            </span>
                            <div class="ml-2 w-12 bg-neutral-200 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $zone['within_1c_pct'] >= 80 ? 'bg-green-500' : ($zone['within_1c_pct'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                     style="width: {{ $zone['within_1c_pct'] }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm">{{ $zone['within_3c_pct'] }}%</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm">{{ $zone['within_5c_pct'] }}%</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium">{{ __('Analisis per Line') }}</h3>
        </div>
        <table class="table table-sm text-sm w-full">
            <thead>
                <tr class="text-xs uppercase text-neutral-500 border-b">
                    <th class="px-4 py-3">{{ __('Line') }}</th>
                    <th class="px-4 py-3">{{ __('Pengukuran') }}</th>
                    <th class="px-4 py-3">{{ __('Rata-rata Deviasi') }}</th>
                    <th class="px-4 py-3">{{ __('Std Dev') }}</th>
                    <th class="px-4 py-3">{{ __('±1°C') }}</th>
                    <th class="px-4 py-3">{{ __('±3°C') }}</th>
                    <th class="px-4 py-3">{{ __('±5°C') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lineVariance as $line)
                <tr class="border-b border-neutral-100 dark:border-neutral-700">
                    <td class="px-4 py-3 font-mono font-bold">{{ sprintf('%02d', $line['line']) }}</td>
                    <td class="px-4 py-3">{{ number_format($line['count']) }}</td>
                    <td class="px-4 py-3">
                        <span class="{{ abs($line['avg_deviation']) <= 1 ? 'text-green-600' : (abs($line['avg_deviation']) <= 3 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ $line['avg_deviation'] > 0 ? '+' : '' }}{{ $line['avg_deviation'] }}°C
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $line['std_deviation'] }}°C</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="text-sm {{ $line['within_1c_pct'] >= 80 ? 'text-green-600' : ($line['within_1c_pct'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $line['within_1c_pct'] }}%
                            </span>
                            <div class="ml-2 w-12 bg-neutral-200 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $line['within_1c_pct'] >= 80 ? 'bg-green-500' : ($line['within_1c_pct'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                     style="width: {{ $line['within_1c_pct'] }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm">{{ $line['within_3c_pct'] }}%</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm">{{ $line['within_5c_pct'] }}%</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Performance Indicators -->
    <div class="mt-6 p-4 bg-neutral-50 dark:bg-neutral-900 rounded-lg">
        <h3 class="text-sm font-medium mb-3">{{ __('Indikator Performa:') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="flex items-center">
                <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                <span>{{ __('Excellent: ±1°C ≥80%, Deviasi ≤1°C') }}</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-yellow-500 rounded mr-2"></div>
                <span>{{ __('Good: ±1°C ≥60%, Deviasi ≤3°C') }}</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-red-500 rounded mr-2"></div>
                <span>{{ __('Needs Improvement: Di bawah kriteria Good') }}</span>
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript