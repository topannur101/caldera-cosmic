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
    public string $view_mode = 'sections';

    public int $progress = 0;
    public array $lines = [];
    public array $sectionSeverity = [];
    public array $lineSeverity = [];
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
    public function updated()
    {
        $this->progress = 0;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        // Phase 1: Mengambil data (0-49%)
        $this->progress = 10;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );
        
        $dSums = $this->getDSumsData();
        
        $this->progress = 49;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        // Phase 2: Menghitung metrik (49-98%)
        $this->progress = 60;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );
        
        $this->calculateSeverityAnalysis($dSums);
        
        $this->progress = 98;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        // Phase 3: Merender grafik (98-100%)
        $this->renderCharts();
        
        $this->progress = 100;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );
    }

    private function getDSumsData()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

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

        return $query->get();
    }

    private function calculateSeverityAnalysis($dSums)
    {
        $targets = InsStc::$target_values; // [75, 73, 68, 63, 58, 53, 43, 43]

        // Initialize section data
        $sectionData = [];
        $lineData = [];
        
        for ($section = 1; $section <= 8; $section++) {
            $sectionData[$section] = [
                'target' => $targets[$section - 1],
                'measurements' => [],
                'deviations' => [],
                'severity_counts' => ['minor' => 0, 'major' => 0, 'critical' => 0]
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
                    'severity_counts' => ['minor' => 0, 'major' => 0, 'critical' => 0],
                    'section_stats' => []
                ];
            }

            for ($section = 1; $section <= 8; $section++) {
                if (isset($hbValues[$section - 1])) {
                    $measurement = $hbValues[$section - 1];
                    $target = $targets[$section - 1];
                    $deviation = $measurement - $target;
                    $absDeviation = abs($deviation);

                    $sectionData[$section]['measurements'][] = $measurement;
                    $sectionData[$section]['deviations'][] = $deviation;

                    $lineData[$line]['measurements'][] = $measurement;
                    $lineData[$line]['deviations'][] = $deviation;

                    // Classify severity based on new ranges
                    if ($absDeviation > 9) {
                        $sectionData[$section]['severity_counts']['critical']++;
                        $lineData[$line]['severity_counts']['critical']++;
                    } elseif ($absDeviation >= 6) {
                        $sectionData[$section]['severity_counts']['major']++;
                        $lineData[$line]['severity_counts']['major']++;
                    } elseif ($absDeviation >= 3) {
                        $sectionData[$section]['severity_counts']['minor']++;
                        $lineData[$line]['severity_counts']['minor']++;
                    }
                    // 0-3°C range is ignored (not counted as deviation)

                    if (!isset($lineData[$line]['section_stats'][$section])) {
                        $lineData[$line]['section_stats'][$section] = [
                            'measurements' => [],
                            'deviations' => [],
                            'severity_counts' => ['minor' => 0, 'major' => 0, 'critical' => 0]
                        ];
                    }
                    $lineData[$line]['section_stats'][$section]['measurements'][] = $measurement;
                    $lineData[$line]['section_stats'][$section]['deviations'][] = $deviation;
                    
                    if ($absDeviation > 9) {
                        $lineData[$line]['section_stats'][$section]['severity_counts']['critical']++;
                    } elseif ($absDeviation >= 6) {
                        $lineData[$line]['section_stats'][$section]['severity_counts']['major']++;
                    } elseif ($absDeviation >= 3) {
                        $lineData[$line]['section_stats'][$section]['severity_counts']['minor']++;
                    }
                }
            }
        }

        // Calculate section statistics
        $this->sectionSeverity = [];
        foreach ($sectionData as $section => $data) {
            if (count($data['measurements']) > 0) {
                $totalMeasurements = count($data['measurements']);
                $totalDeviations = $data['severity_counts']['minor'] + $data['severity_counts']['major'] + $data['severity_counts']['critical'];
                $majorPlusDeviations = $data['severity_counts']['major'] + $data['severity_counts']['critical'];
                
                $this->sectionSeverity[$section] = [
                    'section' => $section,
                    'target' => $data['target'],
                    'count' => $totalMeasurements,
                    'avg_actual' => round(array_sum($data['measurements']) / count($data['measurements']), 2),
                    'avg_deviation' => round(array_sum($data['deviations']) / count($data['deviations']), 2),
                    'std_deviation' => round($this->calculateStandardDeviation($data['deviations']), 2),
                    'min_temp' => min($data['measurements']),
                    'max_temp' => max($data['measurements']),
                    'range' => max($data['measurements']) - min($data['measurements']),
                    'minor_count' => $data['severity_counts']['minor'],
                    'major_count' => $data['severity_counts']['major'],
                    'critical_count' => $data['severity_counts']['critical'],
                    'total_deviations' => $totalDeviations,
                    'major_plus_deviations' => $majorPlusDeviations,
                    'deviation_rate' => round(($totalDeviations / $totalMeasurements) * 100, 1),
                    'major_plus_rate' => round(($majorPlusDeviations / $totalMeasurements) * 100, 1),
                    'critical_rate' => round(($data['severity_counts']['critical'] / $totalMeasurements) * 100, 1),
                    'minor_pct' => round(($data['severity_counts']['minor'] / $totalMeasurements) * 100, 1),
                    'major_pct' => round(($data['severity_counts']['major'] / $totalMeasurements) * 100, 1),
                    'critical_pct' => round(($data['severity_counts']['critical'] / $totalMeasurements) * 100, 1)
                ];

                // Add severity classification
                $this->sectionSeverity[$section]['severity_class'] = $this->classifySeverity(abs($this->sectionSeverity[$section]['avg_deviation']));
            }
        }

        // Calculate line statistics
        $this->lineSeverity = [];
        foreach ($lineData as $line => $data) {
            if (count($data['measurements']) > 0) {
                $totalMeasurements = count($data['measurements']);
                $totalDeviations = $data['severity_counts']['minor'] + $data['severity_counts']['major'] + $data['severity_counts']['critical'];
                $majorPlusDeviations = $data['severity_counts']['major'] + $data['severity_counts']['critical'];
                
                $this->lineSeverity[$line] = [
                    'line' => $line,
                    'count' => $totalMeasurements,
                    'avg_deviation' => round(array_sum($data['deviations']) / count($data['deviations']), 2),
                    'std_deviation' => round($this->calculateStandardDeviation($data['deviations']), 2),
                    'minor_count' => $data['severity_counts']['minor'],
                    'major_count' => $data['severity_counts']['major'],
                    'critical_count' => $data['severity_counts']['critical'],
                    'total_deviations' => $totalDeviations,
                    'major_plus_deviations' => $majorPlusDeviations,
                    'deviation_rate' => round(($totalDeviations / $totalMeasurements) * 100, 1),
                    'major_plus_rate' => round(($majorPlusDeviations / $totalMeasurements) * 100, 1),
                    'critical_rate' => round(($data['severity_counts']['critical'] / $totalMeasurements) * 100, 1),
                    'minor_pct' => round(($data['severity_counts']['minor'] / $totalMeasurements) * 100, 1),
                    'major_pct' => round(($data['severity_counts']['major'] / $totalMeasurements) * 100, 1),
                    'critical_pct' => round(($data['severity_counts']['critical'] / $totalMeasurements) * 100, 1)
                ];
                
                // Add severity classification
                $this->lineSeverity[$line]['severity_class'] = $this->classifySeverity(abs($this->lineSeverity[$line]['avg_deviation']));
            }
        }

        // Calculate overall statistics
        $allDeviations = [];
        $totalSeverityCounts = ['minor' => 0, 'major' => 0, 'critical' => 0];
        
        foreach ($lineData as $data) {
            $allDeviations = array_merge($allDeviations, $data['deviations']);
            $totalSeverityCounts['minor'] += $data['severity_counts']['minor'];
            $totalSeverityCounts['major'] += $data['severity_counts']['major'];
            $totalSeverityCounts['critical'] += $data['severity_counts']['critical'];
        }

        if (count($allDeviations) > 0) {
            $totalMeasurements = count($allDeviations);
            $totalDeviations = $totalSeverityCounts['minor'] + $totalSeverityCounts['major'] + $totalSeverityCounts['critical'];
            $majorPlusDeviations = $totalSeverityCounts['major'] + $totalSeverityCounts['critical'];
            
            $this->overallStats = [
                'total_measurements' => $totalMeasurements,
                'total_deviations' => $totalDeviations,
                'major_plus_deviations' => $majorPlusDeviations,
                'critical_deviations' => $totalSeverityCounts['critical'],
                'avg_deviation' => round(array_sum($allDeviations) / count($allDeviations), 2),
                'std_deviation' => round($this->calculateStandardDeviation($allDeviations), 2),
                'deviation_rate' => round(($totalDeviations / $totalMeasurements) * 100, 1),
                'major_plus_rate' => round(($majorPlusDeviations / $totalMeasurements) * 100, 1),
                'critical_rate' => round(($totalSeverityCounts['critical'] / $totalMeasurements) * 100, 1),
                'minor_pct' => round(($totalSeverityCounts['minor'] / $totalMeasurements) * 100, 1),
                'major_pct' => round(($totalSeverityCounts['major'] / $totalMeasurements) * 100, 1),
                'critical_pct' => round(($totalSeverityCounts['critical'] / $totalMeasurements) * 100, 1)
            ];
            
            // Add overall severity classification
            $this->overallStats['severity_class'] = $this->classifySeverity(abs($this->overallStats['avg_deviation']));
        }
    }

    private function classifySeverity($avgDeviation): array
    {
        if ($avgDeviation < 3) {
            return ['level' => 'good', 'class' => 'text-green-600', 'bg' => 'bg-green-100 text-green-800'];
        } elseif ($avgDeviation < 6) {
            return ['level' => 'minor', 'class' => 'text-yellow-600', 'bg' => 'bg-yellow-100 text-yellow-800'];
        } elseif ($avgDeviation <= 9) {
            return ['level' => 'major', 'class' => 'text-orange-600', 'bg' => 'bg-orange-100 text-orange-800'];
        } else {
            return ['level' => 'critical', 'class' => 'text-red-600', 'bg' => 'bg-red-100 text-red-800'];
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
        if ($this->view_mode === 'sections') {
            $this->renderSectionCharts();
        } else {
            $this->renderLineCharts();
        }
    }

    private function renderSectionCharts()
    {
        // Section severity stacked chart
        $sectionLabels = array_map(fn($section) => "Section $section", array_keys($this->sectionSeverity));
        $minorCounts = array_column($this->sectionSeverity, 'minor_count');
        $majorCounts = array_column($this->sectionSeverity, 'major_count');
        $criticalCounts = array_column($this->sectionSeverity, 'critical_count');

        $severityData = [
            'labels' => $sectionLabels,
            'datasets' => [
                [
                    'label' => 'Minor (3-6°C)',
                    'data' => $minorCounts,
                    'backgroundColor' => 'rgba(255, 205, 86, 0.8)'
                ],
                [
                    'label' => 'Major (6-9°C)',
                    'data' => $majorCounts,
                    'backgroundColor' => 'rgba(255, 159, 64, 0.8)'
                ],
                [
                    'label' => 'Critical (>9°C)',
                    'data' => $criticalCounts,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.8)'
                ]
            ]
        ];

        // Section deviation chart
        $avgDeviations = array_column($this->sectionSeverity, 'avg_deviation');
        $stdDeviations = array_column($this->sectionSeverity, 'std_deviation');

        $deviationData = [
            'labels' => $sectionLabels,
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
               // Severity Chart
               const severityCtx = document.getElementById('severity-chart');
               if (window.severityChart) window.severityChart.destroy();
               window.severityChart = new Chart(severityCtx, {
                     type: 'bar',
                     data: " . json_encode($severityData) . ",
                     options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                           title: {
                                 display: true,
                                 text: '" . __('Klasifikasi Deviasi per Section') . "'
                           }
                        },
                        scales: {
                           x: {
                                 stacked: true
                           },
                           y: {
                                 stacked: true,
                                 beginAtZero: true
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
                                 text: '" . __('Deviasi per Section') . "'
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
        $lineLabels = array_map(fn($line) => "Line " . sprintf('%02d', $line), array_keys($this->lineSeverity));
        $minorCounts = array_column($this->lineSeverity, 'minor_count');
        $majorCounts = array_column($this->lineSeverity, 'major_count');
        $criticalCounts = array_column($this->lineSeverity, 'critical_count');
        $avgDeviations = array_column($this->lineSeverity, 'avg_deviation');

        $lineSeverityData = [
            'labels' => $lineLabels,
            'datasets' => [
                [
                    'label' => 'Minor (3-6°C)',
                    'data' => $minorCounts,
                    'backgroundColor' => 'rgba(255, 205, 86, 0.8)'
                ],
                [
                    'label' => 'Major (6-9°C)',
                    'data' => $majorCounts,
                    'backgroundColor' => 'rgba(255, 159, 64, 0.8)'
                ],
                [
                    'label' => 'Critical (>9°C)',
                    'data' => $criticalCounts,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.8)'
                ]
            ]
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
                  // Line Severity Chart
                  const severityCtx = document.getElementById('severity-chart');
                  if (window.severityChart) window.severityChart.destroy();
                  window.severityChart = new Chart(severityCtx, {
                     type: 'bar',
                     data: " . json_encode($lineSeverityData) . ",
                     options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                              title: {
                                 display: true,
                                 text: '" . __('Klasifikasi Deviasi per Line') . "'
                              }
                        },
                        scales: {
                              x: {
                                 stacked: true,
                                 beginAtZero: true
                              },
                              y: {
                                 stacked: true
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
                        <option value="sections">{{ __('Per Section') }}</option>
                        <option value="lines">{{ __('Per Line') }}</option>
                    </x-select>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="hidden">
                    <x-progress-bar :$progress>                       
                        <span x-text="
                        progress < 49 ? '{{ __('Mengambil data...') }}' : 
                        progress < 98 ? '{{ __('Menghitung metrik...') }}' : 
                        '{{ __('Merender grafik...') }}'
                        "></span>
                    </x-progress-bar>
                </div>
            </div>
            <div class="my-auto">                
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <x-text-button><i class="icon-ellipsis-vertical"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-slide-over', 'metrics-info')">
                            <i class="icon-info me-2"></i>{{ __('Penjelasan Metrik') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>

    <div wire:key="modals">
        <x-slide-over name="metrics-info">
            <div class="p-6 overflow-auto">
                <div class="flex justify-between items-start mb-6">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Penjelasan Metrik Klasifikasi Deviasi') }}
                    </h2>
                    <x-text-button type="button" x-on:click="window.dispatchEvent(escKey)">
                        <i class="icon-x"></i>
                    </x-text-button>
                </div>
                
                <div class="space-y-6 text-sm text-neutral-600 dark:text-neutral-400">
                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Klasifikasi Deviasi') }}</h3>
                        <p class="mb-2">{{ __('Sistem klasifikasi berdasarkan besaran deviasi absolut dari target.') }}</p>
                        <div class="space-y-2">
                            <div class="flex items-center p-2 bg-green-50 dark:bg-green-900/20 rounded">
                                <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                                <span class="font-medium">{{ __('Baik: 0-3°C') }}</span>
                                <span class="ml-auto text-xs">{{ __('Tidak dihitung sebagai deviasi') }}</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded">
                                <div class="w-4 h-4 bg-yellow-500 rounded mr-2"></div>
                                <span class="font-medium">{{ __('Minor: 3-6°C') }}</span>
                                <span class="ml-auto text-xs">{{ __('Dapat diterima') }}</span>
                            </div>
                            <div class="flex items-center p-2 bg-orange-50 dark:bg-orange-900/20 rounded">
                                <div class="w-4 h-4 bg-orange-500 rounded mr-2"></div>
                                <span class="font-medium">{{ __('Major: 6-9°C') }}</span>
                                <span class="ml-auto text-xs">{{ __('Perlu Perhatian') }}</span>
                            </div>
                            <div class="flex items-center p-2 bg-red-50 dark:bg-red-900/20 rounded">
                                <div class="w-4 h-4 bg-red-500 rounded mr-2"></div>
                                <span class="font-medium">{{ __('Critical: >9°C') }}</span>
                                <span class="ml-auto text-xs">{{ __('Perlu Tindakan Segera') }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Metrik Utama') }}</h3>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono space-y-2">
                            <div><strong>{{ __('Tingkat Deviasi:') }}</strong> {{ __('% pengukuran dengan deviasi ≥3°C') }}</div>
                            <div><strong>{{ __('Tingkat Major+:') }}</strong> {{ __('% pengukuran dengan deviasi ≥6°C') }}</div>
                            <div><strong>{{ __('Tingkat Critical:') }}</strong> {{ __('% pengukuran dengan deviasi >9°C') }}</div>
                            <div><strong>{{ __('Rata-rata Deviasi:') }}</strong> {{ __('Σ(Nilai Aktual - Target) / Total Pengukuran') }}</div>
                            <div><strong>{{ __('Standar Deviasi:') }}</strong> {{ __('Mengukur konsistensi/variabilitas deviasi') }}</div>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Target Performa') }}</h3>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs space-y-1">
                            <div>{{ __('• Tingkat Deviasi: <15% (target ideal)') }}</div>
                            <div>{{ __('• Tingkat Major+: <8% (dapat diterima)') }}</div>
                            <div>{{ __('• Tingkat Critical: <3% (batas maksimal)') }}</div>
                            <div>{{ __('• Standar Deviasi: <3°C (konsisten)') }}</div>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Interpretasi Praktis') }}</h3>
                        <div class="space-y-2 text-xs">
                            <div><strong>{{ __('Rata-rata Deviasi Rendah + StdDev Rendah:') }}</strong> {{ __('Performa sangat baik, konsisten mendekati target') }}</div>
                            <div><strong>{{ __('Rata-rata Deviasi Rendah + StdDev Tinggi:') }}</strong> {{ __('Akurat tapi tidak konsisten, perlu stabilisasi') }}</div>
                            <div><strong>{{ __('Rata-rata Deviasi Tinggi + StdDev Rendah:') }}</strong> {{ __('Konsisten tapi bias sistemik, perlu kalibrasi') }}</div>
                            <div><strong>{{ __('Rata-rata Deviasi Tinggi + StdDev Tinggi:') }}</strong> {{ __('Performa buruk, perlu investigasi menyeluruh') }}</div>
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Rekomendasi Tindakan') }}</h3>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-start">
                                <div class="w-4 h-4 bg-green-500 rounded mr-2 mt-1 flex-shrink-0"></div>
                                <div>
                                    <strong>{{ __('Baik (0-3°C):') }}</strong> {{ __('Pertahankan kondisi saat ini, monitoring rutin') }}
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="w-4 h-4 bg-yellow-500 rounded mr-2 mt-1 flex-shrink-0"></div>
                                <div>
                                    <strong>{{ __('Minor (3-6°C):') }}</strong> {{ __('Monitoring intensif, evaluasi trend') }}
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="w-4 h-4 bg-orange-500 rounded mr-2 mt-1 flex-shrink-0"></div>
                                <div>
                                    <strong>{{ __('Major (6-9°C):') }}</strong> {{ __('Review setting, periksa sensor, evaluasi proses') }}
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="w-4 h-4 bg-red-500 rounded mr-2 mt-1 flex-shrink-0"></div>
                                <div>
                                    <strong>{{ __('Critical (>9°C):') }}</strong> {{ __('Inspeksi menyeluruh') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slide-over>
    </div>

    <!-- Overall Statistics -->
    @if(!empty($overallStats))
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Rata-rata Deviasi') }}</div>
            <div class="flex justify-between">
                <div class="text-2xl font-bold {{ $overallStats['severity_class']['class'] }}">
                    {{ $overallStats['avg_deviation'] > 0 ? '+' : '' }}{{ $overallStats['avg_deviation'] }}°C
                </div>
                <div class="text-xs">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $overallStats['severity_class']['bg'] }}">
                        {{ ucfirst($overallStats['severity_class']['level']) }}
                    </span>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Tingkat Deviasi') }}</div>
            <div class="text-2xl font-bold {{ $overallStats['deviation_rate'] <= 15 ? 'text-green-500' : ($overallStats['deviation_rate'] <= 25 ? 'text-yellow-500' : 'text-red-500') }}">
                {{ $overallStats['deviation_rate'] }}%
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Tingkat Major+') }}</div>
            <div class="text-2xl font-bold {{ $overallStats['major_plus_rate'] <= 8 ? 'text-green-500' : ($overallStats['major_plus_rate'] <= 15 ? 'text-yellow-500' : 'text-red-500') }}">
                {{ $overallStats['major_plus_rate'] }}%
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Tingkat Critical') }}</div>
            <div class="text-2xl font-bold {{ $overallStats['critical_rate'] <= 3 ? 'text-green-500' : ($overallStats['critical_rate'] <= 8 ? 'text-yellow-500' : 'text-red-500') }}">
                {{ $overallStats['critical_rate'] }}%
            </div>
            <!-- <div class="text-xs text-neutral-500">{{ __('Target: <3%') }}</div> -->
        </div>
    </div>
    @endif

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="severity-chart"></canvas>
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="deviation-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    @if($view_mode === 'sections')
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium">{{ __('Analisis per Section') }}</h3>
        </div>
        <table class="table table-sm text-sm w-full">
            <thead>
                <tr class="text-xs uppercase text-neutral-500 border-b">
                    <th class="px-4 py-3">{{ __('Section') }}</th>
                    <th class="px-4 py-3">{{ __('Target') }}</th>
                    <th class="px-4 py-3">{{ __('Rata-rata') }}</th>
                    <th colspan="2" class="px-4 py-3">{{ __('Deviasi') }}</th>
                    <th class="px-4 py-3">{{ __('Std Dev') }}</th>
                    <th class="px-4 py-3">{{ __('Rentang') }}</th>
                    <th class="px-4 py-3">{{ __('Minor') }}</th>
                    <th class="px-4 py-3">{{ __('Major') }}</th>
                    <th class="px-4 py-3">{{ __('Kritikal') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sectionSeverity as $section)
                <tr class="border-b border-neutral-100 dark:border-neutral-700">
                    <td class="px-4 py-3 font-mono font-bold">{{ $section['section'] }}</td>
                    <td class="px-4 py-3">{{ $section['target'] }}°C</td>
                    <td class="px-4 py-3">{{ $section['avg_actual'] }}°C</td>
                    <td class="px-4 py-3">
                        <span class="{{ $section['severity_class']['class'] }}">
                            {{ $section['avg_deviation'] > 0 ? '+' : '' }}{{ $section['avg_deviation'] }}°C
                        </span>
                    </td>
                    <td>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $section['severity_class']['bg'] }}">
                            {{ ucfirst($section['severity_class']['level']) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $section['std_deviation'] }}°C</td>
                    <td class="px-4 py-3">{{ $section['range'] }}°C</td>
                    <td class="px-4 py-3">
                        <div class="text-sm">{{ $section['minor_count'] }}</div>
                        <div class="text-xs text-neutral-500">({{ $section['minor_pct'] }}%)</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm">{{ $section['major_count'] }}</div>
                        <div class="text-xs text-neutral-500">({{ $section['major_pct'] }}%)</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm">{{ $section['critical_count'] }}</div>
                        <div class="text-xs text-neutral-500">({{ $section['critical_pct'] }}%)</div>
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
                    <th colspan="2" class="px-4 py-3">{{ __('Rata-rata Deviasi') }}</th>
                    <th class="px-4 py-3">{{ __('Std Dev') }}</th>
                    <th class="px-4 py-3">{{ __('Tingkat Deviasi') }}</th>
                    <th class="px-4 py-3">{{ __('Minor') }}</th>
                    <th class="px-4 py-3">{{ __('Major') }}</th>
                    <th class="px-4 py-3">{{ __('Critical') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lineSeverity as $line)
                <tr class="border-b border-neutral-100 dark:border-neutral-700">
                    <td class="px-4 py-3 font-mono font-bold">{{ sprintf('%02d', $line['line']) }}</td>
                    <td class="px-4 py-3">{{ number_format($line['count']) }}</td>
                    <td class="px-4 py-3">
                        <span class="{{ $line['severity_class']['class'] }}">
                            {{ $line['avg_deviation'] > 0 ? '+' : '' }}{{ $line['avg_deviation'] }}°C
                        </span>
                    </td>
                    <td class="px-4 py-3">                        
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $line['severity_class']['bg'] }}">
                            {{ ucfirst($line['severity_class']['level']) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $line['std_deviation'] }}°C</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <div class="mr-2 w-16 bg-neutral-200 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $line['deviation_rate'] <= 15 ? 'bg-green-500' : ($line['deviation_rate'] <= 25 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                     style="width: {{ min($line['deviation_rate'], 100) }}%"></div>
                            </div>
                            <span class="text-sm {{ $line['deviation_rate'] <= 15 ? 'text-green-600' : ($line['deviation_rate'] <= 25 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $line['deviation_rate'] }}%
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm">{{ $line['minor_count'] }}</div>
                        <div class="text-xs text-neutral-500">({{ $line['minor_pct'] }}%)</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm">{{ $line['major_count'] }}</div>
                        <div class="text-xs text-neutral-500">({{ $line['major_pct'] }}%)</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm">{{ $line['critical_count'] }}</div>
                        <div class="text-xs text-neutral-500">({{ $line['critical_pct'] }}%)</div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript