<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\InsDwpLoadcell;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;

new #[Layout("layouts.app")] class extends Component {
    use WithPagination;
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public string $line = "";

    #[Url]
    public string $plant = "";

    #[Url]
    public string $machine = "";

    #[Url]
    public string $position = "";

    #[Url]
    public string $result = "";

    public string $view = "summary-loadcell";

    public function mount()
    {
        if (! $this->start_at || ! $this->end_at) {
            $this->setToday();
        }

        // update menu
        $this->dispatch("update-menu", $this->view);
    }

    /**
     * Get filtered loadcell query
     */
    private function getLoadcellQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsDwpLoadcell::whereBetween("created_at", [$start, $end]);

        if ($this->plant) {
            $query->where("plant", "like", "%" . strtoupper(trim($this->plant)) . "%");
        }

        if ($this->line) {
            $query->where("line", strtoupper(trim($this->line)));
        }

        if ($this->machine) {
            $query->where("machine_name", $this->machine);
        }

        if ($this->position) {
            $query->where("position", "like", "%" . $this->position . "%");
        }

        if ($this->result) {
            $query->where("result", $this->result);
        }

        return $query;
    }

    /**
     * Option 1: Overview Cards (KPIs)
     */
    private function getOverviewStats(): array
    {
        $query = $this->getLoadcellQuery();

        $totalTests = $query->count();
        $passedTests = (clone $query)->where('result', 'std')->count();
        $failedTests = $totalTests - $passedTests;
        $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;

        // Calculate average peak pressure
        $avgPeakPressure = 0;
        $peakPressures = [];
        
        (clone $query)->chunk(100, function ($records) use (&$peakPressures) {
            foreach ($records as $record) {
                $data = json_decode($record->loadcell_data, true);
                if (isset($data['metadata']['max_peak_pressure'])) {
                    $peakPressures[] = $data['metadata']['max_peak_pressure'];
                }
            }
        });

        if (count($peakPressures) > 0) {
            $avgPeakPressure = round(array_sum($peakPressures) / count($peakPressures), 2);
        }

        // Active machines count
        $activeMachines = (clone $query)->distinct('machine_name')->count('machine_name');

        // Tests by operator
        $operatorCounts = (clone $query)
            ->selectRaw('operator, COUNT(*) as count')
            ->whereNotNull('operator')
            ->groupBy('operator')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->pluck('count', 'operator')
            ->toArray();

        return [
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'failed_tests' => $failedTests,
            'pass_rate' => $passRate,
            'avg_peak_pressure' => $avgPeakPressure,
            'active_machines' => $activeMachines,
            'operator_counts' => $operatorCounts,
        ];
    }

    /**
     * Option 3: Pressure Analysis - Bar Chart Data
     * Returns average pressure for each sensor
     */
    private function getPressureBoxplotData(): array
    {
        $query = $this->getLoadcellQuery();
        
        // Initialize arrays for each sensor
        $sensorData = [
            'C1' => [], 'C2' => [], 'H1' => [], 'L1' => [], 
            'L2' => [], 'M1' => [], 'M2' => [], 'T1' => []
        ];

        $positionSuffix = '';
        if ($this->position) {
            $positionSuffix = '_' . strtoupper(substr($this->position, 0, 1));
        }

        // Collect all peak pressures from each sensor
        $query->chunk(100, function ($records) use (&$sensorData, $positionSuffix) {
            foreach ($records as $record) {
                $data = json_decode($record->loadcell_data, true);
                if (!isset($data['metadata']['cycles'])) continue;

                foreach ($data['metadata']['cycles'] as $cycle) {
                    if (!isset($cycle['sensors'])) continue;

                    foreach ($cycle['sensors'] as $sensorName => $values) {
                        // Extract base sensor name (C1, C2, H1, etc.)
                        $baseName = preg_replace('/_[LR]$/', '', $sensorName);
                        
                        // Filter by position if specified
                        if ($positionSuffix && !str_ends_with($sensorName, $positionSuffix)) {
                            continue;
                        }

                        if (isset($sensorData[$baseName]) && is_array($values)) {
                            foreach ($values as $value) {
                                if (is_numeric($value) && $value > 0) {
                                    $sensorData[$baseName][] = (float) $value;
                                }
                            }
                        }
                    }
                }
            }
        });

        // Calculate average pressure for each sensor
        $barChartData = [];
        foreach ($sensorData as $sensor => $values) {
            if (count($values) > 0) {
                $barChartData[$sensor] = round(array_sum($values) / count($values), 2);
            } else {
                $barChartData[$sensor] = 0;
            }
        }

        return $barChartData;
    }

    /**
     * Option 4: Quality Control Metrics - Bar Chart Data
     */
    private function getQualityMetrics(): array
    {
        $query = $this->getLoadcellQuery();

        // Pass/Fail Distribution
        $passFailData = (clone $query)
            ->selectRaw('result, COUNT(*) as count')
            ->groupBy('result')
            ->get()
            ->pluck('count', 'result')
            ->toArray();

        // Tests by Machine
        $machineData = (clone $query)
            ->selectRaw('machine_name, COUNT(*) as total, 
                SUM(CASE WHEN result = "std" THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN result != "std" THEN 1 ELSE 0 END) as failed')
            ->groupBy('machine_name')
            ->orderBy('machine_name')
            ->get()
            ->map(function ($item) {
                return [
                    'machine' => $item->machine_name,
                    'total' => $item->total,
                    'passed' => $item->passed,
                    'failed' => $item->failed,
                    'pass_rate' => $item->total > 0 ? round(($item->passed / $item->total) * 100, 1) : 0
                ];
            })
            ->toArray();

        // Tests by Position (Left vs Right)
        $positionData = (clone $query)
            ->selectRaw('position, COUNT(*) as total, 
                SUM(CASE WHEN result = "std" THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN result != "std" THEN 1 ELSE 0 END) as failed')
            ->groupBy('position')
            ->get()
            ->map(function ($item) {
                return [
                    'position' => $item->position,
                    'total' => $item->total,
                    'passed' => $item->passed,
                    'failed' => $item->failed,
                    'pass_rate' => $item->total > 0 ? round(($item->passed / $item->total) * 100, 1) : 0
                ];
            })
            ->toArray();

        return [
            'pass_fail' => $passFailData,
            'by_machine' => $machineData,
            'by_position' => $positionData,
        ];
    }

    /**
     * Option 7: Time-Based Analysis - Bar Chart Data
     */
    private function getTimeBasedAnalysis(): array
    {
        $query = $this->getLoadcellQuery();

        // Hourly distribution
        $hourlyData = (clone $query)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        // Fill missing hours with 0
        $hourlyComplete = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyComplete[$i] = $hourlyData[$i] ?? 0;
        }

        // Daily distribution
        $dailyData = (clone $query)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M d'),
                    'count' => $item->count
                ];
            })
            ->toArray();

        // Average latency analysis
        $latencyData = [];
        (clone $query)->whereNotNull('recorded_at')->chunk(100, function ($records) use (&$latencyData) {
            foreach ($records as $record) {
                if ($record->created_at && $record->recorded_at) {
                    $created = Carbon::parse($record->created_at);
                    $recorded = Carbon::parse($record->recorded_at);
                    $latencySeconds = abs($created->diffInSeconds($recorded));
                    $latencyData[] = $latencySeconds;
                }
            }
        });

        $avgLatency = count($latencyData) > 0 ? round(array_sum($latencyData) / count($latencyData), 2) : 0;

        return [
            'hourly' => $hourlyComplete,
            'daily' => $dailyData,
            'avg_latency_seconds' => $avgLatency,
        ];
    }

    public function with(): array
    {
        $overview = $this->getOverviewStats();
        $pressureBoxplot = $this->getPressureBoxplotData();
        $qualityMetrics = $this->getQualityMetrics();
        $timeAnalysis = $this->getTimeBasedAnalysis();

        // Inject chart rendering script
        $this->dispatch('charts-data-ready', [
            'pressureBoxplot' => $pressureBoxplot,
            'qualityMetrics' => $qualityMetrics,
            'timeAnalysis' => $timeAnalysis,
        ]);

        return [
            'overview' => $overview,
            'pressureBoxplot' => $pressureBoxplot,
            'qualityMetrics' => $qualityMetrics,
            'timeAnalysis' => $timeAnalysis,
        ];
    }
}; ?>

<div>
    <!-- Filters Section -->
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
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Plant") }}</label>
                    <x-select wire:model.live="plant" class="w-full lg:w-32">
                        <option value="">{{ __("All") }}</option>
                        <option value="Plant G">G</option>
                        <option value="Plant A">A</option>
                    </x-select>
                </div>
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
                    <x-select wire:model.live="line" class="w-full lg:w-32">
                        <option value="">{{ __("All") }}</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </x-select>
                </div>
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Machine") }}</label>
                    <x-select wire:model.live="machine" class="w-full lg:w-32">
                        <option value="">{{ __("All") }}</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </x-select>
                </div>
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Position") }}</label>
                    <x-select wire:model.live="position" class="w-full lg:w-32">
                        <option value="">{{ __("All") }}</option>
                        <option value="Left">Left</option>
                        <option value="Right">Right</option>
                    </x-select>
                </div>
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Result") }}</label>
                    <x-select wire:model.live="result" class="w-full lg:w-32">
                        <option value="">{{ __("All") }}</option>
                        <option value="std">Standard</option>
                        <option value="fail">Not Standard</option>
                    </x-select>
                </div>
            </div>
        </div>
    </div>

    <!-- Option 1: Overview Cards (KPIs) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Tests -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400 uppercase">{{ __("Total Tests") }}</p>
                    <p class="mt-2 text-3xl font-semibold text-neutral-900 dark:text-neutral-100">{{ number_format($overview['total_tests']) }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-full">
                    <i class="icon-clipboard-check text-2xl text-blue-600 dark:text-blue-400"></i>
                </div>
            </div>
        </div>

        <!-- Pass Rate -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400 uppercase">{{ __("Standard Rate") }}</p>
                    <p class="mt-2 text-3xl font-semibold text-neutral-900 dark:text-neutral-100">{{ $overview['pass_rate'] }}%</p>
                    <p class="text-xs text-neutral-500 mt-1">{{ $overview['passed_tests'] }} / {{ $overview['total_tests'] }}</p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-full">
                    <i class="icon-check-circle text-2xl text-green-600 dark:text-green-400"></i>
                </div>
            </div>
        </div>

        <!-- Average Peak Pressure -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400 uppercase">{{ __("Avg Peak Pressure") }}</p>
                    <p class="mt-2 text-3xl font-semibold text-neutral-900 dark:text-neutral-100">{{ $overview['avg_peak_pressure'] }}</p>
                    <p class="text-xs text-neutral-500 mt-1">kPa</p>
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-full">
                    <i class="icon-activity text-2xl text-purple-600 dark:text-purple-400"></i>
                </div>
            </div>
        </div>

        <!-- Active Machines -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400 uppercase">{{ __("Active Machines") }}</p>
                    <p class="mt-2 text-3xl font-semibold text-neutral-900 dark:text-neutral-100">{{ $overview['active_machines'] }}</p>
                </div>
                <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-full">
                    <i class="icon-cpu text-2xl text-orange-600 dark:text-orange-400"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Operators Card -->
    @if(count($overview['operator_counts']) > 0)
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">{{ __("Top Operators") }}</h3>
        <div class="space-y-3">
            @foreach($overview['operator_counts'] as $operator => $count)
            <div class="flex items-center justify-between">
                <span class="text-neutral-700 dark:text-neutral-300">{{ $operator }}</span>
                <div class="flex items-center gap-3">
                    <div class="w-32 bg-neutral-200 dark:bg-neutral-700 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($count / max($overview['operator_counts'])) * 100 }}%"></div>
                    </div>
                    <span class="text-neutral-900 dark:text-neutral-100 font-semibold w-12 text-right">{{ $count }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Option 3: Pressure Analysis - Boxplot -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">{{ __("Pressure Distribution by Sensor") }}</h3>
        <div id="pressureBoxplot"></div>
    </div>

    <!-- Option 4: Quality Control Metrics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Standard/Not Standard Distribution -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">{{ __("Standard/Not Standard Distribution") }}</h3>
            <div id="passFailChart"></div>
        </div>

        <!-- Tests by Position -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">{{ __("Tests by Position") }}</h3>
            <div id="positionChart"></div>
        </div>
    </div>

    <!-- Tests by Machine -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">{{ __("Quality by Machine") }}</h3>
        <div id="machineChart"></div>
    </div>

    <!-- Average Latency Card -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __("Average Response Latency") }}</h3>
                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($timeAnalysis['avg_latency_seconds'], 2) }}s</p>
                <p class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">{{ __("Time between recording and data arrival") }}</p>
            </div>
            <div class="p-4 bg-blue-100 dark:bg-blue-900/30 rounded-full">
                <i class="icon-clock text-4xl text-blue-600 dark:text-blue-400"></i>
            </div>
        </div>
    </div>

    <script>
    // Store chart instances globally
    window.__loadcellApexCharts = window.__loadcellApexCharts || {};

    function destroyChart(chartName) {
        if (window.__loadcellApexCharts[chartName]) {
            try {
                window.__loadcellApexCharts[chartName].destroy();
            } catch(e) {
                console.error('Error destroying chart:', chartName, e);
            }
        }
    }

    function renderAllCharts() {
        if (typeof ApexCharts === 'undefined') {
            console.error('ApexCharts is not loaded!');
            setTimeout(renderAllCharts, 100);
            return;
        }

        // Get fresh data from Livewire component
        var pressureBoxplotData = @json($pressureBoxplot);
        var qualityMetrics = @json($qualityMetrics);
        var timeAnalysis = @json($timeAnalysis);

            // Option 3: Pressure Bar Chart
            destroyChart('boxplot');
            var barChartOptions = {
                series: [{
                    name: 'Average Pressure',
                    data: Object.keys(pressureBoxplotData).map(function(sensor) {
                        return {
                            x: sensor,
                            y: pressureBoxplotData[sensor]
                        };
                    })
                }],
                chart: {
                    type: 'bar',
                    height: 400,
                    toolbar: { show: true }
                },
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        dataLabels: {
                            position: 'top'
                        },
                        colors: {
                            ranges: [{
                                from: 0,
                                to: 100,
                                color: '#3b82f6'
                            }]
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) {
                        return val.toFixed(2) + ' kPa';
                    },
                    offsetY: -20,
                    style: {
                        fontSize: '12px',
                        colors: ["#304758"]
                    }
                },
                title: {
                    text: 'Average Sensor Pressure Distribution',
                    align: 'left'
                },
                xaxis: {
                    title: { text: 'Sensor Position' },
                    labels: {
                        style: {
                            fontSize: '12px'
                        }
                    }
                },
                yaxis: {
                    title: { text: 'Average Pressure (kPa)' },
                    labels: {
                        formatter: function (val) {
                            return val.toFixed(2);
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val.toFixed(2) + ' kPa';
                        }
                    }
                },
                colors: ['#3b82f6']
            };
            window.__loadcellApexCharts.boxplot = new ApexCharts(document.querySelector("#pressureBoxplot"), barChartOptions);
            window.__loadcellApexCharts.boxplot.render();

            // Option 4: Standard/Not Standard Donut Chart
            destroyChart('passFail');
            var passFailData = qualityMetrics.pass_fail;
            var passFailOptions = {
                series: Object.values(passFailData),
                chart: {
                    type: 'donut',
                    height: 300
                },
                labels: Object.keys(passFailData).map(function(key) {
                    return key === 'std' ? 'Standard' : 'Not Standard';
                }),
                colors: ['#10b981', '#ef4444'],
                legend: {
                    position: 'bottom'
                },
                dataLabels: {
                    enabled: true,
                    formatter: function(val) {
                        return val.toFixed(1) + '%';
                    }
                }
            };
            window.__loadcellApexCharts.passFail = new ApexCharts(document.querySelector("#passFailChart"), passFailOptions);
            window.__loadcellApexCharts.passFail.render();

            // Option 4: Position Bar Chart
            destroyChart('position');
            var positionData = qualityMetrics.by_position;
            var positionOptions = {
                series: [{
                    name: 'Standard',
                    data: positionData.map(function(p) { return p.passed; })
                }, {
                    name: 'Not Standard',
                    data: positionData.map(function(p) { return p.failed; })
                }],
                chart: {
                    type: 'bar',
                    height: 300,
                    stacked: true
                },
                plotOptions: {
                    bar: {
                        horizontal: false
                    }
                },
                xaxis: {
                    categories: positionData.map(function(p) { return p.position; })
                },
                colors: ['#10b981', '#ef4444'],
                legend: {
                    position: 'top'
                },
                yaxis: {
                    title: { text: 'Number of Tests' }
                }
            };
            window.__loadcellApexCharts.position = new ApexCharts(document.querySelector("#positionChart"), positionOptions);
            window.__loadcellApexCharts.position.render();

            // Option 4: Machine Bar Chart
            destroyChart('machine');
            var machineData = qualityMetrics.by_machine;
            var machineOptions = {
                series: [{
                    name: 'Standard',
                    data: machineData.map(function(m) { return m.passed; })
                }, {
                    name: 'Not Standard',
                    data: machineData.map(function(m) { return m.failed; })
                }],
                chart: {
                    type: 'bar',
                    height: 350,
                    stacked: false
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        dataLabels: {
                            position: 'top'
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    offsetY: -20,
                    style: {
                        fontSize: '12px',
                        colors: ["#304758"]
                    }
                },
                xaxis: {
                    categories: machineData.map(function(m) { return 'Machine ' + m.machine; }),
                    title: { text: 'Machine' }
                },
                yaxis: {
                    title: { text: 'Number of Tests' }
                },
                colors: ['#10b981', '#ef4444'],
                legend: {
                    position: 'top'
                }
            };
            window.__loadcellApexCharts.machine = new ApexCharts(document.querySelector("#machineChart"), machineOptions);
            window.__loadcellApexCharts.machine.render();
    }

    // Initialize charts with delay to ensure DOM is ready
    function initCharts() {
        var allElementsExist = 
            document.getElementById('pressureBoxplot') &&
            document.getElementById('passFailChart') &&
            document.getElementById('positionChart') &&
            document.getElementById('machineChart');

        if (allElementsExist && typeof ApexCharts !== 'undefined') {
            renderAllCharts();
        } else {
            setTimeout(initCharts, 100);
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }

    // Refresh charts on Livewire updates
    document.addEventListener('livewire:init', function() {
        Livewire.hook('morph.updated', function() {
            setTimeout(renderAllCharts, 100);
        });
    });
    </script>
</div>
