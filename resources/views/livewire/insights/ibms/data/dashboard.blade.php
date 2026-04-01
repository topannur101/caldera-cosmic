<?php

use Livewire\Volt\Component;
use App\Models\InsIbmsCount;
use App\Models\InsIbmsDevice;

new class extends Component {
    public $totalBatches = 0;
    public $averageBatchTime = 0;
    public $chartData = [];
    public $pieChartData = [];
    public $batchNotStandard = 0;
    public $batchStandard = 0;
    public $hasData = false;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        // Get all machine names from active devices config
        $allMachineNames = InsIbmsDevice::active()->get()
            ->flatMap(function ($device) {
                $machines = data_get($device->config, 'list_machine', []);
                return collect($machines)->pluck('name')->filter();
            })
            ->unique()
            ->sort()
            ->values();

        $records = InsIbmsCount::whereDate('created_at', now()->toDateString())
            ->orderBy('data->name')
            ->latest()
            ->get();

        $this->totalBatches = $records->count();

        $totalDurationMinutes = 0;
        foreach ($records as $record) {
            if ($record->duration) {
                $parts = explode(':', $record->duration);
                $totalDurationMinutes += ($parts[0] ?? 0) * 60 + ($parts[1] ?? 0);
            }
        }
        $this->averageBatchTime = $records->count() > 0 ? round($totalDurationMinutes / $records->count()) : 0;

        // Group records by machine name
        $grouped = $records->groupBy(function ($item) {
            return $item->data['name'] ?? 'Unknown';
        });

        // Build chart data for all machines (from config), filling zeros for machines without data
        $machineNames = $allMachineNames->isNotEmpty() ? $allMachineNames : $grouped->keys()->sort()->values();

        $this->chartData = $machineNames->map(function ($name) use ($grouped) {
            $group = $grouped->get($name, collect());
            $statusCounts = $group->countBy(function ($item) {
                return data_get($item, 'data.status');
            });

            return [
                'machine' => 'Machine ' . $name,
                'too_early' => (int) $statusCounts->get('too_early', 0) + (int) $statusCounts->get('to_early', 0),
                'on_time' => (int) $statusCounts->get('on_time', 0),
                'too_late' => (int) $statusCounts->get('to_late', 0) + (int) $statusCounts->get('too_late', 0),
                'total' => $group->count(),
            ];
        })->values()->toArray();

        $this->hasData = count($this->chartData) > 0;

        $this->batchNotStandard = $records->whereIn('data.status', ['too_early', 'to_early', 'to_late', 'too_late'])->count();
        $this->batchStandard = $records->where('data.status', 'on_time')->count();

        // Calculate pie chart percentages
        $total = $records->count();
        if ($total > 0) {
            $statusCounts = $records->countBy(function ($item) {
                return data_get($item, 'data.status');
            });

            $tooEarlyCount = (int) $statusCounts->get('too_early', 0) + (int) $statusCounts->get('to_early', 0);
            $onTimeCount = (int) $statusCounts->get('on_time', 0);
            $toLateCount = (int) $statusCounts->get('to_late', 0) + (int) $statusCounts->get('too_late', 0);

            $tooEarlyPct = ($tooEarlyCount / $total) * 100;
            $onTimePct = ($onTimeCount / $total) * 100;
            $toLatePct = ($toLateCount / $total) * 100;

            $this->pieChartData = [
                ['label' => 'Too Early (<20 minutes)', 'value' => round($tooEarlyPct, 1), 'color' => '#ef4444'],
                ['label' => 'On Time (20 minutes)', 'value' => round($onTimePct, 1), 'color' => '#22c55e'],
            ];
        } else {
            $this->pieChartData = [];
        }
    }
}; ?>

<div class="p-6 min-h-screen">
    <div class="grid grid-cols-4 gap-4 mb-4">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-8">
            <h3 class="text-gray-600 dark:text-white text-lg font-semibold mb-4">Total Batch</h3>
            <p class="text-5xl font-bold text-green-600 dark:text-white">{{ $totalBatches }}</p>
            <p class="text-gray-500 dark:text-white text-sm mt-2">batch</p>
        </div>

        <!-- Average Batch Time -->
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-8">
            <h3 class="text-gray-600 dark:text-white text-lg font-semibold mb-4">Average Batch Time</h3>
            <p class="text-5xl font-bold text-green-600 dark:text-white">{{ $averageBatchTime }}</p>
            <p class="text-gray-500 dark:text-white text-sm mt-2">minutes / batch</p>
        </div>

        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-8">
            <h3 class="text-gray-600 dark:text-white text-lg font-semibold mb-4">Batch Not Standard</h3>
            <p class="text-5xl font-bold text-red-600 dark:text-white">{{ $batchNotStandard ?? 0 }}</p>
            <p class="text-gray-500 dark:text-white text-sm mt-2">batch</p>
        </div>

        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-8">
            <h3 class="text-gray-600 dark:text-white text-lg font-semibold mb-4">Batch Standard</h3>
            <p class="text-5xl font-bold text-green-600 dark:text-white">{{ $batchStandard ?? 0 }}</p>
            <p class="text-gray-500 dark:text-white text-sm mt-2">batch</p>
        </div>
    </div>
    <!-- Top Metrics -->
    <div class="grid grid-cols gap-4 mb-2">
        <div>
             <!-- Bar Chart Section -->
            <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6 mb-4">
                <h2 class="text-xl font-bold text-neutral-800 dark:text-white mb-6">Total Batch per Machine</h2>
                @if ($hasData)
                    <div id="batchApexChart"></div>
                @else
                    <div class="h-[350px] flex items-center justify-center text-gray-500 dark:text-white">No Data Available</div>
                @endif
            </div>
        </div>
    </div>
    <div class="grid grid-cols-6 gap-4 mb-8">
        <div class="col-span-2">
            <div class="w-full bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
                <h1 class="text-center text-xl font-bold text-gray-800 dark:text-white mb-6">Online System Monitoring</h1>
                @if ($hasData)
                    <div id="onlineSystemMonitoringChart"></div>
                @else
                    <div class="h-[370px] flex items-center justify-center text-gray-500 dark:text-white">No Data Available</div>
                @endif
            </div>
        </div>
        <!-- Donut Chart Section -->
        <div class="col-span-4 bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
            <h2 class="text-center text-xl font-bold text-gray-800 dark:text-white mb-6">Evaluation Percentage</h2>
            @if ($hasData)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 px-4">
                    @foreach ($chartData as $index => $machine)
                        <div class="text-center mt-3">
                            <div class="text-center mt-3">
                                <p class="text-gray-800 dark:text-white text-xl font-bold leading-tight mb-5">{{ $machine['machine'] }}</p>
                                <div id="machinePieChart-{{ $index }}" class="h-[200px]"></div>
                            </div>
                            <!-- detail standard or not standard -->
                            <div class="text-gray-500 dark:text-white text-sm mt-2 gap-2 flex flex-col items-center mt-4">
                                <div class="flex items-center gap-1 px-3 py-1 rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    <p>Too Early: {{ $machine['too_early'] }} batches</p>
                                </div>
                                <div class="flex items-center gap-1 px-3 py-1 rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <p>On Time: {{ $machine['on_time'] }} batches</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="h-[370px] flex items-center justify-center text-gray-500 dark:text-white">No Data Available</div>
            @endif
        </div>
    </div>
</div>

@script
<script>
    const hasData = @json($hasData);
    const chartData = {!! json_encode($chartData) !!};
    const isDarkMode = document.documentElement.classList.contains('dark');
    const textColor = isDarkMode ? '#e6edf3' : '#0f172a';

    const ibmsChartColors = ['#ef4444', '#22c55e', '#f59e0b'];
    const monitoringColors = ['#4ade80', '#9ca3af', '#f59e0b'];
    function formatDuration(totalSeconds) {
        const safeSeconds = Math.max(0, Number(totalSeconds) || 0);
        const hours = Math.floor(safeSeconds / 3600);
        const minutes = Math.floor((safeSeconds % 3600) / 60);
        const seconds = safeSeconds % 60;

        if (hours > 0) {
            return `${hours} hours ${minutes} minutes ${seconds} seconds`;
        }

        if (minutes > 0) {
            return `${minutes} minutes ${seconds} seconds`;
        }

        return `${seconds} seconds`;
    }

    function renderOnlineSystemMonitoringChart() {
        if (typeof ApexCharts === 'undefined') {
            return;
        }

        const container = document.querySelector('#onlineSystemMonitoringChart');
        if (!container) {
            return;
        }

        // Keep values aligned with reference card design.
        const monitoringSeries = [
            { label: 'Online', value: 95.85, duration: 7 * 3600 + 58 * 60 + 47, color: monitoringColors[0] },
            { label: 'Offline', value: 0, duration: 0, color: monitoringColors[1] },
            { label: 'Timeout (RTO)', value: 4.15, duration: 20 * 60 + 45, color: monitoringColors[2] },
        ];

        container.innerHTML = `
            <div class="h-[190px]" id="onlineSystemMonitoringDonut"></div>
            <div class="space-y-3 mt-1">
                ${monitoringSeries.map((item) => `
                    <div class="${isDarkMode ? 'bg-neutral-700/30' : 'bg-gray-100'} rounded-md px-3 py-2 flex items-center justify-between">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-4 h-4 rounded-full shrink-0" style="background-color: ${item.color}"></span>
                            <span class="text-base font-semibold ${isDarkMode ? 'text-neutral-100' : 'text-gray-700'}">${item.label}</span>
                        </div>
                        <div class="text-right">
                            <p class="text-xl font-bold leading-none" style="color: ${item.color}">${item.value}%</p>
                            <p class="text-sm ${isDarkMode ? 'text-neutral-300' : 'text-gray-500'}">${formatDuration(item.duration)}</p>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        const donutContainer = container.querySelector('#onlineSystemMonitoringDonut');
        if (!donutContainer) {
            return;
        }

        if (donutContainer._apexChart) {
            donutContainer._apexChart.destroy();
        }

        const donutOptions = {
            series: monitoringSeries.map((item) => item.value),
            chart: {
                type: 'donut',
                height: 190,
                background: 'transparent',
                toolbar: { show: false },
                animations: { enabled: true, speed: 350 },
                foreColor: textColor
            },
            labels: monitoringSeries.map((item) => item.label),
            colors: monitoringSeries.map((item) => item.color),
            legend: { show: false },
            stroke: { width: 0 },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '58%'
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (value, { seriesIndex }) {
                        return value.toFixed(2) + '% - ' + formatDuration(monitoringSeries[seriesIndex].duration);
                    }
                }
            }
        };

        donutContainer._apexChart = new ApexCharts(donutContainer, donutOptions);
        donutContainer._apexChart.render();
    }


    function renderBatchChart() {
        if (typeof ApexCharts === 'undefined') {
            return;
        }

        const container = document.querySelector('#batchApexChart');
        if (!container) {
            return;
        }

        if (container._apexChart) {
            container._apexChart.destroy();
        }

        const series = [
            { name: 'Too Early (<20 minutes)', data: chartData.map(d => d.too_early) },
            { name: 'On Time (20 minutes)', data: chartData.map(d => d.on_time) },
        ];

        const options = {
            series,
            chart: {
                type: 'bar',
                height: 350,
                stacked: true,
                background: 'transparent',
                toolbar: { show: false },
                animations: { enabled: true, speed: 350 },
                fontFamily: 'inherit',
                foreColor: textColor
            },
            theme: {
                mode: isDarkMode ? 'dark' : 'light'
            },
            grid: {
                borderColor: isDarkMode ? '#374151' : '#e5e7eb'
            },
            legend: {
                labels: {
                    colors: textColor
                },
                position: 'top',
                horizontalAlign: 'left'
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '60%',
                    dataLabels: {
                        total: {
                            enabled: true,
                            style: { fontSize: '13px', fontWeight: 900 }
                        }
                    }
                }
            },
            dataLabels: {
                enabled: true,
                style: { colors: ['#ffffff'] },
                formatter: function (val) { return val > 0 ? val : ''; }
            },
            stroke: { width: 1, colors: ['#fff'] },
            xaxis: {
                categories: chartData.map(d => d.machine),
                title: { text: 'Batch Count', style: { color: textColor } },
                labels: { style: { colors: textColor } }
            },
            yaxis: {
                title: { text: 'Machine', style: { color: textColor } },
                labels: { style: { colors: textColor } }
            },
            tooltip: {
                y: { formatter: function (val) { return val + ' batches'; } }
            },
            colors: ibmsChartColors
        };

        container._apexChart = new ApexCharts(container, options);
        container._apexChart.render();
    }

    function renderMachinePieCharts() {
        if (typeof ApexCharts === 'undefined') {
            return;
        }

        chartData.forEach((machine, index) => {
            const container = document.querySelector('#machinePieChart-' + index);
            if (!container) {
                return;
            }

            if (container._apexChart) {
                container._apexChart.destroy();
            }

            const series = [
                Number(machine.too_early) || 0,
                Number(machine.on_time) || 0,
                Number(machine.too_late) || 0,
            ];

            const total = series.reduce((sum, value) => sum + value, 0);
            if (total <= 0) {
                return;
            }

            const options = {
                series,
                chart: {
                    type: 'donut',
                    height: 200,
                    background: 'transparent',
                    animations: { enabled: true, speed: 350 },
                    toolbar: { show: false },
                    foreColor: textColor
                },
                labels: ['Too Early (<15 minutes)', 'On Time (15 minutes)'],
                colors: ibmsChartColors,
                legend: { show: false },
                stroke: { width: 0 },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '58%'
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    style: {
                        colors: ['#ffffff'],
                        fontWeight: 700
                    },
                    formatter: function (val) {
                        return val >= 3 ? val.toFixed(1) + '%' : Math.round(val) + '%';
                    },
                    dropShadow: { enabled: false }
                },
                tooltip: {
                    y: {
                        formatter: function (value) {
                            const percent = total ? (value / total) * 100 : 0;
                            return value + ' batches (' + percent.toFixed(1) + '%)';
                        }
                    }
                }
            };

            container._apexChart = new ApexCharts(container, options);
            container._apexChart.render();
        });
    }

    function renderAllCharts() {
        if (!hasData) {
            return;
        }

        renderOnlineSystemMonitoringChart();
        renderBatchChart();
        renderMachinePieCharts();
    }

    function ensureApexChartsAndRender() {
        if (typeof ApexCharts !== 'undefined') {
            renderAllCharts();
        }
    }

    ensureApexChartsAndRender();
</script>
@endscript
