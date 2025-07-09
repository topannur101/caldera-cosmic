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
    public array $summaryStats = [];
    public array $dailyStats = [];
    public array $correctionStats = [];
    public array $machineComparison = [];

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
        
        $this->calculateSummaryStats($metrics);
        $this->calculateDailyStats($metrics);
        $this->calculateCorrectionStats($metrics);
        $this->calculateMachineComparison($metrics);
        
        $this->generatePerformanceTrendChart();
        $this->generateCorrectionAnalyticsChart();
        $this->generateMachineComparisonChart();
        $this->generatePerformanceDistributionChart();
    }

    private function calculateSummaryStats($metrics)
    {
        $this->summaryStats = [
            'total_batches' => $metrics->count(),
            'avg_mae' => round($metrics->avg('t_mae') ?? 0, 2),
            'avg_ssd' => round($metrics->avg('t_ssd') ?? 0, 2),
            'avg_balance' => round(abs($metrics->avg('t_balance') ?? 0), 2),
            'avg_correction_uptime' => round($metrics->avg('correction_uptime') ?? 0, 1),
            'avg_duration' => $this->calculateAverageDuration($metrics),
            'quality_pass_rate' => $this->calculateQualityPassRate($metrics),
        ];
    }

    private function calculateDailyStats($metrics)
    {
        $this->dailyStats = $metrics->groupBy(function ($metric) {
            return Carbon::parse($metric->created_at)->format('Y-m-d');
        })->map(function ($dayMetrics) {
            return [
                'date' => Carbon::parse($dayMetrics->first()->created_at)->format('Y-m-d'),
                'batch_count' => $dayMetrics->count(),
                'avg_mae' => round($dayMetrics->avg('t_mae') ?? 0, 2),
                'avg_ssd' => round($dayMetrics->avg('t_ssd') ?? 0, 2),
                'avg_balance' => round(abs($dayMetrics->avg('t_balance') ?? 0), 2),
                'correction_uptime' => round($dayMetrics->avg('correction_uptime') ?? 0, 1),
            ];
        })->values()->toArray();
    }

    private function calculateCorrectionStats($metrics)
    {
        $autoCount = $metrics->where('correction_uptime', '>', 50)->count();
        $manualCount = $metrics->where('correction_uptime', '<=', 50)->count();

        $this->correctionStats = [
            'auto_count' => $autoCount,
            'manual_count' => $manualCount,
            'auto_percentage' => $metrics->count() > 0 ? round(($autoCount / $metrics->count()) * 100, 1) : 0,
            'avg_correction_rate' => round($metrics->avg('correction_rate') ?? 0, 1),
            'high_correction_batches' => $metrics->where('correction_rate', '>', 20)->count(),
        ];
    }

    private function calculateMachineComparison($metrics)
    {
        $this->machineComparison = $metrics->groupBy('ins_ctc_machine_id')
            ->map(function ($machineMetrics) {
                $machine = $machineMetrics->first()->ins_ctc_machine;
                return [
                    'machine_line' => $machine->line ?? 'N/A',
                    'batch_count' => $machineMetrics->count(),
                    'avg_mae' => round($machineMetrics->avg('t_mae') ?? 0, 2),
                    'avg_ssd' => round($machineMetrics->avg('t_ssd') ?? 0, 2),
                    'correction_uptime' => round($machineMetrics->avg('correction_uptime') ?? 0, 1),
                    'quality_score' => $this->calculateMachineQualityScore($machineMetrics),
                ];
            })
            ->sortBy('machine_line')
            ->values()
            ->toArray();
    }

    private function calculateAverageDuration($metrics): string
    {
        $totalSeconds = 0;
        $validCount = 0;

        foreach ($metrics as $metric) {
            if ($metric->data && is_array($metric->data) && count($metric->data) >= 2) {
                $firstTimestamp = $metric->data[0][0] ?? null;
                $lastTimestamp = $metric->data[count($metric->data) - 1][0] ?? null;
                
                if ($firstTimestamp && $lastTimestamp) {
                    try {
                        $start = Carbon::parse($firstTimestamp);
                        $end = Carbon::parse($lastTimestamp);
                        $totalSeconds += $end->diffInSeconds($start);
                        $validCount++;
                    } catch (Exception $e) {
                        // Skip invalid timestamps
                    }
                }
            }
        }

        if ($validCount === 0) {
            return '00:00:00';
        }

        $avgSeconds = $totalSeconds / $validCount;
        return gmdate('H:i:s', $avgSeconds);
    }

    private function calculateQualityPassRate($metrics): float
    {
        if ($metrics->count() === 0) {
            return 0;
        }

        $passCount = $metrics->where('t_mae', '<=', 1.0)->count();
        return round(($passCount / $metrics->count()) * 100, 1);
    }

    private function calculateMachineQualityScore($metrics): float
    {
        $avgMae = $metrics->avg('t_mae') ?? 0;
        $avgSsd = $metrics->avg('t_ssd') ?? 0;
        $correctionUptime = $metrics->avg('correction_uptime') ?? 0;

        $score = 100;
        
        // Penalize high MAE
        if ($avgMae > 0.5) {
            $score -= ($avgMae - 0.5) * 40;
        }
        
        // Penalize high SSD  
        if ($avgSsd > 0.5) {
            $score -= ($avgSsd - 0.5) * 30;
        }
        
        // Bonus for good correction uptime
        if ($correctionUptime > 70) {
            $score += 10;
        } elseif ($correctionUptime < 30) {
            $score -= 15;
        }

        return round(max(0, min(100, $score)), 1);
    }

    private function generatePerformanceTrendChart()
    {
        $chartData = [
            'labels' => array_column($this->dailyStats, 'date'),
            'datasets' => [
                [
                    'label' => 'MAE',
                    'data' => array_column($this->dailyStats, 'avg_mae'),
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'SSD', 
                    'data' => array_column($this->dailyStats, 'avg_ssd'),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Balance',
                    'data' => array_column($this->dailyStats, 'avg_balance'),
                    'borderColor' => '#8B5CF6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'tension' => 0.4,
                ],
            ]
        ];

        $this->js("
            const trendCtx = document.getElementById('performance-trend-chart');
            if (window.performanceTrendChart) window.performanceTrendChart.destroy();
            
            window.performanceTrendChart = new Chart(trendCtx, {
                type: 'line',
                data: " . json_encode($chartData) . ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Tanggal'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Nilai (mm)'
                            },
                            beginAtZero: true
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        ");
    }

    private function generateCorrectionAnalyticsChart()
    {
        $correctionData = [
            'labels' => array_column($this->dailyStats, 'date'),
            'datasets' => [
                [
                    'label' => 'Correction Uptime (%)',
                    'data' => array_column($this->dailyStats, 'correction_uptime'),
                    'backgroundColor' => '#10B981',
                    'borderColor' => '#059669',
                    'borderWidth' => 2,
                    'type' => 'bar',
                ],
                [
                    'label' => 'Jumlah Batch',
                    'data' => array_column($this->dailyStats, 'batch_count'),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'type' => 'line',
                    'tension' => 0.4,
                    'yAxisID' => 'y1'
                ]
            ]
        ];

        $this->js("
            const correctionCtx = document.getElementById('correction-analytics-chart');
            if (window.correctionChart) window.correctionChart.destroy();
            
            window.correctionChart = new Chart(correctionCtx, {
                type: 'bar',
                data: " . json_encode($correctionData) . ",
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
                                text: 'Correction Uptime (%)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Jumlah Batch'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
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
                    'label' => 'Quality Score',
                    'data' => array_column($this->machineComparison, 'quality_score'),
                    'backgroundColor' => '#8B5CF6',
                    'borderColor' => '#7C3AED',
                    'borderWidth' => 1,
                ]
            ]
        ];

        $this->js("
            const machineCtx = document.getElementById('machine-comparison-chart');
            if (window.machineChart) window.machineChart.destroy();
            
            window.machineChart = new Chart(machineCtx, {
                type: 'bar',
                data: " . json_encode($chartData) . ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Quality Score (%)'
                            }
                        }
                    }
                }
            });
        ");
    }

    private function generatePerformanceDistributionChart()
    {
        $metrics = $this->getMetricsQuery()->get();
        
        // Create distribution buckets for MAE
        $maeBuckets = [
            '0.0-0.5' => $metrics->whereBetween('t_mae', [0, 0.5])->count(),
            '0.5-1.0' => $metrics->whereBetween('t_mae', [0.5, 1.0])->count(),
            '1.0-1.5' => $metrics->whereBetween('t_mae', [1.0, 1.5])->count(),
            '1.5+' => $metrics->where('t_mae', '>', 1.5)->count(),
        ];

        $distributionData = [
            'labels' => array_keys($maeBuckets),
            'datasets' => [
                [
                    'label' => 'Jumlah Batch',
                    'data' => array_values($maeBuckets),
                    'backgroundColor' => [
                        '#10B981',
                        '#F59E0B', 
                        '#EF4444',
                        '#7F1D1D'
                    ],
                    'borderWidth' => 1,
                ]
            ]
        ];

        $this->js("
            const distributionCtx = document.getElementById('performance-distribution-chart');
            if (window.distributionChart) window.distributionChart.destroy();
            
            window.distributionChart = new Chart(distributionCtx, {
                type: 'doughnut',
                data: " . json_encode($distributionData) . ",
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
            <div class="text-sm text-neutral-500 mb-1">{{ __('Total Batch') }}</div>
            <div class="text-2xl font-bold">{{ number_format($summaryStats['total_batches'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __('Rata-rata MAE') }}</div>
            <div class="text-2xl font-bold">{{ $summaryStats['avg_mae'] ?? 0 }} <span class="text-sm font-normal">mm</span></div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __('Correction Uptime') }}</div>
            <div class="text-2xl font-bold">{{ $summaryStats['avg_correction_uptime'] ?? 0 }}<span class="text-sm font-normal">%</span></div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __('Quality Pass Rate') }}</div>
            <div class="text-2xl font-bold">{{ $summaryStats['quality_pass_rate'] ?? 0 }}<span class="text-sm font-normal">%</span></div>
        </div>
    </div>

    <!-- Performance Trend Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium mb-4">{{ __('Tren Performa') }}</h3>
        <div class="h-80">
            <canvas id="performance-trend-chart" wire:ignore></canvas>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Correction Analytics -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('Analitik Koreksi') }}</h3>
            <div class="h-80">
                <canvas id="correction-analytics-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Performance Distribution -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('Distribusi Performa MAE') }}</h3>
            <div class="h-80">
                <canvas id="performance-distribution-chart" wire:ignore></canvas>
            </div>
        </div>
    </div>

    <!-- Machine Comparison -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
        <h3 class="text-lg font-medium mb-4">{{ __('Perbandingan Mesin') }}</h3>
        <div class="h-80">
            <canvas id="machine-comparison-chart" wire:ignore></canvas>
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript