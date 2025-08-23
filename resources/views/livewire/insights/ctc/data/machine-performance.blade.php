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

    public array $machines = [];
    public array $machinePerformance = [];
    public array $performanceRanking = [];
    public array $availabilityData = [];
    public array $qualityTrends = [];
    public array $productivityTrends = [];
    public array $fleetStats = [];

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

        return $query->orderBy("created_at", "DESC");
    }

    #[On("update")]
    public function update()
    {
        $metrics = $this->getMetricsQuery()->get();

        $this->calculateMachinePerformance($metrics);
        $this->calculatePerformanceRanking();
        $this->calculateAvailabilityData($metrics);
        $this->calculateQualityTrends($metrics);
        $this->calculateProductivityTrends($metrics);
        $this->calculateFleetStats();

        $this->generatePerformanceRankingChart();
        $this->generateQualityTrendsChart();
        $this->generateProductivityChart();
        $this->generateAvailabilityChart();
        $this->generatePerformanceMatrixChart();
        $this->generateComparisonRadarChart();
    }

    private function calculateMachinePerformance($metrics)
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();
        $totalHours = $end->diffInHours($start);

        $this->machinePerformance = $metrics
            ->groupBy("ins_ctc_machine_id")
            ->map(function ($machineMetrics) use ($totalHours) {
                $machine = $machineMetrics->first()->ins_ctc_machine;
                $totalBatches = $machineMetrics->count();

                if ($totalBatches === 0) {
                    return [
                        "machine_id" => $machine->id,
                        "machine_line" => $machine->line,
                        "total_batches" => 0,
                        "utilization" => 0,
                        "throughput_rate" => 0,
                        "quality_score" => 0,
                        "availability" => 0,
                        "consistency_score" => 0,
                        "overall_performance" => 0,
                    ];
                }

                // Calculate quality metrics
                $avgGood = $machineMetrics->filter(fn ($m) => abs($m->t_balance) <= 1)->count();
                $maeGood = $machineMetrics->filter(fn ($m) => $m->t_mae <= 1.0)->count();
                $ssdGood = $machineMetrics->filter(fn ($m) => $m->t_ssd <= 1.0)->count();
                $correctionGood = $machineMetrics->filter(fn ($m) => $m->correction_uptime > 50)->count();

                $qualityScore = (($avgGood + $maeGood + $ssdGood + $correctionGood) / ($totalBatches * 4)) * 100;

                // Calculate utilization (active processing time)
                $activePeriods = $this->calculateActivePeriods($machineMetrics);
                $utilization = $totalHours > 0 ? ($activePeriods / $totalHours) * 100 : 0;

                // Calculate throughput
                $throughputRate = $totalHours > 0 ? $totalBatches / $totalHours : 0;

                // Calculate availability (online vs offline)
                $availability = $this->calculateAvailability($machine, $totalHours);

                // Calculate consistency score
                $consistencyScore = $this->calculateConsistencyScore($machineMetrics);

                // Overall performance score
                $overallPerformance = $qualityScore * 0.3 + $utilization * 0.25 + $availability * 0.25 + $consistencyScore * 0.2;

                return [
                    "machine_id" => $machine->id,
                    "machine_line" => $machine->line,
                    "total_batches" => $totalBatches,
                    "utilization" => round($utilization, 1),
                    "throughput_rate" => round($throughputRate, 2),
                    "quality_score" => round($qualityScore, 1),
                    "availability" => round($availability, 1),
                    "consistency_score" => round($consistencyScore, 1),
                    "overall_performance" => round($overallPerformance, 1),
                    "avg_mae" => round($machineMetrics->avg("t_mae") ?? 0, 2),
                    "avg_ssd" => round($machineMetrics->avg("t_ssd") ?? 0, 2),
                    "avg_balance" => round(abs($machineMetrics->avg("t_balance") ?? 0), 2),
                    "avg_correction_uptime" => round($machineMetrics->avg("correction_uptime") ?? 0, 1),
                ];
            })
            ->sortBy("machine_line")
            ->values()
            ->toArray();
    }

    private function calculatePerformanceRanking()
    {
        $this->performanceRanking = collect($this->machinePerformance)
            ->sortByDesc("overall_performance")
            ->values()
            ->map(function ($machine, $index) {
                $machine["rank"] = $index + 1;
                $machine["performance_category"] = $this->getPerformanceCategory($machine["overall_performance"]);
                return $machine;
            })
            ->toArray();
    }

    private function calculateAvailabilityData($metrics)
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $this->availabilityData = $metrics
            ->groupBy(function ($metric) {
                return Carbon::parse($metric->created_at)->format("Y-m-d");
            })
            ->map(function ($dayMetrics, $date) {
                $machineActivity = $dayMetrics
                    ->groupBy("ins_ctc_machine_id")
                    ->map(function ($machineMetrics) {
                        $machine = $machineMetrics->first()->ins_ctc_machine;
                        return [
                            "machine_line" => $machine->line,
                            "batch_count" => $machineMetrics->count(),
                            "first_activity" => $machineMetrics->min("created_at"),
                            "last_activity" => $machineMetrics->max("created_at"),
                            "is_active" => true,
                        ];
                    })
                    ->sortBy("machine_line")
                    ->values();

                return [
                    "date" => $date,
                    "machines" => $machineActivity->toArray(),
                    "active_machines" => $machineActivity->count(),
                    "total_batches" => $dayMetrics->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function calculateQualityTrends($metrics)
    {
        $this->qualityTrends = $metrics
            ->groupBy(function ($metric) {
                return Carbon::parse($metric->created_at)->format("Y-m-d");
            })
            ->map(function ($dayMetrics, $date) {
                return $dayMetrics
                    ->groupBy("ins_ctc_machine_id")
                    ->map(function ($machineMetrics) use ($date) {
                        $machine = $machineMetrics->first()->ins_ctc_machine;
                        $total = $machineMetrics->count();

                        $avgGood = $machineMetrics->filter(fn ($m) => abs($m->t_balance) <= 1)->count();
                        $maeGood = $machineMetrics->filter(fn ($m) => $m->t_mae <= 1.0)->count();
                        $ssdGood = $machineMetrics->filter(fn ($m) => $m->t_ssd <= 1.0)->count();
                        $correctionGood = $machineMetrics->filter(fn ($m) => $m->correction_uptime > 50)->count();

                        return [
                            "date" => $date,
                            "machine_line" => $machine->line,
                            "quality_score" => $total > 0 ? round((($avgGood + $maeGood + $ssdGood + $correctionGood) / ($total * 4)) * 100, 1) : 0,
                            "avg_mae" => round($machineMetrics->avg("t_mae") ?? 0, 2),
                            "avg_ssd" => round($machineMetrics->avg("t_ssd") ?? 0, 2),
                            "batch_count" => $total,
                        ];
                    })
                    ->sortBy("machine_line")
                    ->values();
            })
            ->values()
            ->flatten(1)
            ->toArray();
    }

    private function calculateProductivityTrends($metrics)
    {
        $this->productivityTrends = $metrics
            ->groupBy(function ($metric) {
                return Carbon::parse($metric->created_at)->format("Y-m-d");
            })
            ->map(function ($dayMetrics, $date) {
                return $dayMetrics
                    ->groupBy("ins_ctc_machine_id")
                    ->map(function ($machineMetrics) use ($date) {
                        $machine = $machineMetrics->first()->ins_ctc_machine;

                        return [
                            "date" => $date,
                            "machine_line" => $machine->line,
                            "batch_count" => $machineMetrics->count(),
                            "batches_per_hour" => round($machineMetrics->count() / 24, 2), // Simplified
                        ];
                    })
                    ->sortBy("machine_line")
                    ->values();
            })
            ->values()
            ->flatten(1)
            ->toArray();
    }

    private function calculateFleetStats()
    {
        if (empty($this->machinePerformance)) {
            $this->fleetStats = [
                "total_machines" => 0,
                "avg_utilization" => 0,
                "avg_quality_score" => 0,
                "avg_availability" => 0,
                "top_performer" => null,
                "improvement_needed" => 0,
            ];
            return;
        }

        $performances = collect($this->machinePerformance);

        $this->fleetStats = [
            "total_machines" => $performances->count(),
            "avg_utilization" => round($performances->avg("utilization"), 1),
            "avg_quality_score" => round($performances->avg("quality_score"), 1),
            "avg_availability" => round($performances->avg("availability"), 1),
            "avg_throughput" => round($performances->avg("throughput_rate"), 2),
            "top_performer" => $performances->sortByDesc("overall_performance")->first(),
            "improvement_needed" => $performances->where("overall_performance", "<", 70)->count(),
            "high_performers" => $performances->where("overall_performance", ">", 80)->count(),
        ];
    }

    private function calculateActivePeriods($metrics): float
    {
        // Simplified calculation - assume each batch represents active time
        $batchDurations = [];
        foreach ($metrics as $metric) {
            if ($metric->data && is_array($metric->data) && count($metric->data) >= 2) {
                $firstTimestamp = $metric->data[0][0] ?? null;
                $lastTimestamp = $metric->data[count($metric->data) - 1][0] ?? null;

                if ($firstTimestamp && $lastTimestamp) {
                    try {
                        $start = Carbon::parse($firstTimestamp);
                        $end = Carbon::parse($lastTimestamp);
                        $batchDurations[] = $end->diffInHours($start, true);
                    } catch (Exception $e) {
                        // Skip invalid timestamps
                    }
                }
            }
        }

        return array_sum($batchDurations);
    }

    private function calculateAvailability($machine, $totalHours): float
    {
        // Simplified availability calculation based on recent activity patterns
        $recentMetrics = $machine
            ->ins_ctc_metrics()
            ->whereBetween("created_at", [Carbon::parse($this->start_at), Carbon::parse($this->end_at)->endOfDay()])
            ->get();

        if ($recentMetrics->isEmpty()) {
            return 0;
        }

        // Calculate availability based on distribution of activities over time
        $activeDays = $recentMetrics
            ->groupBy(function ($metric) {
                return Carbon::parse($metric->created_at)->format("Y-m-d");
            })
            ->count();

        $totalDays = Carbon::parse($this->start_at)->diffInDays(Carbon::parse($this->end_at)) + 1;

        return ($activeDays / $totalDays) * 100;
    }

    private function calculateConsistencyScore($metrics): float
    {
        if ($metrics->count() < 2) {
            return 100;
        }

        // Calculate coefficient of variation for MAE (lower = more consistent)
        $maeValues = $metrics
            ->pluck("t_mae")
            ->filter()
            ->toArray();
        if (empty($maeValues)) {
            return 100;
        }

        $mean = array_sum($maeValues) / count($maeValues);
        $variance = array_sum(array_map(fn ($x) => pow($x - $mean, 2), $maeValues)) / count($maeValues);
        $stdDev = sqrt($variance);

        $cv = $mean > 0 ? ($stdDev / $mean) * 100 : 0;

        // Convert to consistency score (100 = perfectly consistent, 0 = highly variable)
        return max(0, 100 - $cv);
    }

    private function getPerformanceCategory($score): string
    {
        if ($score >= 80) {
            return "High";
        }
        if ($score >= 60) {
            return "Medium";
        }
        return "Low";
    }

    private function generatePerformanceRankingChart()
    {
        $chartData = [
            "labels" => array_map(function ($machine) {
                return "Line " . sprintf("%02d", $machine["machine_line"]);
            }, $this->performanceRanking),
            "datasets" => [
                [
                    "label" => "Overall Performance (%)",
                    "data" => array_column($this->performanceRanking, "overall_performance"),
                    "backgroundColor" => array_map(function ($machine) {
                        if ($machine["overall_performance"] >= 80) {
                            return "#10B981";
                        }
                        if ($machine["overall_performance"] >= 60) {
                            return "#F59E0B";
                        }
                        return "#EF4444";
                    }, $this->performanceRanking),
                    "borderWidth" => 1,
                ],
            ],
        ];

        $this->js(
            "
            const rankingCtx = document.getElementById('performance-ranking-chart');
            if (window.rankingChart) window.rankingChart.destroy();

            window.rankingChart = new Chart(rankingCtx, {
                type: 'bar',
                data: " .
                json_encode($chartData) .
                ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Performance Score (%)' }
                        }
                    }
                }
            });
        ",
        );
    }

    private function generateQualityTrendsChart()
    {
        if (empty($this->qualityTrends)) {
            return;
        }

        // Group by machine for multiple lines
        $machineGroups = collect($this->qualityTrends)->groupBy("machine_line");
        $dates = collect($this->qualityTrends)
            ->pluck("date")
            ->unique()
            ->sort()
            ->values();

        $datasets = $machineGroups
            ->map(function ($machineData, $line) use ($dates) {
                $dataPoints = $dates->map(function ($date) use ($machineData) {
                    $dayData = $machineData->where("date", $date)->first();
                    return $dayData ? $dayData["quality_score"] : null;
                });

                return [
                    "label" => "Line " . sprintf("%02d", $line),
                    "data" => $dataPoints->toArray(),
                    "borderColor" => $this->getLineColor($line),
                    "backgroundColor" => $this->getLineColor($line, 0.1),
                    "tension" => 0.4,
                    "fill" => false,
                ];
            })
            ->values()
            ->toArray();

        $chartData = [
            "labels" => $dates->toArray(),
            "datasets" => $datasets,
        ];

        $this->js(
            "
            const qualityCtx = document.getElementById('quality-trends-chart');
            if (window.qualityChart) window.qualityChart.destroy();

            window.qualityChart = new Chart(qualityCtx, {
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
                            title: { display: true, text: 'Quality Score (%)' }
                        }
                    }
                }
            });
        ",
        );
    }

    private function generateProductivityChart()
    {
        if (empty($this->productivityTrends)) {
            return;
        }

        $machineGroups = collect($this->productivityTrends)->groupBy("machine_line");
        $dates = collect($this->productivityTrends)
            ->pluck("date")
            ->unique()
            ->sort()
            ->values();

        $datasets = $machineGroups
            ->map(function ($machineData, $line) use ($dates) {
                $dataPoints = $dates->map(function ($date) use ($machineData) {
                    $dayData = $machineData->where("date", $date)->first();
                    return $dayData ? $dayData["batch_count"] : 0;
                });

                return [
                    "label" => "Line " . sprintf("%02d", $line),
                    "data" => $dataPoints->toArray(),
                    "backgroundColor" => $this->getLineColor($line, 0.7),
                    "borderColor" => $this->getLineColor($line),
                    "borderWidth" => 1,
                ];
            })
            ->values()
            ->toArray();

        $chartData = [
            "labels" => $dates->toArray(),
            "datasets" => $datasets,
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
                            beginAtZero: true,
                            title: { display: true, text: 'Daily Batch Count' }
                        }
                    }
                }
            });
        ",
        );
    }

    private function generateAvailabilityChart()
    {
        $chartData = [
            "labels" => array_column($this->availabilityData, "date"),
            "datasets" => [
                [
                    "label" => "Active Machines",
                    "data" => array_column($this->availabilityData, "active_machines"),
                    "backgroundColor" => "#10B981",
                    "borderColor" => "#059669",
                    "borderWidth" => 2,
                ],
                [
                    "label" => "Total Batches",
                    "data" => array_column($this->availabilityData, "total_batches"),
                    "backgroundColor" => "#3B82F6",
                    "borderColor" => "#2563EB",
                    "type" => "line",
                    "tension" => 0.4,
                    "yAxisID" => "y1",
                ],
            ],
        ];

        $this->js(
            "
            const availabilityCtx = document.getElementById('availability-chart');
            if (window.availabilityChart) window.availabilityChart.destroy();

            window.availabilityChart = new Chart(availabilityCtx, {
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
                            title: { display: true, text: 'Active Machines' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Total Batches' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        ",
        );
    }

    private function generatePerformanceMatrixChart()
    {
        if (empty($this->machinePerformance)) {
            return;
        }

        $chartData = [
            "labels" => array_map(function ($machine) {
                return "Line " . sprintf("%02d", $machine["machine_line"]);
            }, $this->machinePerformance),
            "datasets" => [
                [
                    "label" => "Quality Score",
                    "data" => array_column($this->machinePerformance, "quality_score"),
                    "backgroundColor" => "#8B5CF6",
                ],
                [
                    "label" => "Utilization",
                    "data" => array_column($this->machinePerformance, "utilization"),
                    "backgroundColor" => "#10B981",
                ],
                [
                    "label" => "Availability",
                    "data" => array_column($this->machinePerformance, "availability"),
                    "backgroundColor" => "#F59E0B",
                ],
                [
                    "label" => "Consistency",
                    "data" => array_column($this->machinePerformance, "consistency_score"),
                    "backgroundColor" => "#EF4444",
                ],
            ],
        ];

        $this->js(
            "
            const matrixCtx = document.getElementById('performance-matrix-chart');
            if (window.matrixChart) window.matrixChart.destroy();

            window.matrixChart = new Chart(matrixCtx, {
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
                            title: { display: true, text: 'Score (%)' }
                        }
                    }
                }
            });
        ",
        );
    }

    private function generateComparisonRadarChart()
    {
        if (empty($this->machinePerformance) || count($this->machinePerformance) < 2) {
            return;
        }

        // Take top 3 performers for comparison
        $topPerformers = array_slice($this->performanceRanking, 0, 3);

        $datasets = array_map(
            function ($machine, $index) {
                $colors = ["#10B981", "#3B82F6", "#F59E0B"];
                return [
                    "label" => "Line " . sprintf("%02d", $machine["machine_line"]),
                    "data" => [$machine["quality_score"], $machine["utilization"], $machine["availability"], $machine["consistency_score"]],
                    "borderColor" => $colors[$index] ?? "#8B5CF6",
                    "backgroundColor" => ($colors[$index] ?? "#8B5CF6") . "20",
                    "pointBackgroundColor" => $colors[$index] ?? "#8B5CF6",
                    "borderWidth" => 2,
                ];
            },
            $topPerformers,
            array_keys($topPerformers),
        );

        $chartData = [
            "labels" => ["Quality", "Utilization", "Availability", "Consistency"],
            "datasets" => $datasets,
        ];

        $this->js(
            "
            const radarCtx = document.getElementById('comparison-radar-chart');
            if (window.radarChart) window.radarChart.destroy();

            window.radarChart = new Chart(radarCtx, {
                type: 'radar',
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
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { stepSize: 20 }
                        }
                    }
                }
            });
        ",
        );
    }

    private function getLineColor($line, $opacity = 1): string
    {
        $colors = ["#10B981", "#3B82F6", "#F59E0B", "#EF4444", "#8B5CF6", "#06B6D4", "#84CC16", "#F97316", "#EC4899", "#6366F1"];

        $color = $colors[($line - 1) % count($colors)];

        if ($opacity < 1) {
            // Convert hex to rgba
            $hex = ltrim($color, "#");
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return "rgba($r, $g, $b, $opacity)";
        }

        return $color;
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
            <div class="grid grid-cols-1 lg:flex gap-3">
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
                    <x-select wire:model.live="machine_id" class="w-full lg:w-20">
                        <option value="">{{ __("Semua") }}</option>
                        @foreach ($machines as $id => $line)
                            <option value="{{ $id }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
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

    <!-- Fleet Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Total Mesin") }}</div>
            <div class="text-2xl font-bold">{{ $fleetStats["total_machines"] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Rerata Utilisasi") }}</div>
            <div class="text-2xl font-bold text-blue-600">
                {{ $fleetStats["avg_utilization"] ?? 0 }}
                <span class="text-sm font-normal">%</span>
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Rerata Kualitas") }}</div>
            <div class="text-2xl font-bold text-green-600">
                {{ $fleetStats["avg_quality_score"] ?? 0 }}
                <span class="text-sm font-normal">%</span>
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Rerata Ketersediaan") }}</div>
            <div class="text-2xl font-bold text-purple-600">
                {{ $fleetStats["avg_availability"] ?? 0 }}
                <span class="text-sm font-normal">%</span>
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("High Performers") }}</div>
            <div class="text-2xl font-bold text-teal-600">{{ $fleetStats["high_performers"] ?? 0 }}</div>
            <div class="text-xs text-neutral-500">{{ __("> 80% performance") }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Perlu perbaikan") }}</div>
            <div class="text-2xl font-bold text-orange-600">{{ $fleetStats["improvement_needed"] ?? 0 }}</div>
            <div class="text-xs text-neutral-500">{{ __("< 70% performance") }}</div>
        </div>
    </div>

    <!-- Performance Ranking Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium mb-4">{{ __("Peringkat Kinerja Mesin") }}</h3>
        <div class="h-80">
            <canvas id="performance-ranking-chart" wire:ignore></canvas>
        </div>
    </div>

    <!-- Charts Grid Row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Quality Trends -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Tren Kualitas per Mesin") }}</h3>
            <div class="h-80">
                <canvas id="quality-trends-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Productivity Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Produktivitas Harian per Mesin") }}</h3>
            <div class="h-80">
                <canvas id="productivity-chart" wire:ignore></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Grid Row 2 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Availability Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Ketersediaan Mesin") }}</h3>
            <div class="h-80">
                <canvas id="availability-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Performance Matrix -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Matriks Performa Mesin") }}</h3>
            <div class="h-80">
                <canvas id="performance-matrix-chart" wire:ignore></canvas>
            </div>
        </div>
    </div>

    <!-- Top Performers Comparison -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium mb-4">{{ __("Perbandingan Top Performers") }}</h3>
        <div class="h-80">
            <canvas id="comparison-radar-chart" wire:ignore></canvas>
        </div>
    </div>

    <!-- Detailed Performance Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Performance Ranking Table -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Detail Peringkat Kinerja") }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Rank") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Line") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Performance") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Category") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Batches") }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($performanceRanking as $machine)
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ $machine["rank"] }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    {{ __("Line") }} {{ sprintf("%02d", $machine["machine_line"]) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $machine["overall_performance"] > 80 ? "bg-green-100 text-green-800" : ($machine["overall_performance"] > 60 ? "bg-yellow-100 text-yellow-800" : "bg-red-100 text-red-800") }}"
                                    >
                                        {{ $machine["overall_performance"] }}%
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $machine["performance_category"] === "High" ? "bg-green-100 text-green-800" : ($machine["performance_category"] === "Medium" ? "bg-yellow-100 text-yellow-800" : "bg-red-100 text-red-800") }}"
                                    >
                                        {{ $machine["performance_category"] }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    {{ $machine["total_batches"] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detailed Metrics Table -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __("Metrik Detail Mesin") }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Line") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Utilization") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Quality") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Availability") }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">{{ __("Throughput") }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($machinePerformance as $machine)
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ __("Line") }} {{ sprintf("%02d", $machine["machine_line"]) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">{{ $machine["utilization"] }}%</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">{{ $machine["quality_score"] }}%</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">{{ $machine["availability"] }}%</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-300">
                                    {{ $machine["throughput_rate"] }} {{ __("batch/jam") }}
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
