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

new #[Layout("layouts.app")] class extends Component {
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public $machine_id;

    #[Url]
    public string $recipe_name = "";

    public array $machines = [];
    public array $evaluationStats = [];
    public array $productivityStats = [];
    public array $dailyStats = [];
    public array $shiftStats = [];
    public array $machineComparison = [];

    public function mount()
    {
        if (! $this->start_at || ! $this->end_at) {
            $this->setThisWeek();
        }

        $this->machines = InsCtcMachine::orderBy("line")
            ->get()
            ->pluck("line", "id")
            ->toArray();
    }

    private function getMetricsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsCtcMetric::with(["ins_ctc_machine", "ins_ctc_recipe", "ins_rubber_batch"])->whereBetween("created_at", [$start, $end]);

        if ($this->machine_id) {
            $query->where("ins_ctc_machine_id", $this->machine_id);
        }

        if ($this->recipe_name) {
            $query->whereHas("ins_ctc_recipe", function ($q) {
                $q->where("name", "like", "%" . $this->recipe_name . "%");
            });
        }

        return $query->orderBy("created_at", "DESC");
    }

    #[On("update")]
    public function update()
    {
        $metrics = $this->getMetricsQuery()->get();

        $this->calculateEvaluationStats($metrics);
        $this->calculateProductivityStats($metrics);
        $this->calculateDailyStats($metrics);
        $this->calculateShiftStats($metrics);
        $this->calculateMachineComparison($metrics);

        $this->generateEvaluationDistributionCharts();
        $this->generateProductivityChart();
        $this->generatePerformanceTrendChart();
        $this->generateMachineComparisonChart();
        $this->generateShiftAnalysisChart();
    }

    private function calculateEvaluationStats($metrics)
    {
        $totalBatches = $metrics->count();

        if ($totalBatches === 0) {
            $this->evaluationStats = [
                "total_batches" => 0,
                "avg_good" => 0,
                "mae_good" => 0,
                "ssd_good" => 0,
                "correction_good" => 0,
                "overall_performance" => 0,
                "target_achievement" => 0,
            ];
            return;
        }

        // Calculate evaluation percentages
        $avgGood = $metrics
            ->filter(function ($metric) {
                return abs($metric->t_balance) <= 1; // seimbang
            })
            ->count();

        $maeGood = $metrics
            ->filter(function ($metric) {
                return $metric->t_mae <= 1.0; // sesuai standar
            })
            ->count();

        $ssdGood = $metrics
            ->filter(function ($metric) {
                return $metric->t_ssd <= 1.0; // konsisten
            })
            ->count();

        $correctionGood = $metrics
            ->filter(function ($metric) {
                return $metric->correction_uptime > 50; // auto
            })
            ->count();

        // Calculate target achievement (all evaluations good)
        $targetAchievement = $metrics
            ->filter(function ($metric) {
                return abs($metric->t_balance) <= 1 && $metric->t_mae <= 1.0 && $metric->t_ssd <= 1.0 && $metric->correction_uptime > 50;
            })
            ->count();

        $this->evaluationStats = [
            "total_batches" => $totalBatches,
            "avg_good" => round(($avgGood / $totalBatches) * 100, 1),
            "avg_good_count" => $avgGood,
            "mae_good" => round(($maeGood / $totalBatches) * 100, 1),
            "mae_good_count" => $maeGood,
            "ssd_good" => round(($ssdGood / $totalBatches) * 100, 1),
            "ssd_good_count" => $ssdGood,
            "correction_good" => round(($correctionGood / $totalBatches) * 100, 1),
            "correction_good_count" => $correctionGood,
            "overall_performance" => round((($avgGood + $maeGood + $ssdGood + $correctionGood) / ($totalBatches * 4)) * 100, 1),
            "target_achievement" => round(($targetAchievement / $totalBatches) * 100, 1),
            "target_achievement_count" => $targetAchievement,
        ];
    }

    private function calculateProductivityStats($metrics)
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();
        $totalHours = $end->diffInHours($start);

        $totalBatches = $metrics->count();

        $this->productivityStats = [
            "total_batches" => $totalBatches,
            "batches_per_hour" => $totalHours > 0 ? round($totalBatches / $totalHours, 2) : 0,
            "daily_average" => round($totalBatches / max(1, $end->diffInDays($start)), 1),
            "peak_hour_production" => $this->calculatePeakHourProduction($metrics),
            "production_consistency" => $this->calculateProductionConsistency($metrics),
        ];
    }

    private function calculateDailyStats($metrics)
    {
        $this->dailyStats = $metrics
            ->groupBy(function ($metric) {
                return Carbon::parse($metric->created_at)->format("Y-m-d");
            })
            ->map(function ($dayMetrics) {
                $total = $dayMetrics->count();

                if ($total === 0) {
                    return [
                        "date" => "",
                        "batch_count" => 0,
                        "batches_per_hour" => 0,
                        "avg_good" => 0,
                        "mae_good" => 0,
                        "ssd_good" => 0,
                        "correction_good" => 0,
                        "overall_performance" => 0,
                    ];
                }

                $date = Carbon::parse($dayMetrics->first()->created_at)->format("Y-m-d");
                $avgGood = $dayMetrics->filter(fn ($m) => abs($m->t_balance) <= 1)->count();
                $maeGood = $dayMetrics->filter(fn ($m) => $m->t_mae <= 1.0)->count();
                $ssdGood = $dayMetrics->filter(fn ($m) => $m->t_ssd <= 1.0)->count();
                $correctionGood = $dayMetrics->filter(fn ($m) => $m->correction_uptime > 50)->count();

                return [
                    "date" => $date,
                    "batch_count" => $total,
                    "batches_per_hour" => round($total / 24, 2), // Assuming 24-hour operation
                    "avg_good" => round(($avgGood / $total) * 100, 1),
                    "mae_good" => round(($maeGood / $total) * 100, 1),
                    "ssd_good" => round(($ssdGood / $total) * 100, 1),
                    "correction_good" => round(($correctionGood / $total) * 100, 1),
                    "overall_performance" => round((($avgGood + $maeGood + $ssdGood + $correctionGood) / ($total * 4)) * 100, 1),
                ];
            })
            ->values()
            ->toArray();
    }

    private function calculateShiftStats($metrics)
    {
        $this->shiftStats = $metrics
            ->groupBy(function ($metric) {
                $hour = Carbon::parse($metric->created_at)->hour;
                if ($hour >= 6 && $hour < 14) {
                    return 1;
                } // Shift 1: 06:00-14:00
                if ($hour >= 14 && $hour < 22) {
                    return 2;
                } // Shift 2: 14:00-22:00
                return 3; // Shift 3: 22:00-06:00
            })
            ->map(function ($shiftMetrics, $shift) {
                $total = $shiftMetrics->count();

                if ($total === 0) {
                    return [
                        "shift" => $shift,
                        "batch_count" => 0,
                        "batches_per_hour" => 0,
                        "avg_good" => 0,
                        "mae_good" => 0,
                        "ssd_good" => 0,
                        "correction_good" => 0,
                        "overall_performance" => 0,
                    ];
                }

                $avgGood = $shiftMetrics->filter(fn ($m) => abs($m->t_balance) <= 1)->count();
                $maeGood = $shiftMetrics->filter(fn ($m) => $m->t_mae <= 1.0)->count();
                $ssdGood = $shiftMetrics->filter(fn ($m) => $m->t_ssd <= 1.0)->count();
                $correctionGood = $shiftMetrics->filter(fn ($m) => $m->correction_uptime > 50)->count();

                return [
                    "shift" => $shift,
                    "batch_count" => $total,
                    "batches_per_hour" => round($total / 8, 2), // 8-hour shifts
                    "avg_good" => round(($avgGood / $total) * 100, 1),
                    "mae_good" => round(($maeGood / $total) * 100, 1),
                    "ssd_good" => round(($ssdGood / $total) * 100, 1),
                    "correction_good" => round(($correctionGood / $total) * 100, 1),
                    "overall_performance" => round((($avgGood + $maeGood + $ssdGood + $correctionGood) / ($total * 4)) * 100, 1),
                ];
            })
            ->sortBy("shift")
            ->values()
            ->toArray();
    }

    private function calculateMachineComparison($metrics)
    {
        $this->machineComparison = $metrics
            ->groupBy("ins_ctc_machine_id")
            ->map(function ($machineMetrics) {
                $machine = $machineMetrics->first()->ins_ctc_machine;
                $total = $machineMetrics->count();

                if ($total === 0) {
                    return [
                        "machine_line" => $machine->line ?? "N/A",
                        "batch_count" => 0,
                        "batches_per_hour" => 0,
                        "overall_performance" => 0,
                    ];
                }

                $avgGood = $machineMetrics->filter(fn ($m) => abs($m->t_balance) <= 1)->count();
                $maeGood = $machineMetrics->filter(fn ($m) => $m->t_mae <= 1.0)->count();
                $ssdGood = $machineMetrics->filter(fn ($m) => $m->t_ssd <= 1.0)->count();
                $correctionGood = $machineMetrics->filter(fn ($m) => $m->correction_uptime > 50)->count();

                return [
                    "machine_line" => $machine->line ?? "N/A",
                    "batch_count" => $total,
                    "batches_per_hour" => round($total / 24, 2), // Simplified calculation
                    "avg_good" => round(($avgGood / $total) * 100, 1),
                    "mae_good" => round(($maeGood / $total) * 100, 1),
                    "ssd_good" => round(($ssdGood / $total) * 100, 1),
                    "correction_good" => round(($correctionGood / $total) * 100, 1),
                    "overall_performance" => round((($avgGood + $maeGood + $ssdGood + $correctionGood) / ($total * 4)) * 100, 1),
                ];
            })
            ->sortBy("machine_line")
            ->values()
            ->toArray();
    }

    private function calculatePeakHourProduction($metrics): float
    {
        $hourlyProduction = $metrics
            ->groupBy(function ($metric) {
                return Carbon::parse($metric->created_at)->format("Y-m-d H");
            })
            ->map->count();

        return $hourlyProduction->max() ?? 0;
    }

    private function calculateProductionConsistency($metrics): float
    {
        $dailyProduction = $metrics
            ->groupBy(function ($metric) {
                return Carbon::parse($metric->created_at)->format("Y-m-d");
            })
            ->map->count();

        if ($dailyProduction->count() <= 1) {
            return 100;
        }

        $mean = $dailyProduction->avg();
        $variance = $dailyProduction->map(fn ($count) => pow($count - $mean, 2))->avg();
        $stdDev = sqrt($variance);

        return $mean > 0 ? round(max(0, 100 - ($stdDev / $mean) * 100), 1) : 0;
    }

    private function generateEvaluationDistributionCharts()
    {
        // AVG Evaluation Chart
        $avgData = [
            "labels" => ["Seimbang", "Jomplang"],
            "datasets" => [
                [
                    "data" => [$this->evaluationStats["avg_good_count"] ?? 0, ($this->evaluationStats["total_batches"] ?? 0) - ($this->evaluationStats["avg_good_count"] ?? 0)],
                    "backgroundColor" => ["#10B981", "#EF4444"],
                ],
            ],
        ];

        $this->js(
            "
            const avgCtx = document.getElementById('avg-evaluation-chart');
            if (window.avgChart) window.avgChart.destroy();

            window.avgChart = new Chart(avgCtx, {
                type: 'doughnut',
                data: " .
                json_encode($avgData) .
                ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        ",
        );

        // MAE Evaluation Chart
        $maeData = [
            "labels" => ["Sesuai Standar", "Di Luar Standar"],
            "datasets" => [
                [
                    "data" => [$this->evaluationStats["mae_good_count"] ?? 0, ($this->evaluationStats["total_batches"] ?? 0) - ($this->evaluationStats["mae_good_count"] ?? 0)],
                    "backgroundColor" => ["#10B981", "#EF4444"],
                ],
            ],
        ];

        $this->js(
            "
            const maeCtx = document.getElementById('mae-evaluation-chart');
            if (window.maeChart) window.maeChart.destroy();

            window.maeChart = new Chart(maeCtx, {
                type: 'doughnut',
                data: " .
                json_encode($maeData) .
                ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        ",
        );

        // SSD Evaluation Chart
        $ssdData = [
            "labels" => ["Konsisten", "Fluktuatif"],
            "datasets" => [
                [
                    "data" => [$this->evaluationStats["ssd_good_count"] ?? 0, ($this->evaluationStats["total_batches"] ?? 0) - ($this->evaluationStats["ssd_good_count"] ?? 0)],
                    "backgroundColor" => ["#10B981", "#EF4444"],
                ],
            ],
        ];

        $this->js(
            "
            const ssdCtx = document.getElementById('ssd-evaluation-chart');
            if (window.ssdChart) window.ssdChart.destroy();

            window.ssdChart = new Chart(ssdCtx, {
                type: 'doughnut',
                data: " .
                json_encode($ssdData) .
                ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        ",
        );

        // Correction Evaluation Chart
        $correctionData = [
            "labels" => ["Auto", "Manual"],
            "datasets" => [
                [
                    "data" => [
                        $this->evaluationStats["correction_good_count"] ?? 0,
                        ($this->evaluationStats["total_batches"] ?? 0) - ($this->evaluationStats["correction_good_count"] ?? 0),
                    ],
                    "backgroundColor" => ["#10B981", "#EF4444"],
                ],
            ],
        ];

        $this->js(
            "
            const correctionCtx = document.getElementById('correction-evaluation-chart');
            if (window.correctionEvalChart) window.correctionEvalChart.destroy();

            window.correctionEvalChart = new Chart(correctionCtx, {
                type: 'doughnut',
                data: " .
                json_encode($correctionData) .
                ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        ",
        );
    }

    private function generateProductivityChart()
    {
        $chartData = [
            "labels" => array_column($this->dailyStats, "date"),
            "datasets" => [
                [
                    "label" => "Batch Count",
                    "data" => array_column($this->dailyStats, "batch_count"),
                    "backgroundColor" => "#3B82F6",
                    "borderColor" => "#2563EB",
                    "borderWidth" => 1,
                    "yAxisID" => "y",
                ],
                [
                    "label" => "Batches per Hour",
                    "data" => array_column($this->dailyStats, "batches_per_hour"),
                    "borderColor" => "#10B981",
                    "backgroundColor" => "rgba(16, 185, 129, 0.1)",
                    "type" => "line",
                    "tension" => 0.4,
                    "yAxisID" => "y1",
                ],
            ],
        ];

        $this->js(
            "
            const productivityCtx = document.getElementById('productivity-chart');
            if (window.productivityChart) window.productivityChart.destroy();

            window.productivityChart = new Chart(productivityCtx, {
                type: 'bar',
                data: " .
                json_encode($chartData) .
                ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Batch Count' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Batches per Hour' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        ",
        );
    }

    private function generatePerformanceTrendChart()
    {
        $chartData = [
            "labels" => array_column($this->dailyStats, "date"),
            "datasets" => [
                [
                    "label" => "AVG (Seimbang %)",
                    "data" => array_column($this->dailyStats, "avg_good"),
                    "borderColor" => "#8B5CF6",
                    "backgroundColor" => "rgba(139, 92, 246, 0.1)",
                    "tension" => 0.4,
                ],
                [
                    "label" => "MAE (Sesuai Standar %)",
                    "data" => array_column($this->dailyStats, "mae_good"),
                    "borderColor" => "#10B981",
                    "backgroundColor" => "rgba(16, 185, 129, 0.1)",
                    "tension" => 0.4,
                ],
                [
                    "label" => "SSD (Konsisten %)",
                    "data" => array_column($this->dailyStats, "ssd_good"),
                    "borderColor" => "#F59E0B",
                    "backgroundColor" => "rgba(245, 158, 11, 0.1)",
                    "tension" => 0.4,
                ],
                [
                    "label" => "Correction (Auto %)",
                    "data" => array_column($this->dailyStats, "correction_good"),
                    "borderColor" => "#EF4444",
                    "backgroundColor" => "rgba(239, 68, 68, 0.1)",
                    "tension" => 0.4,
                ],
            ],
        ];

        $this->js(
            "
            const trendCtx = document.getElementById('performance-trend-chart');
            if (window.performanceTrendChart) window.performanceTrendChart.destroy();

            window.performanceTrendChart = new Chart(trendCtx, {
                type: 'line',
                data: " .
                json_encode($chartData) .
                ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Percentage (%)' }
                        }
                    }
                }
            });
        ",
        );
    }

    private function generateMachineComparisonChart()
    {
        if (empty($this->machineComparison)) {
            return;
        }

        $chartData = [
            "labels" => array_map(function ($machine) {
                return "Line " . sprintf("%02d", $machine["machine_line"]);
            }, $this->machineComparison),
            "datasets" => [
                [
                    "label" => "Overall Performance (%)",
                    "data" => array_column($this->machineComparison, "overall_performance"),
                    "backgroundColor" => "#3B82F6",
                ],
                [
                    "label" => "Batches per Hour",
                    "data" => array_column($this->machineComparison, "batches_per_hour"),
                    "backgroundColor" => "#10B981",
                    "yAxisID" => "y1",
                ],
            ],
        ];

        $this->js(
            "
            const machineCtx = document.getElementById('machine-comparison-chart');
            if (window.machineChart) window.machineChart.destroy();

            window.machineChart = new Chart(machineCtx, {
                type: 'bar',
                data: " .
                json_encode($chartData) .
                ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Performance (%)' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Batches per Hour' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        ",
        );
    }

    private function generateShiftAnalysisChart()
    {
        $chartData = [
            "labels" => array_map(function ($shift) {
                return "Shift " . $shift["shift"];
            }, $this->shiftStats),
            "datasets" => [
                [
                    "label" => "Batch Count",
                    "data" => array_column($this->shiftStats, "batch_count"),
                    "backgroundColor" => "#3B82F6",
                ],
                [
                    "label" => "Overall Performance (%)",
                    "data" => array_column($this->shiftStats, "overall_performance"),
                    "backgroundColor" => "#10B981",
                    "yAxisID" => "y1",
                ],
            ],
        ];

        $this->js(
            "
            const shiftCtx = document.getElementById('shift-analysis-chart');
            if (window.shiftChart) window.shiftChart.destroy();

            window.shiftChart = new Chart(shiftCtx, {
                type: 'bar',
                data: " .
                json_encode($chartData) .
                ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Batch Count' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Performance (%)' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        ",
        );
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
                                <x-text-button class="uppercase ml-3">
                                    {{ __("Rentang") }}
                                    <i class="icon-chevron-down ms-1"></i>
                                </x-text-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setToday">
                                    {{ __("Hari ini") }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                    {{ __("Kemarin") }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                    {{ __("Minggu ini") }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                    {{ __("Minggu lalu") }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                    {{ __("Bulan ini") }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                    {{ __("Bulan lalu") }}
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
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
                    <x-select wire:model.live="machine_id" class="w-full lg:w-20">
                        <option value=""></option>
                        @foreach ($machines as $id => $line)
                            <option value="{{ $id }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Resep") }}</label>
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
                        {{ __("Memuat...") }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Batch total") }}</div>
            <div class="text-2xl font-bold">{{ number_format($evaluationStats["total_batches"] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Batch/jam") }}</div>
            <div class="text-2xl font-bold text-blue-600">{{ $productivityStats["batches_per_hour"] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Performa keseluruhan") }}</div>
            <div class="text-2xl font-bold text-green-600">
                {{ $evaluationStats["overall_performance"] ?? 0 }}
                <span class="text-sm font-normal">%</span>
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Capaian target") }}</div>
            <div class="text-2xl font-bold text-purple-600">
                {{ $evaluationStats["target_achievement"] ?? 0 }}
                <span class="text-sm font-normal">%</span>
            </div>
            <div class="text-xs text-neutral-500">
                {{ $evaluationStats["target_achievement_count"] ?? 0 }}/{{ $evaluationStats["total_batches"] ?? 0 }} {{ __("batch") }}
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Puncak produksi") }}</div>
            <div class="text-2xl font-bold text-orange-600">{{ $productivityStats["peak_hour_production"] ?? 0 }}</div>
            <div class="text-xs text-neutral-500">{{ __("batch/jam") }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Konsistensi") }}</div>
            <div class="text-2xl font-bold text-teal-600">
                {{ $productivityStats["production_consistency"] ?? 0 }}
                <span class="text-sm font-normal">%</span>
            </div>
        </div>
    </div>

    <!-- Evaluation Distribution Charts -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("AVG Evaluation (Balance)") }}</h3>
            <div class="h-64">
                <canvas id="avg-evaluation-chart" wire:ignore></canvas>
            </div>
            <div class="mt-4 text-center">
                <div class="text-lg font-bold text-green-600">{{ $evaluationStats["avg_good"] ?? 0 }}%</div>
                <div class="text-sm text-neutral-500">{{ __("Seimbang") }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("MAE Evaluation (Accuracy)") }}</h3>
            <div class="h-64">
                <canvas id="mae-evaluation-chart" wire:ignore></canvas>
            </div>
            <div class="mt-4 text-center">
                <div class="text-lg font-bold text-green-600">{{ $evaluationStats["mae_good"] ?? 0 }}%</div>
                <div class="text-sm text-neutral-500">{{ __("Sesuai Standar") }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Evaluasi SSD (Konsistensi)") }}</h3>
            <div class="h-64">
                <canvas id="ssd-evaluation-chart" wire:ignore></canvas>
            </div>
            <div class="mt-4 text-center">
                <div class="text-lg font-bold text-green-600">{{ $evaluationStats["ssd_good"] ?? 0 }}%</div>
                <div class="text-sm text-neutral-500">{{ __("Konsisten") }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Evaluasi koreksi") }}</h3>
            <div class="h-64">
                <canvas id="correction-evaluation-chart" wire:ignore></canvas>
            </div>
            <div class="mt-4 text-center">
                <div class="text-lg font-bold text-green-600">{{ $evaluationStats["correction_good"] ?? 0 }}%</div>
                <div class="text-sm text-neutral-500">{{ __("Auto") }}</div>
            </div>
        </div>
    </div>

    <!-- Productivity Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium mb-4">{{ __("Tren Produktivitas") }}</h3>
        <div class="h-80">
            <canvas id="productivity-chart" wire:ignore></canvas>
        </div>
    </div>

    <!-- Performance Trend Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium mb-4">{{ __("Tren Performa Evaluasi") }}</h3>
        <div class="h-80">
            <canvas id="performance-trend-chart" wire:ignore></canvas>
        </div>
    </div>

    <!-- Machine and Shift Comparison -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Machine Comparison -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Perbandingan Performa Mesin") }}</h3>
            <div class="h-80">
                <canvas id="machine-comparison-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Shift Analysis -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Analisis per Shift") }}</h3>
            <div class="h-80">
                <canvas id="shift-analysis-chart" wire:ignore></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Statistics Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Machine Statistics -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Statistik Detail Mesin") }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Line") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Batch") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Batch/Jam") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Performance") }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($machineComparison as $machine)
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ __("Line") }} {{ sprintf("%02d", $machine["machine_line"]) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    {{ $machine["batch_count"] }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    {{ $machine["batches_per_hour"] }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $machine["overall_performance"] > 80 ? "bg-green-100 text-green-800" : ($machine["overall_performance"] > 60 ? "bg-yellow-100 text-yellow-800" : "bg-red-100 text-red-800") }}"
                                    >
                                        {{ $machine["overall_performance"] }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Shift Statistics -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Statistik Detail Shift") }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Shift") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Batch") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Batch/Jam") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Performance") }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($shiftStats as $shift)
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __("Shift") }} {{ $shift["shift"] }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    {{ $shift["batch_count"] }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    {{ $shift["batches_per_hour"] }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $shift["overall_performance"] > 80 ? "bg-green-100 text-green-800" : ($shift["overall_performance"] > 60 ? "bg-yellow-100 text-yellow-800" : "bg-red-100 text-red-800") }}"
                                    >
                                        {{ $shift["overall_performance"] }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@script
    <script>
        $wire.$dispatch('update');
    </script>
@endscript
