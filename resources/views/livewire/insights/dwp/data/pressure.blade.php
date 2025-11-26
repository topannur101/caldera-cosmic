<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Traits\HasDateRangeFilter;
use App\Models\InsDwpDevice;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\InsDwpCount;
use Carbon\Carbon;

new #[Layout("layouts.app")] class extends Component {
    use WithPagination;
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public $device_id;

    #[Url]
    public string $line = "G5";

    // Selected machine filter (bound to the view via wire:model)
    #[Url]
    public string $machine = "";

    // Selected position filter (L for Left, R for Right)
    #[Url]
    public string $position = "L";

    public array $devices = [];
    public int $perPage = 20;
    public string $view = "pressure";

    public function mount()
    {
        if (!$this->start_at || !$this->end_at) {
            $this->setThisWeek();
        }

        $this->devices = InsDwpDevice::orderBy("name")
            ->get()
            ->pluck("name", "id")
            ->toArray();

        $this->dispatch("update-menu", $this->view);
    }

    private function getCountsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsDwpCount::select(
            "ins_dwp_counts.*",
            "ins_dwp_counts.created_at as count_created_at"
        )
            ->whereBetween("ins_dwp_counts.created_at", [$start, $end]);

        if ($this->device_id) {
            $device = InsDwpDevice::find($this->device_id);
            if ($device) {
                $deviceLines = $device->getLines();
                $query->whereIn("ins_dwp_counts.line", $deviceLines);
            }
        }

        if ($this->line) {
            $query->where("ins_dwp_counts.line", "like", "%" . strtoupper(trim($this->line)) . "%");
        }

        if (!empty($this->machine)) { // Changed from $this->mechine to $this->machine
            // allow numeric or string machine identifier
            $query->where('ins_dwp_counts.mechine', $this->machine); // Changed from $this->mechine to $this->machine
        }

        return $query->orderBy("ins_dwp_counts.created_at", "DESC");
    }

    /**
     * GET DATA MACHINES
     * Description : This code for get data machines on database ins_dwp_device
     */
    private function getDataMachines($selectedLine = null)
    {
        if (!$selectedLine) {
            return [];
        }

        // Query for the specific device that handles this line to avoid loading all of them.
        $device = InsDwpDevice::whereJsonContains('config', [['line' => strtoupper($selectedLine)]])
            ->select('config')
            ->first();

        if ($device) {
            foreach ($device->config as $lineConfig) {
                if (strtoupper($lineConfig['line']) === strtoupper($selectedLine)) {
                    return $lineConfig['list_mechine'] ?? [];
                }
            }
        }
        return [];
    }

    /**
     * Helper function to calculate median
     */
    private function getMedian(array $array)
    {
        if (empty($array)) return 0;
        // Filter out non-numeric values
        $numericArray = array_filter($array, 'is_numeric');
        if (empty($numericArray)) return 0;

        sort($numericArray);
        $count = count($numericArray);
        $middle = floor(($count - 1) / 2);
        $median = ($count % 2) ? $numericArray[$middle] : ($numericArray[$middle] + $numericArray[$middle + 1]) / 2;

        return round($median);
    }

    /**
     * Calculates the 5-point summary (min, q1, median, q3, max) for a boxplot.
     */
    private function getBoxplotSummary(array $data): ?array
    {
        if (empty($data)) {
            return null; // Return null if there is no data
        }

        sort($data);
        $count = count($data);
        $min = $data[0];
        $max = $data[$count - 1];

        // Median (Q2)
        $mid_index = (int)floor($count / 2);
        $median = ($count % 2 === 0)
            ? ($data[$mid_index - 1] + $data[$mid_index]) / 2
            : $data[$mid_index];

        // Q1 (Median of lower half)
        $lower_half = array_slice($data, 0, $mid_index);
        $q1 = 0;
        if (!empty($lower_half)) {
            $q1_count = count($lower_half);
            $q1_mid_index = (int)floor($q1_count / 2);
            $q1 = ($q1_count % 2 === 0)
                ? ($lower_half[$q1_mid_index - 1] + $lower_half[$q1_mid_index]) / 2
                : $lower_half[$q1_mid_index];
        } else {
            $q1 = $min;
        }

        // Q3 (Median of upper half)
        $upper_half = array_slice($data, ($count % 2 === 0) ? $mid_index : $mid_index + 1);
        $q3 = 0;
        if (!empty($upper_half)) {
            $q3_count = count($upper_half);
            $q3_mid_index = (int)floor($q3_count / 2);
            $q3 = ($q3_count % 2 === 0)
                ? ($upper_half[$q3_mid_index - 1] + $upper_half[$q3_mid_index]) / 2
                : $upper_half[$q3_mid_index];
        } else {
             $q3 = $max;
        }

        // Return the 5-point summary, rounded
        return array_map(fn($v) => round($v, 2), [$min, $q1, $median, $q3, $max]);
    }

    #[On("updated")]
    public function with(): array
    {
        $counts = $this->getCountsQuery()->paginate($this->perPage);
        $this->generateCharts(); // Add this line to regenerate charts when filters change
        return [
            "counts" => $counts,
        ];
    }

    #[On("updated")]
    public function update()
    {
        $this->generateCharts();
    }

    // Generate Charts
    private function generateCharts()
    {
        // Get all the relevant data points based on the filters (date, line, machine)
        $dataRaw = InsDwpCount::select(
            "ins_dwp_counts.*",
            "ins_dwp_counts.created_at as count_created_at"
        )
            ->whereBetween("ins_dwp_counts.created_at", [
                Carbon::parse($this->start_at),
                Carbon::parse($this->end_at)->endOfDay(),
            ]);

        if ($this->line) {
            $dataRaw->where("ins_dwp_counts.line", "like", "%" . strtoupper(trim($this->line)) . "%");
        }

        if (!empty($this->machine)) {
            $dataRaw->where('ins_dwp_counts.mechine', $this->machine); // Make sure 'mechine' matches your actual DB column name
        }

        if (!empty($this->position)) {
            $dataRaw->where('ins_dwp_counts.position', $this->position);
        }

        // Generate duration chart data
        $this->generateDurationChart($dataRaw);

        $presureData = $dataRaw->whereNotNull('pv')->get()->toArray();
        $counts = collect($presureData);

        // Prepare arrays to hold median values for each of the 4 sensors
        $toeheel_left_data = [];
        $toeheel_right_data = [];
        $side_left_data = [];
        $side_right_data = [];

        // Loop through each database record
        foreach ($counts as $count) {
            $arrayPv = json_decode($count['pv'], true);

            // Check for enhanced PV structure first
            if (isset($arrayPv['waveforms']) && is_array($arrayPv['waveforms'])) {
                // Enhanced format: extract waveforms
                $waveforms = $arrayPv['waveforms'];
                $toeHeelArray = $waveforms[0] ?? [];
                $sideArray = $waveforms[1] ?? [];
            } elseif (isset($arrayPv[0]) && isset($arrayPv[1])) {
                // Legacy format: direct array access
                $toeHeelArray = $arrayPv[0];
                $sideArray = $arrayPv[1];
            } else {
                // Invalid format, skip this record
                continue;
            }

            // Calculate median for each sensor array
            $toeHeelMedian = $this->getMedian($toeHeelArray);
            $sideMedian = $this->getMedian($sideArray);

            if ($count['position'] === 'L') {
                $toeheel_left_data[] = $toeHeelMedian;
                $side_left_data[] = $sideMedian;
            } elseif ($count['position'] === 'R') {
                $toeheel_right_data[] = $toeHeelMedian;
                $side_right_data[] = $sideMedian;
            }
        }

        $datasets = [
            [
                'x' => 'Toe-Heel Left',
                'y' => $this->getBoxplotSummary($toeheel_left_data)
            ],
            [
                'x' => 'Toe-Heel Right',
                'y' => $this->getBoxplotSummary($toeheel_right_data)
            ],
            [
                'x' => 'Side Left',
                'y' => $this->getBoxplotSummary($side_left_data)
            ],
            [
                'x' => 'Side Right',
                'y' => $this->getBoxplotSummary($side_right_data)
            ],
        ];

        // Filter out any datasets that returned null (no data)
        $filteredDatasets = array_filter($datasets, fn($d) => $d['y'] !== null);

        $performanceData = [
            'labels' => ['Toe-Heel Left', 'Toe-Heel Right', 'Side Left', 'Side Right'],
            'datasets' => array_values($filteredDatasets), // Pass only the valid data
        ];

        // Dispatch the event to the frontend to update the chart
        $this->dispatch('refresh-performance-chart', [
            'performanceData' => $performanceData,
        ]);
    }

    /**
     * Generate Duration Chart Data
     * Categorizes batch processing times by machine
     */
    private function generateDurationChart($query)
    {
        // Get data with duration information
        $durationData = clone $query;
        $durationData = $durationData->whereNotNull('duration')
            ->whereNotNull('mechine')
            ->get();

        // Initialize counters for each machine (1-4)
        $machines = [1 => [], 2 => [], 3 => [], 4 => []];

        foreach ($durationData as $record) {
            $duration = floatval($record->duration);
            $machine = intval($record->mechine);

            if (!isset($machines[$machine])) {
                continue;
            }

            // Categorize based on duration
            if ($duration < 10) {
                $machines[$machine]['too_early_max'] = ($machines[$machine]['too_early_max'] ?? 0) + 1;
            } elseif ($duration < 13) {
                $machines[$machine]['too_early_min'] = ($machines[$machine]['too_early_min'] ?? 0) + 1;
            } elseif ($duration >= 13 && $duration <= 16) {
                $machines[$machine]['on_time'] = ($machines[$machine]['on_time'] ?? 0) + 1;
            } else { // > 16
                $machines[$machine]['on_time_manual'] = ($machines[$machine]['on_time_manual'] ?? 0) + 1;
            }
        }

        // Prepare data for chart
        $chartData = [
            'categories' => ['Machine 1', 'Machine 2', 'Machine 3', 'Machine 4'],
            'series' => [
                [
                    'name' => 'Too early (< 10s)',
                    'data' => [
                        $machines[1]['too_early_max'] ?? 0,
                        $machines[2]['too_early_max'] ?? 0,
                        $machines[3]['too_early_max'] ?? 0,
                        $machines[4]['too_early_max'] ?? 0,
                    ],
                    'color' => '#ef4444' // Red
                ],
                [
                    'name' => 'Too early (10-13s)',
                    'data' => [
                        $machines[1]['too_early_min'] ?? 0,
                        $machines[2]['too_early_min'] ?? 0,
                        $machines[3]['too_early_min'] ?? 0,
                        $machines[4]['too_early_min'] ?? 0,
                    ],
                    'color' => '#ef4444' // Red
                ],
                [
                    'name' => 'On time (13-16s)',
                    'data' => [
                        $machines[1]['on_time'] ?? 0,
                        $machines[2]['on_time'] ?? 0,
                        $machines[3]['on_time'] ?? 0,
                        $machines[4]['on_time'] ?? 0,
                    ],
                    'color' => '#22c55e' // Green
                ],
                [
                    'name' => 'On time (manual)',
                    'data' => [
                        $machines[1]['on_time_manual'] ?? 0,
                        $machines[2]['on_time_manual'] ?? 0,
                        $machines[3]['on_time_manual'] ?? 0,
                        $machines[4]['on_time_manual'] ?? 0,
                    ],
                    'color' => '#f97316' // Orange
                ],
            ],
        ];

        // Dispatch the event to the frontend
        $this->dispatch('refresh-duration-chart', [
            'durationData' => $chartData,
        ]);
    }
}; ?>

<div>
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
                <x-select wire:model.live="line" wire:change="dispatch('updated')" class="w-full lg:w-32">
                    <option value="g5">G5</option>
                </x-select>
            </div>
            <div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Machine") }}</label>
                <x-select wire:model.live="machine" wire:change="dispatch('updated')" class="w-full lg:w-32">
                    <option value="">All</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </x-select>
            </div>
            <div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Position") }}</label>
                <x-select wire:model.live="position" wire:change="dispatch('updated')" class="w-full lg:w-32">
                    <option value="">All</option>
                    <option value="L">Left</option>
                    <option value="R">Right</option>
                </x-select>
            </div>
        </div>
    </div>
  </div>
  <div class="overflow-hidden">
    <div class="grid grid-cols-1 gap-2 md:grid-cols-1 md:gap-2">
         <!-- chart section type boxplot -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div
                x-data="{
                    performanceChart: null,

                    // This function will now handle all chart creation/updates
                    initOrUpdateChart(performanceData) {
                        const chartEl = this.$refs.chartContainer; // Get element using x-ref
                        if (!chartEl) {
                            console.error('[ApexChart] Chart container x-ref=\'chartContainer\' not found.');
                            return;
                        }

                        const datasets = performanceData.datasets || [];

                        // --- FIX 1: Corrected Data Transformation ---
                        // ApexCharts boxplot expects: { x: 'label', y: [min, q1, median, q3, max] }
                        const transformedData = datasets
                            .filter(dataset => {
                                return dataset &&
                                    dataset.hasOwnProperty('x') &&
                                    dataset.hasOwnProperty('y') &&
                                    Array.isArray(dataset.y) &&
                                    dataset.y.length === 5 &&
                                    dataset.y.every(val => typeof val === 'number' && !isNaN(val));
                            })
                            .map(dataset => {
                                return {
                                    x: dataset.x, // The category name (e.g., 'Toe-Heel Left')
                                    y: dataset.y  // The 5-point array [min, q1, median, q3, max]
                                };
                            });

                        const hasValidData = transformedData.length > 0;
                        console.log('[ApexChart] Valid transformed data:', transformedData);

                        // --- FIX 2: Robust Update Logic ---
                        // Always destroy the old chart instance before creating a new one.
                        if (this.performanceChart) {
                            console.log('[ApexChart] Destroying old chart before update.');
                            this.performanceChart.destroy();
                        }

                        const options = {
                            // --- FIX 3: Corrected Series Definition ---
                            series: [{
                                name: 'Performance',
                                data: transformedData
                            }],
                            chart: {
                                type: 'boxPlot',
                                height: 350,
                                toolbar: { show: true },
                                animations: {
                                    enabled: true,
                                    speed: 350 // Faster animation for updates
                                }
                            },
                            title: {
                                text: 'DWP Machine Performance Boxplot'
                            },
                            xaxis: {
                                // --- FIX 4: Removed Redundant Categories ---
                                type: 'category',
                            },
                            yaxis: {
                                title: { text: 'Pressure' },
                                labels: {
                                    formatter: (val) => { return val.toFixed(2) }
                                }
                            },
                            tooltip: {
                                y: {
                                    formatter: (val) => { return val.toFixed(2) }
                                }
                            },
                            noData: hasValidData ? undefined : {
                                text: 'No data available',
                                align: 'center',
                                verticalAlign: 'middle',
                            }
                        };

                        console.log('[ApexChart] Creating new chart instance.');
                        this.performanceChart = new ApexCharts(chartEl, options);
                        this.performanceChart.render();
                    }
                }"
                x-init="
                    // Listen for the Livewire event
                    $wire.on('refresh-performance-chart', function(payload) {
                        let data = payload;
                        if (payload && payload.detail) data = payload.detail;
                        if (Array.isArray(payload) && payload.length) data = payload[0];
                        if (payload && payload[0] && payload[0].performanceData) data = payload[0];

                        const performanceData = data?.performanceData;
                        if (!performanceData) {
                            console.warn('[DWP Dashboard] refresh-performance-chart payload missing expected properties', data);
                            return;
                        }
                        console.log('Received refresh-performance-chart event with ', performanceData);

                        try {
                            // Call our Alpine method
                            initOrUpdateChart(performanceData);
                        } catch (e) {
                            console.error('[DWP Dashboard] error while initializing/updating ApexChart', e, performanceData);
                        }
                    });

                    // Initial load - dispatch the updated event to fetch data and render chart
                    console.log('[Alpine] Triggering initial data load.');
                    $wire.$dispatch('updated');
                " >
                <div id="performanceChart" x-ref="chartContainer" wire:ignore></div>
            </div>
        </div>
        <!-- Duration Chart - Stacked Bar Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div
                x-data="{
                    durationChart: null,

                    initOrUpdateDurationChart(durationData) {
                        const chartEl = this.$refs.durationChartContainer;
                        if (!chartEl) {
                            console.error('[DurationChart] Chart container not found.');
                            return;
                        }

                        const series = durationData.series || [];
                        const categories = durationData.categories || [];

                        console.log('[DurationChart] Data:', { series, categories });

                        // Destroy old chart if exists
                        if (this.durationChart) {
                            console.log('[DurationChart] Destroying old chart.');
                            this.durationChart.destroy();
                        }

                        const options = {
                            series: series,
                            chart: {
                                type: 'bar',
                                height: 350,
                                stacked: true,
                                toolbar: { show: true },
                                animations: {
                                    enabled: true,
                                    speed: 350
                                }
                            },
                            plotOptions: {
                                bar: {
                                    horizontal: true,
                                    dataLabels: {
                                        total: {
                                            enabled: true,
                                            offsetX: 0,
                                            style: {
                                                fontSize: '13px',
                                                fontWeight: 900
                                            }
                                        }
                                    }
                                },
                            },
                            stroke: {
                                width: 1,
                                colors: ['#fff']
                            },
                            title: {
                                text: 'Batch Processing Time by Machine'
                            },
                            xaxis: {
                                categories: categories,
                                title: {
                                    text: 'Cycle Count'
                                }
                            },
                            yaxis: {
                                title: {
                                    text: 'Machine'
                                }
                            },
                            tooltip: {
                                y: {
                                    formatter: function (val) {
                                        return val + ' Cycles'
                                    }
                                }
                            },
                            fill: {
                                opacity: 1
                            },
                            legend: {
                                position: 'top',
                                horizontalAlign: 'left',
                                offsetX: 40
                            },
                            colors: series.map(s => s.color)
                        };

                        console.log('[DurationChart] Creating new chart.');
                        this.durationChart = new ApexCharts(chartEl, options);
                        this.durationChart.render();
                    }
                }"
                x-init="
                    $wire.on('refresh-duration-chart', function(payload) {
                        let data = payload;
                        if (payload && payload.detail) data = payload.detail;
                        if (Array.isArray(payload) && payload.length) data = payload[0];
                        if (payload && payload[0] && payload[0].durationData) data = payload[0];

                        const durationData = data?.durationData;
                        if (!durationData) {
                            console.warn('[DurationChart] Missing durationData in payload', data);
                            return;
                        }
                        console.log('[DurationChart] Received data:', durationData);

                        try {
                            initOrUpdateDurationChart(durationData);
                        } catch (e) {
                            console.error('[DurationChart] Error:', e);
                        }
                    });

                    console.log('[DurationChart] Waiting for data...');
                "
            >
                <div x-ref="durationChartContainer" wire:ignore></div>
            </div>
        </div>
    </div>
  </div>
</div>
