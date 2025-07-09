<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\InsCtcMetric;
use App\Models\InsCtcMachine;
use App\Models\InsCtcRecipe;
use Carbon\Carbon;
use App\Traits\HasDateRangeFilter;

new #[Layout('layouts.app')] class extends Component {
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public $machine_id;

    #[Url]
    public string $recipe_name = '';

    public array $machines = [];
    public array $correctionStats = [];
    public array $dailyStats = [];
    public array $shiftStats = [];
    public array $machineComparison = [];
    public array $effectivenessData = [];

    public function mount()
    {
        if (!$this->start_at || !$this->end_at) {
            $this->setThisWeek();
        }

        $this->machines = InsCtcMachine::orderBy('line')->get()->pluck('line', 'id')->toArray();
    }

    private function getMetricsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsCtcMetric::with(['ins_ctc_machine', 'ins_ctc_recipe', 'ins_rubber_batch'])
            ->whereBetween('created_at', [$start, $end]);

        if ($this->machine_id) {
            $query->where('ins_ctc_machine_id', $this->machine_id);
        }

        if ($this->recipe_name) {
            $query->whereHas('ins_ctc_recipe', function ($q) {
                $q->where('name', 'like', '%' . $this->recipe_name . '%');
            });
        }

        return $query->orderBy('created_at', 'DESC');
    }

    #[On('update')]
    public function update()
    {
        $metrics = $this->getMetricsQuery()->get();
        
        $this->calculateCorrectionStats($metrics);
        $this->calculateDailyStats($metrics);
        $this->calculateShiftStats($metrics);
        $this->calculateMachineComparison($metrics);
        $this->calculateEffectivenessData($metrics);
        
        $this->generateEfficiencyTrendChart();
        $this->generateAutoVsManualChart();
        $this->generateCorrectionEffectivenessChart();
        $this->generateMachineComparisonChart();
        $this->generateShiftAnalysisChart();
        $this->generateIndividualCorrectionsChart();
    }

    private function calculateCorrectionStats($metrics)
    {
        $autoCount = $metrics->where('correction_uptime', '>', 50)->count();
        $manualCount = $metrics->where('correction_uptime', '<=', 50)->count();
        $totalBatches = $metrics->count();

        $this->correctionStats = [
            'total_batches' => $totalBatches,
            'auto_batches' => $autoCount,
            'manual_batches' => $manualCount,
            'auto_percentage' => $totalBatches > 0 ? round(($autoCount / $totalBatches) * 100, 1) : 0,
            'avg_correction_uptime' => round($metrics->avg('correction_uptime') ?? 0, 1),
            'avg_correction_rate' => round($metrics->avg('correction_rate') ?? 0, 1),
            'total_corrections' => $this->calculateTotalCorrections($metrics),
            'correction_effectiveness' => $this->calculateCorrectionEffectiveness($metrics),
        ];
    }

    private function calculateDailyStats($metrics)
    {
        $this->dailyStats = $metrics->groupBy(function ($metric) {
            return Carbon::parse($metric->created_at)->format('Y-m-d');
        })->map(function ($dayMetrics) {
            $autoCount = $dayMetrics->where('correction_uptime', '>', 50)->count();
            $totalCount = $dayMetrics->count();
            
            return [
                'date' => Carbon::parse($dayMetrics->first()->created_at)->format('Y-m-d'),
                'batch_count' => $totalCount,
                'avg_cu' => round($dayMetrics->avg('correction_uptime') ?? 0, 1),
                'avg_cr' => round($dayMetrics->avg('correction_rate') ?? 0, 1),
                'auto_percentage' => $totalCount > 0 ? round(($autoCount / $totalCount) * 100, 1) : 0,
                'avg_mae' => round($dayMetrics->avg('t_mae') ?? 0, 2),
                'corrections_left' => $this->calculateCorrections($dayMetrics, 'left'),
                'corrections_right' => $this->calculateCorrections($dayMetrics, 'right'),
            ];
        })->values()->toArray();
    }

    private function calculateShiftStats($metrics)
    {
        $this->shiftStats = $metrics->groupBy(function ($metric) {
            $hour = Carbon::parse($metric->created_at)->hour;
            if ($hour >= 6 && $hour < 14) return 1; // Shift 1: 06:00-14:00
            if ($hour >= 14 && $hour < 22) return 2; // Shift 2: 14:00-22:00
            return 3; // Shift 3: 22:00-06:00
        })->map(function ($shiftMetrics, $shift) {
            $autoCount = $shiftMetrics->where('correction_uptime', '>', 50)->count();
            $totalCount = $shiftMetrics->count();
            
            return [
                'shift' => $shift,
                'batch_count' => $totalCount,
                'auto_count' => $autoCount,
                'manual_count' => $totalCount - $autoCount,
                'auto_percentage' => $totalCount > 0 ? round(($autoCount / $totalCount) * 100, 1) : 0,
                'avg_cu' => round($shiftMetrics->avg('correction_uptime') ?? 0, 1),
                'avg_cr' => round($shiftMetrics->avg('correction_rate') ?? 0, 1),
                'avg_mae' => round($shiftMetrics->avg('t_mae') ?? 0, 2),
                'quality_pass_rate' => $this->calculateQualityPassRate($shiftMetrics),
            ];
        })->sortBy('shift')->values()->toArray();
    }

    private function calculateMachineComparison($metrics)
    {
        $this->machineComparison = $metrics->groupBy('ins_ctc_machine_id')
            ->map(function ($machineMetrics) {
                $machine = $machineMetrics->first()->ins_ctc_machine;
                $autoCount = $machineMetrics->where('correction_uptime', '>', 50)->count();
                $totalCount = $machineMetrics->count();
                
                return [
                    'machine_line' => $machine->line ?? 'N/A',
                    'batch_count' => $totalCount,
                    'auto_percentage' => $totalCount > 0 ? round(($autoCount / $totalCount) * 100, 1) : 0,
                    'avg_cu' => round($machineMetrics->avg('correction_uptime') ?? 0, 1),
                    'avg_cr' => round($machineMetrics->avg('correction_rate') ?? 0, 1),
                    'correction_efficiency' => $this->calculateCorrectionEfficiency($machineMetrics),
                    'quality_improvement' => $this->calculateQualityImprovement($machineMetrics),
                ];
            })
            ->sortBy('machine_line')
            ->values()
            ->toArray();
    }

    private function calculateEffectivenessData($metrics)
    {
        $this->effectivenessData = $metrics->map(function ($metric) {
            return [
                'correction_rate' => $metric->correction_rate,
                'mae' => $metric->t_mae,
                'correction_uptime' => $metric->correction_uptime,
                'is_auto' => $metric->correction_uptime > 50,
                'quality_pass' => $metric->t_mae <= 1.0,
            ];
        })->toArray();
    }

    private function calculateTotalCorrections($metrics): int
    {
        $total = 0;
        foreach ($metrics as $metric) {
            if ($metric->data && is_array($metric->data)) {
                foreach ($metric->data as $point) {
                    $actionLeft = $point[2] ?? 0;
                    $actionRight = $point[3] ?? 0;
                    if ($actionLeft > 0) $total++;
                    if ($actionRight > 0) $total++;
                }
            }
        }
        return $total;
    }

    private function calculateCorrections($metrics, $side): int
    {
        $total = 0;
        foreach ($metrics as $metric) {
            if ($metric->data && is_array($metric->data)) {
                foreach ($metric->data as $point) {
                    if ($side === 'left' && ($point[2] ?? 0) > 0) $total++;
                    if ($side === 'right' && ($point[3] ?? 0) > 0) $total++;
                }
            }
        }
        return $total;
    }

    private function calculateCorrectionEffectiveness($metrics): float
    {
        $highCorrectionBatches = $metrics->where('correction_rate', '>', 20);
        if ($highCorrectionBatches->count() === 0) return 0;
        
        $avgQualityHighCorrection = $highCorrectionBatches->avg('t_mae');
        $avgQualityOverall = $metrics->avg('t_mae');
        
        if ($avgQualityOverall === 0) return 0;
        
        $improvement = (($avgQualityOverall - $avgQualityHighCorrection) / $avgQualityOverall) * 100;
        return round(max(0, $improvement), 1);
    }

    private function calculateCorrectionEfficiency($metrics): float
    {
        $avgCU = $metrics->avg('correction_uptime');
        $avgCR = $metrics->avg('correction_rate');
        $qualityPassRate = $this->calculateQualityPassRate($metrics);
        
        // Efficiency score: balance between automation and quality
        $efficiency = ($avgCU * 0.4) + ($qualityPassRate * 0.6) - ($avgCR * 0.1);
        return round(max(0, min(100, $efficiency)), 1);
    }

    private function calculateQualityImprovement($metrics): float
    {
        $autoBatches = $metrics->where('correction_uptime', '>', 50);
        $manualBatches = $metrics->where('correction_uptime', '<=', 50);
        
        if ($autoBatches->count() === 0 || $manualBatches->count() === 0) return 0;
        
        $autoQuality = $autoBatches->avg('t_mae');
        $manualQuality = $manualBatches->avg('t_mae');
        
        if ($manualQuality === 0) return 0;
        
        $improvement = (($manualQuality - $autoQuality) / $manualQuality) * 100;
        return round($improvement, 1);
    }

    private function calculateQualityPassRate($metrics): float
    {
        if ($metrics->count() === 0) return 0;
        $passCount = $metrics->where('t_mae', '<=', 1.0)->count();
        return round(($passCount / $metrics->count()) * 100, 1);
    }

    private function generateEfficiencyTrendChart()
    {
        $chartData = [
            'labels' => array_column($this->dailyStats, 'date'),
            'datasets' => [
                [
                    'label' => 'Correction Uptime (%)',
                    'data' => array_column($this->dailyStats, 'avg_cu'),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Correction Rate (%)',
                    'data' => array_column($this->dailyStats, 'avg_cr'),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Auto Operation (%)',
                    'data' => array_column($this->dailyStats, 'auto_percentage'),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ]
        ];

        $this->js("
            const efficiencyCtx = document.getElementById('efficiency-trend-chart');
            if (window.efficiencyChart) window.efficiencyChart.destroy();
            
            window.efficiencyChart = new Chart(efficiencyCtx, {
                type: 'line',
                data: " . json_encode($chartData) . ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'CU & CR (%)'
                            },
                            min: 0,
                            max: 100
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Auto Operation (%)'
                            },
                            min: 0,
                            max: 100,
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        ");
    }

    private function generateAutoVsManualChart()
    {
        $autoData = [
            'labels' => ['Auto', 'Manual'],
            'datasets' => [
                [
                    'data' => [
                        $this->correctionStats['auto_batches'],
                        $this->correctionStats['manual_batches']
                    ],
                    'backgroundColor' => ['#10B981', '#EF4444'],
                    'borderWidth' => 1,
                ]
            ]
        ];

        $this->js("
            const autoCtx = document.getElementById('auto-vs-manual-chart');
            if (window.autoChart) window.autoChart.destroy();
            
            window.autoChart = new Chart(autoCtx, {
                type: 'doughnut',
                data: " . json_encode($autoData) . ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        ");
    }

    private function generateCorrectionEffectivenessChart()
    {
        // Create scatter plot data for CR vs MAE
        $effectivenessPlotData = array_map(function($item) {
            return [
                'x' => $item['correction_rate'],
                'y' => $item['mae'],
            ];
        }, $this->effectivenessData);

        $chartData = [
            'datasets' => [
                [
                    'label' => 'Correction Rate vs MAE',
                    'data' => $effectivenessPlotData,
                    'backgroundColor' => '#8B5CF6',
                    'borderColor' => '#7C3AED',
                    'pointRadius' => 4,
                ]
            ]
        ];

        $this->js("
            const effectivenessCtx = document.getElementById('correction-effectiveness-chart');
            if (window.effectivenessChart) window.effectivenessChart.destroy();
            
            window.effectivenessChart = new Chart(effectivenessCtx, {
                type: 'scatter',
                data: " . json_encode($chartData) . ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Correction Rate (%)'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'MAE (mm)'
                            }
                        }
                    }
                }
            });
        ");
    }

    private function generateMachineComparisonChart()
    {
        if (empty($this->machineComparison)) {
            return;
        }

        $chartData = [
            'labels' => array_map(function($machine) {
                return 'Line ' . sprintf('%02d', $machine['machine_line']);
            }, $this->machineComparison),
            'datasets' => [
                [
                    'label' => 'Auto Operation %',
                    'data' => array_column($this->machineComparison, 'auto_percentage'),
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => 'Correction Efficiency',
                    'data' => array_column($this->machineComparison, 'correction_efficiency'),
                    'backgroundColor' => '#3B82F6',
                ]
            ]
        ];

        $this->js("
            const machineCtx = document.getElementById('machine-comparison-chart');
            if (window.machineComparisonChart) window.machineComparisonChart.destroy();
            
            window.machineComparisonChart = new Chart(machineCtx, {
                type: 'bar',
                data: " . json_encode($chartData) . ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Percentage (%)'
                            }
                        }
                    }
                }
            });
        ");
    }

    private function generateShiftAnalysisChart()
    {
        $chartData = [
            'labels' => array_map(function($shift) {
                return 'Shift ' . $shift['shift'];
            }, $this->shiftStats),
            'datasets' => [
                [
                    'label' => 'Auto Batches',
                    'data' => array_column($this->shiftStats, 'auto_count'),
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => 'Manual Batches',
                    'data' => array_column($this->shiftStats, 'manual_count'),
                    'backgroundColor' => '#EF4444',
                ]
            ]
        ];

        $this->js("
            const shiftCtx = document.getElementById('shift-analysis-chart');
            if (window.shiftChart) window.shiftChart.destroy();
            
            window.shiftChart = new Chart(shiftCtx, {
                type: 'bar',
                data: " . json_encode($chartData) . ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            title: {
                                display: true,
                                text: 'Jumlah Batch'
                            }
                        }
                    }
                }
            });
        ");
    }

    private function generateIndividualCorrectionsChart()
    {
        $chartData = [
            'labels' => array_column($this->dailyStats, 'date'),
            'datasets' => [
                [
                    'label' => 'Koreksi Kiri',
                    'data' => array_column($this->dailyStats, 'corrections_left'),
                    'backgroundColor' => '#F59E0B',
                    'borderColor' => '#D97706',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Koreksi Kanan',
                    'data' => array_column($this->dailyStats, 'corrections_right'),
                    'backgroundColor' => '#8B5CF6',
                    'borderColor' => '#7C3AED',
                    'borderWidth' => 1,
                ]
            ]
        ];

        $this->js("
            const individualCtx = document.getElementById('individual-corrections-chart');
            if (window.individualChart) window.individualChart.destroy();
            
            window.individualChart = new Chart(individualCtx, {
                type: 'bar',
                data: " . json_encode($chartData) . ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Jumlah Koreksi'
                            }
                        }
                    }
                }
            });
        ");
    }

    public function updated()
    {
        $this->update();
    }
};

?>

<div>
    <!-- Filter Section -->
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
                                <x-dropdown-link href="#" wire:click.prevent="setToday">
                                    {{ __('Hari ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                    {{ __('Kemarin') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
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
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" type="date" class="w-40" />
                    <x-text-input wire:model.live="end_at" type="date" class="w-40" />
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grid grid-cols-2 lg:flex gap-3">
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-select wire:model.live="machine_id" class="w-full lg:w-20">
                        <option value=""></option>
                        @foreach($machines as $id => $line)
                            <option value="{{ $id }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>
                
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Resep') }}</label>
                    <x-text-input wire:model.live.debounce.500ms="recipe_name" class="w-full lg:w-32" />
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                    <div class="relative w-3">
                        <x-spinner class="sm mono"></x-spinner>
                    </div>
                    <div>
                        {{ __('Memuat...') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __('Auto Operation') }}</div>
            <div class="text-2xl font-bold text-green-600">{{ $correctionStats['auto_percentage'] ?? 0 }}<span class="text-sm font-normal">%</span></div>
            <div class="text-xs text-neutral-500">{{ ($correctionStats['auto_batches'] ?? 0) . '/' . ($correctionStats['total_batches'] ?? 0) }} {{ __('batch') }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __('Avg Correction Uptime') }}</div>
            <div class="text-2xl font-bold">{{ $correctionStats['avg_correction_uptime'] ?? 0 }}<span class="text-sm font-normal">%</span></div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __('Avg Correction Rate') }}</div>
            <div class="text-2xl font-bold">{{ $correctionStats['avg_correction_rate'] ?? 0 }}<span class="text-sm font-normal">%</span></div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __('Correction Effectiveness') }}</div>
            <div class="text-2xl font-bold text-blue-600">{{ $correctionStats['correction_effectiveness'] ?? 0 }}<span class="text-sm font-normal">%</span></div>
        </div>
    </div>

    <!-- Efficiency Trend Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium mb-4">{{ __('Tren Efisiensi Koreksi') }}</h3>
        <div class="h-80">
            <canvas id="efficiency-trend-chart" wire:ignore></canvas>
        </div>
    </div>

    <!-- Charts Grid Row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Auto vs Manual Distribution -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('Distribusi Auto vs Manual') }}</h3>
            <div class="h-80">
                <canvas id="auto-vs-manual-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Correction Effectiveness Scatter -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('Efektivitas Koreksi (CR vs MAE)') }}</h3>
            <div class="h-80">
                <canvas id="correction-effectiveness-chart" wire:ignore></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Grid Row 2 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Machine Comparison -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('Perbandingan Performa Mesin') }}</h3>
            <div class="h-80">
                <canvas id="machine-comparison-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Shift Analysis -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('Analisis per Shift') }}</h3>
            <div class="h-80">
                <canvas id="shift-analysis-chart" wire:ignore></canvas>
            </div>
        </div>
    </div>

    <!-- Individual Corrections Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium mb-4">{{ __('Pola Koreksi Individual (Kiri vs Kanan)') }}</h3>
        <div class="h-80">
            <canvas id="individual-corrections-chart" wire:ignore></canvas>
        </div>
    </div>

    <!-- Shift Statistics Table -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
        <h3 class="text-lg font-medium mb-4">{{ __('Statistik Detail per Shift') }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                <thead class="bg-neutral-50 dark:bg-neutral-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __('Shift') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __('Total Batch') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __('Auto Operation') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __('Avg CU') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __('Avg CR') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __('Avg MAE') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __('Quality Pass Rate') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach($shiftStats as $shift)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                {{ __('Shift') }} {{ $shift['shift'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                {{ $shift['batch_count'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $shift['auto_percentage'] > 70 ? 'bg-green-100 text-green-800' : ($shift['auto_percentage'] > 40 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $shift['auto_percentage'] }}%
                                </span>
                                <div class="text-xs text-neutral-400">{{ $shift['auto_count'] }}/{{ $shift['batch_count'] }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                {{ $shift['avg_cu'] }}%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                {{ $shift['avg_cr'] }}%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                <span class="{{ $shift['avg_mae'] <= 1.0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $shift['avg_mae'] }} mm
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $shift['quality_pass_rate'] > 80 ? 'bg-green-100 text-green-800' : ($shift['quality_pass_rate'] > 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $shift['quality_pass_rate'] }}%
                                </span>
                            </td>
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