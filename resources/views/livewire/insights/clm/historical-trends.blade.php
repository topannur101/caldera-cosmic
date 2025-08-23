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
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public string $location = "";

    #[Url]
    public string $comparison_period = "week"; // week, month

    public array $locations = [];
    public array $trendMetrics = [];
    public array $dailyAverages = [];
    public array $weeklyComparisons = [];
    public array $monthlyComparisons = [];
    public int $progress = 0;

    public function mount()
    {
        if (! $this->start_at || ! $this->end_at) {
            $this->setThisMonth(); // Default to monthly view for trends
        }

        // Get available locations (currently only 'ip')
        $this->locations = InsClmRecord::distinct()
            ->whereNotNull("location")
            ->pluck("location")
            ->toArray();
    }

    #[On("update")]
    public function updated()
    {
        $this->progress = 0;
        $this->stream(to: "progress", content: $this->progress, replace: true);

        // Phase 1: Fetch data (0-49%)
        $this->progress = 10;
        $this->stream(to: "progress", content: $this->progress, replace: true);

        $records = $this->getRecordsData();

        $this->progress = 49;
        $this->stream(to: "progress", content: $this->progress, replace: true);

        // Phase 2: Calculate trends and comparisons (49-98%)
        $this->progress = 60;
        $this->stream(to: "progress", content: $this->progress, replace: true);

        $this->calculateTrendMetrics($records);
        $this->calculateComparisons($records);

        $this->progress = 98;
        $this->stream(to: "progress", content: $this->progress, replace: true);

        // Phase 3: Render charts (98-100%)
        $this->renderCharts();

        $this->progress = 100;
        $this->stream(to: "progress", content: $this->progress, replace: true);
    }

    private function getRecordsData()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsClmRecord::whereBetween("created_at", [$start, $end]);

        if ($this->location) {
            $query->where("location", $this->location);
        }

        return $query->orderBy("created_at")->get();
    }

    private function calculateTrendMetrics($records)
    {
        if ($records->isEmpty()) {
            $this->trendMetrics = [
                "temperature_trend" => "stable",
                "humidity_trend" => "stable",
                "temperature_change" => 0,
                "humidity_change" => 0,
                "volatility_temperature" => 0,
                "volatility_humidity" => 0,
            ];
            $this->dailyAverages = [];
            return;
        }

        // Calculate daily averages
        $dailyData = $records->groupBy(function ($record) {
            return Carbon::parse($record->created_at)->format("Y-m-d");
        });

        $this->dailyAverages = [];
        foreach ($dailyData as $date => $dayRecords) {
            $this->dailyAverages[] = [
                "date" => $date,
                "temperature_avg" => round($dayRecords->avg("temperature"), 1),
                "humidity_avg" => round($dayRecords->avg("humidity"), 1),
                "temperature_min" => $dayRecords->min("temperature"),
                "temperature_max" => $dayRecords->max("temperature"),
                "humidity_min" => $dayRecords->min("humidity"),
                "humidity_max" => $dayRecords->max("humidity"),
                "count" => $dayRecords->count(),
            ];
        }

        // Calculate trend direction and magnitude
        if (count($this->dailyAverages) >= 2) {
            $firstHalf = array_slice($this->dailyAverages, 0, ceil(count($this->dailyAverages) / 2));
            $secondHalf = array_slice($this->dailyAverages, floor(count($this->dailyAverages) / 2));

            $firstHalfTempAvg = collect($firstHalf)->avg("temperature_avg");
            $secondHalfTempAvg = collect($secondHalf)->avg("temperature_avg");
            $tempChange = $secondHalfTempAvg - $firstHalfTempAvg;

            $firstHalfHumidityAvg = collect($firstHalf)->avg("humidity_avg");
            $secondHalfHumidityAvg = collect($secondHalf)->avg("humidity_avg");
            $humidityChange = $secondHalfHumidityAvg - $firstHalfHumidityAvg;

            // Calculate volatility (coefficient of variation)
            $tempAvgs = collect($this->dailyAverages)->pluck("temperature_avg");
            $humidityAvgs = collect($this->dailyAverages)->pluck("humidity_avg");

            $tempVolatility = $tempAvgs->avg() > 0 ? ($this->calculateStandardDeviation($tempAvgs->toArray()) / $tempAvgs->avg()) * 100 : 0;
            $humidityVolatility = $humidityAvgs->avg() > 0 ? ($this->calculateStandardDeviation($humidityAvgs->toArray()) / $humidityAvgs->avg()) * 100 : 0;

            $this->trendMetrics = [
                "temperature_trend" => $this->determineTrend($tempChange),
                "humidity_trend" => $this->determineTrend($humidityChange),
                "temperature_change" => round($tempChange, 2),
                "humidity_change" => round($humidityChange, 2),
                "volatility_temperature" => round($tempVolatility, 2),
                "volatility_humidity" => round($humidityVolatility, 2),
            ];
        }
    }

    private function calculateComparisons($records)
    {
        if ($this->comparison_period === "week") {
            $this->calculateWeeklyComparisons($records);
        } else {
            $this->calculateMonthlyComparisons($records);
        }
    }

    private function calculateWeeklyComparisons($records)
    {
        $weeklyData = $records->groupBy(function ($record) {
            $date = Carbon::parse($record->created_at);
            return $date->year . "-W" . sprintf("%02d", $date->week);
        });

        $this->weeklyComparisons = [];
        foreach ($weeklyData as $week => $weekRecords) {
            $this->weeklyComparisons[] = [
                "period" => $week,
                "period_label" => "Week " . explode("-W", $week)[1] . ", " . explode("-W", $week)[0],
                "temperature_avg" => round($weekRecords->avg("temperature"), 1),
                "humidity_avg" => round($weekRecords->avg("humidity"), 1),
                "temperature_min" => $weekRecords->min("temperature"),
                "temperature_max" => $weekRecords->max("temperature"),
                "humidity_min" => $weekRecords->min("humidity"),
                "humidity_max" => $weekRecords->max("humidity"),
                "count" => $weekRecords->count(),
            ];
        }

        // Sort by period
        usort($this->weeklyComparisons, function ($a, $b) {
            return $a["period"] <=> $b["period"];
        });
    }

    private function calculateMonthlyComparisons($records)
    {
        $monthlyData = $records->groupBy(function ($record) {
            return Carbon::parse($record->created_at)->format("Y-m");
        });

        $this->monthlyComparisons = [];
        foreach ($monthlyData as $month => $monthRecords) {
            $monthName = Carbon::parse($month . "-01")->format("F Y");
            $this->monthlyComparisons[] = [
                "period" => $month,
                "period_label" => $monthName,
                "temperature_avg" => round($monthRecords->avg("temperature"), 1),
                "humidity_avg" => round($monthRecords->avg("humidity"), 1),
                "temperature_min" => $monthRecords->min("temperature"),
                "temperature_max" => $monthRecords->max("temperature"),
                "humidity_min" => $monthRecords->min("humidity"),
                "humidity_max" => $monthRecords->max("humidity"),
                "count" => $monthRecords->count(),
            ];
        }

        // Sort by period
        usort($this->monthlyComparisons, function ($a, $b) {
            return $a["period"] <=> $b["period"];
        });
    }

    private function determineTrend($change)
    {
        if ($change > 1) {
            return "increasing";
        }
        if ($change < -1) {
            return "decreasing";
        }
        return "stable";
    }

    private function calculateStandardDeviation($values)
    {
        $count = count($values);
        if ($count < 2) {
            return 0;
        }

        $mean = array_sum($values) / $count;
        $squareDiffs = array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        return sqrt(array_sum($squareDiffs) / ($count - 1));
    }

    private function renderCharts()
    {
        if (empty($this->dailyAverages)) {
            $this->js(
                "
                ['daily-trends-chart', 'comparison-chart'].forEach(id => {
                    const container = document.getElementById(id);
                    if (container) container.innerHTML = '<div class=\"flex items-center justify-center h-full text-neutral-500\">" .
                    __("Data tidak tersedia") .
                    "</div>';
                });
            ",
            );
            return;
        }

        // Daily averages chart
        $dailyTrendsOptions = $this->getDailyTrendsChartOptions();

        // Comparison chart (weekly or monthly)
        $comparisonOptions = $this->getComparisonChartOptions();

        $this->js(
            "
            (function() {
                // Daily trends chart
                const dailyCtx = document.getElementById('daily-trends-chart');
                if (window.dailyTrendsChart) window.dailyTrendsChart.destroy();
                if (dailyCtx) {
                    window.dailyTrendsChart = new Chart(dailyCtx, " .
                json_encode($dailyTrendsOptions) .
                ");
                }

                // Comparison chart
                const comparisonCtx = document.getElementById('comparison-chart');
                if (window.comparisonChart) window.comparisonChart.destroy();
                if (comparisonCtx) {
                    window.comparisonChart = new Chart(comparisonCtx, " .
                json_encode($comparisonOptions) .
                ");
                }
            })();
        ",
        );
    }

    private function getDailyTrendsChartOptions()
    {
        return [
            "type" => "line",
            "data" => [
                "labels" => array_column($this->dailyAverages, "date"),
                "datasets" => [
                    [
                        "label" => __("Rata-rata Suhu Harian (°C)"),
                        "data" => array_column($this->dailyAverages, "temperature_avg"),
                        "borderColor" => "rgba(220, 38, 127, 1)",
                        "backgroundColor" => "rgba(220, 38, 127, 0.1)",
                        "yAxisID" => "y",
                        "tension" => 0.4,
                    ],
                    [
                        "label" => __("Rata-rata Kelembaban Harian (%)"),
                        "data" => array_column($this->dailyAverages, "humidity_avg"),
                        "borderColor" => "rgba(59, 130, 246, 1)",
                        "backgroundColor" => "rgba(59, 130, 246, 0.1)",
                        "yAxisID" => "y1",
                        "tension" => 0.4,
                    ],
                ],
            ],
            "options" => [
                "responsive" => true,
                "maintainAspectRatio" => false,
                "interaction" => [
                    "intersect" => false,
                    "mode" => "index",
                ],
                "scales" => [
                    "x" => [
                        "type" => "time",
                        "time" => [
                            "unit" => "day",
                            "displayFormats" => [
                                "day" => "MMM dd",
                            ],
                        ],
                        "title" => [
                            "display" => true,
                            "text" => __("Tanggal"),
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                        "ticks" => [
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                    ],
                    "y" => [
                        "type" => "linear",
                        "display" => true,
                        "position" => "left",
                        "title" => [
                            "display" => true,
                            "text" => __("Suhu (°C)"),
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                        "ticks" => [
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                    ],
                    "y1" => [
                        "type" => "linear",
                        "display" => true,
                        "position" => "right",
                        "title" => [
                            "display" => true,
                            "text" => __("Kelembaban (%)"),
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                        "grid" => [
                            "drawOnChartArea" => false,
                        ],
                        "ticks" => [
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                    ],
                ],
                "plugins" => [
                    "title" => [
                        "display" => true,
                        "text" => __("Tren Harian"),
                        "color" => session("bg") === "dark" ? "#e5e5e5" : "#404040",
                    ],
                    "legend" => [
                        "display" => true,
                        "position" => "top",
                    ],
                ],
            ],
        ];
    }

    private function getComparisonChartOptions()
    {
        $data = $this->comparison_period === "week" ? $this->weeklyComparisons : $this->monthlyComparisons;
        $title = $this->comparison_period === "week" ? __("Perbandingan Mingguan") : __("Perbandingan Bulanan");

        return [
            "type" => "bar",
            "data" => [
                "labels" => array_column($data, "period_label"),
                "datasets" => [
                    [
                        "label" => __("Rata-rata Suhu (°C)"),
                        "data" => array_column($data, "temperature_avg"),
                        "backgroundColor" => "rgba(220, 38, 127, 0.8)",
                        "borderColor" => "rgba(220, 38, 127, 1)",
                        "borderWidth" => 1,
                        "yAxisID" => "y",
                    ],
                    [
                        "label" => __("Rata-rata Kelembaban (%)"),
                        "data" => array_column($data, "humidity_avg"),
                        "backgroundColor" => "rgba(59, 130, 246, 0.8)",
                        "borderColor" => "rgba(59, 130, 246, 1)",
                        "borderWidth" => 1,
                        "yAxisID" => "y1",
                    ],
                ],
            ],
            "options" => [
                "responsive" => true,
                "maintainAspectRatio" => false,
                "plugins" => [
                    "title" => [
                        "display" => true,
                        "text" => $title,
                        "color" => session("bg") === "dark" ? "#e5e5e5" : "#404040",
                    ],
                    "legend" => [
                        "display" => true,
                        "position" => "top",
                    ],
                ],
                "scales" => [
                    "x" => [
                        "title" => [
                            "display" => true,
                            "text" => $this->comparison_period === "week" ? __("Minggu") : __("Bulan"),
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                        "ticks" => [
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                    ],
                    "y" => [
                        "type" => "linear",
                        "display" => true,
                        "position" => "left",
                        "title" => [
                            "display" => true,
                            "text" => __("Suhu (°C)"),
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                        "ticks" => [
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                    ],
                    "y1" => [
                        "type" => "linear",
                        "display" => true,
                        "position" => "right",
                        "title" => [
                            "display" => true,
                            "text" => __("Kelembaban (%)"),
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                        "grid" => [
                            "drawOnChartArea" => false,
                        ],
                        "ticks" => [
                            "color" => session("bg") === "dark" ? "#525252" : "#a3a3a3",
                        ],
                    ],
                ],
            ],
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
                                <x-text-button class="uppercase ml-3">
                                    {{ __("Rentang") }}
                                    <i class="icon-chevron-down ms-1"></i>
                                </x-text-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">{{ __("Minggu ini") }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">{{ __("Minggu lalu") }}</x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">{{ __("Bulan ini") }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">{{ __("Bulan lalu") }}</x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisQuarter">{{ __("Kuartal ini") }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastQuarter">{{ __("Kuartal lalu") }}</x-dropdown-link>
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

            <!-- Location and Comparison Period -->
            <div class="flex gap-3">
                <div>
                    <label for="location-filter" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Lokasi") }}</label>
                    <x-select class="w-24" id="location-filter" wire:model.live="location" disabled>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc }}" {{ $loc === "ip" ? "selected" : "" }}>{{ strtoupper($loc) }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label for="comparison-period" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Perbandingan") }}</label>
                    <x-select class="w-32" id="comparison-period" wire:model.live="comparison_period">
                        <option value="week">{{ __("Mingguan") }}</option>
                        <option value="month">{{ __("Bulanan") }}</option>
                    </x-select>
                </div>
            </div>

            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>

            <!-- Loading indicator -->
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="hidden">
                    <x-progress-bar :$progress>
                        <span
                            x-text="
                                progress < 49
                                    ? '{{ __("Mengambil data...") }}'
                                    : progress < 98
                                      ? '{{ __("Menghitung tren...") }}'
                                      : '{{ __("Merender grafik...") }}'
                            "
                        ></span>
                    </x-progress-bar>
                </div>
            </div>
        </div>
    </div>

    <!-- Trend Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Temperature Trend -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @switch($trendMetrics["temperature_trend"] ?? "stable")
                        @case("increasing")
                            <i class="icon-trending-up text-2xl text-red-500"></i>

                            @break
                        @case("decreasing")
                            <i class="icon-trending-down text-2xl text-blue-500"></i>

                            @break
                        @default
                            <i class="icon-minus text-2xl text-green-500"></i>
                    @endswitch
                </div>
                <div class="ml-4">
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __("Tren Suhu") }}</div>
                    <div class="text-lg font-bold">
                        @switch($trendMetrics["temperature_trend"] ?? "stable")
                            @case("increasing")
                                {{ __("Meningkat") }}

                                @break
                            @case("decreasing")
                                {{ __("Menurun") }}

                                @break
                            @default
                                {{ __("Stabil") }}
                        @endswitch
                    </div>
                    <div class="text-xs text-neutral-500">{{ ($trendMetrics["temperature_change"] ?? 0) > 0 ? "+" : "" }}{{ $trendMetrics["temperature_change"] ?? 0 }}°C</div>
                </div>
            </div>
        </div>

        <!-- Humidity Trend -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @switch($trendMetrics["humidity_trend"] ?? "stable")
                        @case("increasing")
                            <i class="icon-trending-up text-2xl text-red-500"></i>

                            @break
                        @case("decreasing")
                            <i class="icon-trending-down text-2xl text-blue-500"></i>

                            @break
                        @default
                            <i class="icon-minus text-2xl text-green-500"></i>
                    @endswitch
                </div>
                <div class="ml-4">
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __("Tren Kelembaban") }}</div>
                    <div class="text-lg font-bold">
                        @switch($trendMetrics["humidity_trend"] ?? "stable")
                            @case("increasing")
                                {{ __("Meningkat") }}

                                @break
                            @case("decreasing")
                                {{ __("Menurun") }}

                                @break
                            @default
                                {{ __("Stabil") }}
                        @endswitch
                    </div>
                    <div class="text-xs text-neutral-500">{{ ($trendMetrics["humidity_change"] ?? 0) > 0 ? "+" : "" }}{{ $trendMetrics["humidity_change"] ?? 0 }}%</div>
                </div>
            </div>
        </div>

        <!-- Temperature Volatility -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __("Volatilitas Suhu") }}</div>
            <div
                class="text-2xl font-bold {{ ($trendMetrics["volatility_temperature"] ?? 0) > 10 ? "text-red-500" : (($trendMetrics["volatility_temperature"] ?? 0) > 5 ? "text-yellow-500" : "text-green-500") }}"
            >
                {{ $trendMetrics["volatility_temperature"] ?? 0 }}%
            </div>
            <div class="text-xs text-neutral-500">{{ __("Koefisien variasi") }}</div>
        </div>

        <!-- Humidity Volatility -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __("Volatilitas Kelembaban") }}</div>
            <div
                class="text-2xl font-bold {{ ($trendMetrics["volatility_humidity"] ?? 0) > 10 ? "text-red-500" : (($trendMetrics["volatility_humidity"] ?? 0) > 5 ? "text-yellow-500" : "text-green-500") }}"
            >
                {{ $trendMetrics["volatility_humidity"] ?? 0 }}%
            </div>
            <div class="text-xs text-neutral-500">{{ __("Koefisien variasi") }}</div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Daily Trends Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80" wire:ignore>
                <canvas id="daily-trends-chart"></canvas>
            </div>
        </div>

        <!-- Comparison Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80" wire:ignore>
                <canvas id="comparison-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Daily Averages Table -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Rata-rata Harian") }}</h3>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="table table-sm text-sm w-full">
                <thead class="sticky top-0 bg-neutral-50 dark:bg-neutral-700">
                    <tr class="text-xs uppercase text-neutral-500 border-b">
                        <th class="px-4 py-3">{{ __("Tanggal") }}</th>
                        <th class="px-4 py-3">{{ __("Suhu Rata-rata (°C)") }}</th>
                        <th class="px-4 py-3">{{ __("Suhu Min-Max (°C)") }}</th>
                        <th class="px-4 py-3">{{ __("Kelembaban Rata-rata (%)") }}</th>
                        <th class="px-4 py-3">{{ __("Kelembaban Min-Max (%)") }}</th>
                        <th class="px-4 py-3">{{ __("Jumlah Data") }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dailyAverages as $day)
                        <tr class="border-b border-neutral-100 dark:border-neutral-700">
                            <td class="px-4 py-3 font-mono">{{ Carbon::parse($day["date"])->format("d M Y") }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $day["temperature_avg"] }}</td>
                            <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">{{ $day["temperature_min"] }} - {{ $day["temperature_max"] }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $day["humidity_avg"] }}</td>
                            <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">{{ $day["humidity_min"] }} - {{ $day["humidity_max"] }}</td>
                            <td class="px-4 py-3">{{ $day["count"] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Period Comparison Table -->
    @if ($comparison_period === "week" && ! empty($weeklyComparisons))
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Perbandingan Mingguan") }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-sm text-sm w-full">
                    <thead>
                        <tr class="text-xs uppercase text-neutral-500 border-b">
                            <th class="px-4 py-3">{{ __("Minggu") }}</th>
                            <th class="px-4 py-3">{{ __("Suhu Rata-rata (°C)") }}</th>
                            <th class="px-4 py-3">{{ __("Kelembaban Rata-rata (%)") }}</th>
                            <th class="px-4 py-3">{{ __("Rentang Suhu (°C)") }}</th>
                            <th class="px-4 py-3">{{ __("Rentang Kelembaban (%)") }}</th>
                            <th class="px-4 py-3">{{ __("Total Data") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($weeklyComparisons as $week)
                            <tr class="border-b border-neutral-100 dark:border-neutral-700">
                                <td class="px-4 py-3 font-medium">{{ $week["period_label"] }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $week["temperature_avg"] }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $week["humidity_avg"] }}</td>
                                <td class="px-4 py-3">{{ $week["temperature_max"] - $week["temperature_min"] }}</td>
                                <td class="px-4 py-3">{{ $week["humidity_max"] - $week["humidity_min"] }}</td>
                                <td class="px-4 py-3">{{ $week["count"] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($comparison_period === "month" && ! empty($monthlyComparisons))
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Perbandingan Bulanan") }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-sm text-sm w-full">
                    <thead>
                        <tr class="text-xs uppercase text-neutral-500 border-b">
                            <th class="px-4 py-3">{{ __("Bulan") }}</th>
                            <th class="px-4 py-3">{{ __("Suhu Rata-rata (°C)") }}</th>
                            <th class="px-4 py-3">{{ __("Kelembaban Rata-rata (%)") }}</th>
                            <th class="px-4 py-3">{{ __("Rentang Suhu (°C)") }}</th>
                            <th class="px-4 py-3">{{ __("Rentang Kelembaban (%)") }}</th>
                            <th class="px-4 py-3">{{ __("Total Data") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthlyComparisons as $month)
                            <tr class="border-b border-neutral-100 dark:border-neutral-700">
                                <td class="px-4 py-3 font-medium">{{ $month["period_label"] }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $month["temperature_avg"] }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $month["humidity_avg"] }}</td>
                                <td class="px-4 py-3">{{ $month["temperature_max"] - $month["temperature_min"] }}</td>
                                <td class="px-4 py-3">{{ $month["humidity_max"] - $month["humidity_min"] }}</td>
                                <td class="px-4 py-3">{{ $month["count"] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@script
    <script>
        $wire.$dispatch('update');
    </script>
@endscript
