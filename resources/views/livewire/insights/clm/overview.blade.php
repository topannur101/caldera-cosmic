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

    public array $locations = [];

    public array $currentReadings = [
        "updated_at" => null,
        "temperature" => 0,
        "humidity" => 0,
    ];

    public array $periodStats = [];

    public int $progress = 0;

    public function mount()
    {
        if (! $this->start_at || ! $this->end_at) {
            $this->setThisWeek();
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

        // Phase 2: Calculate metrics (49-98%)
        $this->progress = 60;
        $this->stream(to: "progress", content: $this->progress, replace: true);

        $this->calculateMetrics($records);

        $this->progress = 98;
        $this->stream(to: "progress", content: $this->progress, replace: true);

        // Phase 3: Render charts (98-100%)
        $this->renderCharts($records);

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

    private function calculateMetrics($records)
    {
        if ($records->isEmpty()) {
            $this->currentReadings = [
                "temperature" => 0,
                "humidity" => 0,
                "updated_at" => null,
            ];
            $this->periodStats = [
                "temperature" => ["min" => 0, "max" => 0, "avg" => 0],
                "humidity" => ["min" => 0, "max" => 0, "avg" => 0],
                "total_records" => 0,
            ];
            return;
        }

        // Get latest readings
        $latest = $records->last();
        $this->currentReadings = [
            "temperature" => $latest->temperature,
            "humidity" => $latest->humidity,
            "updated_at" => $latest->created_at,
        ];

        // Calculate period statistics
        $temperatures = $records->pluck("temperature");
        $humidity = $records->pluck("humidity");

        $this->periodStats = [
            "temperature" => [
                "min" => $temperatures->min(),
                "max" => $temperatures->max(),
                "avg" => round($temperatures->avg(), 1),
            ],
            "humidity" => [
                "min" => $humidity->min(),
                "max" => $humidity->max(),
                "avg" => round($humidity->avg(), 1),
            ],
            "total_records" => $records->count(),
        ];
    }

    private function renderCharts($records)
    {
        if ($records->isEmpty()) {
            $this->js(
                "
                const container = document.getElementById('climate-overview-chart');
                if (container) {
                    container.innerHTML = '<div class=\"flex items-center justify-center h-full text-neutral-500\">" .
                    __("Data tidak tersedia") .
                    "</div>';
                }
            ",
            );
            return;
        }

        // Determine aggregation based on date range
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at);
        $daysDiff = $start->diffInDays($end);

        $chartData = $this->prepareChartData($records, $daysDiff <= 7);

        $chartOptions = [
            "type" => "line",
            "data" => $chartData,
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
                            "displayFormats" => [
                                "hour" => "MMM dd HH:mm",
                                "day" => "MMM dd",
                            ],
                        ],
                        "title" => [
                            "display" => true,
                            "text" => __("Waktu"),
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
                    "datalabels" => [
                        "display" => false,
                    ],
                    "title" => [
                        "display" => true,
                        "text" => __("Tren Suhu dan Kelembaban"),
                        "color" => session("bg") === "dark" ? "#e5e5e5" : "#404040",
                    ],
                    "legend" => [
                        "display" => true,
                        "position" => "top",
                    ],
                ],
            ],
        ];

        $this->js(
            "
            (function() {
                const ctx = document.getElementById('climate-overview-chart');
                if (window.climateOverviewChart) window.climateOverviewChart.destroy();
                if (ctx) {
                    window.climateOverviewChart = new Chart(ctx, " .
                json_encode($chartOptions) .
                ");
                }
            })();
        ",
        );
    }

    private function prepareChartData($records, $useHourly = true)
    {
        if ($useHourly) {
            // Show all hourly data
            $temperatureData = [];
            $humidityData = [];

            foreach ($records as $record) {
                $timestamp = Carbon::parse($record->created_at)->format("c");
                $temperatureData[] = ["x" => $timestamp, "y" => $record->temperature];
                $humidityData[] = ["x" => $timestamp, "y" => $record->humidity];
            }
        } else {
            // Aggregate to daily averages
            $dailyData = $records->groupBy(function ($record) {
                return Carbon::parse($record->created_at)->format("Y-m-d");
            });

            $temperatureData = [];
            $humidityData = [];

            foreach ($dailyData as $date => $dayRecords) {
                $avgTemp = round($dayRecords->avg("temperature"), 1);
                $avgHumidity = round($dayRecords->avg("humidity"), 1);

                $temperatureData[] = ["x" => $date, "y" => $avgTemp];
                $humidityData[] = ["x" => $date, "y" => $avgHumidity];
            }
        }

        return [
            "datasets" => [
                [
                    "label" => __("Suhu (°C)"),
                    "data" => $temperatureData,
                    "borderColor" => "rgba(220, 38, 127, 1)",
                    "backgroundColor" => "rgba(220, 38, 127, 0.1)",
                    "yAxisID" => "y",
                    "tension" => 0.4,
                ],
                [
                    "label" => __("Kelembaban (%)"),
                    "data" => $humidityData,
                    "borderColor" => "rgba(59, 130, 246, 1)",
                    "backgroundColor" => "rgba(59, 130, 246, 0.1)",
                    "yAxisID" => "y1",
                    "tension" => 0.4,
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
                                <x-dropdown-link href="#" wire:click.prevent="setToday">{{ __("Hari ini") }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">{{ __("Kemarin") }}</x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">{{ __("Minggu ini") }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">{{ __("Minggu lalu") }}</x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">{{ __("Bulan ini") }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">{{ __("Bulan lalu") }}</x-dropdown-link>
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
                <label for="location-filter" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Lokasi") }}</label>
                <x-select class="w-32" id="location-filter" wire:model.live="location" disabled>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc }}" {{ $loc === "ip" ? "selected" : "" }}>{{ strtoupper($loc) }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>

            <!-- Loading indicator -->
            <div class="grow flex justify-center gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div wire:loading.class="hidden">{{ number_format($periodStats["total_records"] ?? 0) . " " . __("catatan") }}</div>
                        <div wire:loading.class.remove="hidden" class="hidden">
                            <x-progress-bar :$progress>
                                <span
                                    x-text="
                                        progress < 49
                                            ? '{{ __("Mengambil data...") }}'
                                            : progress < 98
                                              ? '{{ __("Menghitung metrik...") }}'
                                              : '{{ __("Merender grafik...") }}'
                                    "
                                ></span>
                            </x-progress-bar>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Readings & Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Current Temperature -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="icon-thermometer text-2xl text-pink-600"></i>
                </div>
                <div class="ml-4">
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __("Suhu Saat Ini") }}</div>
                    <div class="text-2xl font-bold pt-1">{{ $currentReadings["temperature"] ?? 0 }}°C</div>
                    @if ($currentReadings["updated_at"])
                        <div class="text-xs text-neutral-500">{{ Carbon::parse($currentReadings["updated_at"])->diffForHumans() }}</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Current Humidity -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="icon-droplet text-2xl text-blue-600"></i>
                </div>
                <div class="ml-4">
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __("Kelembaban Saat Ini") }}</div>
                    <div class="text-2xl font-bold pt-1">{{ $currentReadings["humidity"] ?? 0 }}%</div>
                    @if ($currentReadings["updated_at"])
                        <div class="text-xs text-neutral-500">{{ Carbon::parse($currentReadings["updated_at"])->diffForHumans() }}</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Temperature Stats -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __("Statistik Suhu") }}</div>
            <div class="space-y-1">
                <div class="flex justify-between text-sm">
                    <span>{{ __("Min") . " - " . __("Maks") }}:</span>
                    <span class="font-medium">{{ ($periodStats["temperature"]["min"] ?? 0) . " - " . ($periodStats["temperature"]["max"] ?? 0) }}°C</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span>{{ __("Rerata") . " | " . __("Rentang") }}:</span>
                    <span class="font-medium">
                        {{ ($periodStats["temperature"]["avg"] ?? 0) . " | " . ($periodStats["temperature"]["max"] ?? 0) - ($periodStats["temperature"]["min"] ?? 0) }}°C
                    </span>
                </div>
            </div>
        </div>

        <!-- Humidity Stats -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __("Statistik Kelembaban") }}</div>
            <div class="space-y-1">
                <div class="flex justify-between text-sm">
                    <span>{{ __("Min") . " - " . __("Maks") }}:</span>
                    <span class="font-medium">{{ ($periodStats["humidity"]["min"] ?? 0) . " - " . ($periodStats["humidity"]["max"] ?? 0) }}%</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span>{{ __("Rerata") . " | " . __("Rentang") }}:</span>
                    <span class="font-medium">
                        {{ ($periodStats["humidity"]["avg"] ?? 0) . " | " . ($periodStats["humidity"]["max"] ?? 0) - ($periodStats["humidity"]["min"] ?? 0) }}%
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Combined Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
        <div class="h-96" wire:ignore>
            <canvas id="climate-overview-chart"></canvas>
        </div>
    </div>
</div>

@script
    <script>
        $wire.$dispatch('update');
    </script>
@endscript
