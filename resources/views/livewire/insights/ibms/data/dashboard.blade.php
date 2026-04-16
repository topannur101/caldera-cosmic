<?php

use Livewire\Volt\Component;
use App\Models\InsIbmsCount;
use App\Models\InsIbmsDevice;
use App\Models\Project;
use App\Services\DurationFormatterService;
use App\Services\UptimeCalculatorService;
use App\Services\WorkingHoursService;
use Carbon\Carbon;

new class extends Component {
    public $totalBatches = 0;
    public $averageBatchTime = 0;
    public $chartData = [];
    public $pieChartData = [];
    public $batchNotStandard = 0;
    public $batchStandard = 0;
    public $hasData = false;
    public $onlineStats = [];

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
            ->where('duration', '>=', '00:01:00') // Filter out records with duration less than 1 minute
            ->orderBy('data->name')
            ->latest()
            ->get();

        $this->totalBatches = $records->count();

        $totalDurationMinutes = 0;
        foreach ($records as $record) {
            $totalDurationMinutes += $this->durationToMinutes($record->duration);
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
            $durationCounts = $group->countBy(function ($item) {
                return $this->getDurationCategory($item->duration);
            });

            return [
                'machine' => 'Machine ' . $name,
                'le_10' => (int) $durationCounts->get('le_10', 0),
                'min_11_19' => (int) $durationCounts->get('min_11_19', 0),
                'exact_20' => (int) $durationCounts->get('exact_20', 0),
                'gt_20' => (int) $durationCounts->get('gt_20', 0),
                'total' => $group->count(),
            ];
        })->values()->toArray();

        $this->hasData = count($this->chartData) > 0;

        $this->batchNotStandard = $records->whereIn('data.status', ['too_early', 'to_early', 'to_late', 'too_late'])->count();
        $this->batchStandard = $records->where('data.status', 'on_time')->count();

        // Calculate pie chart percentages
        $total = $records->count();
        if ($total > 0) {
            $durationCounts = $records->countBy(function ($item) {
                return $this->getDurationCategory($item->duration);
            });

            $this->pieChartData = [
                ['label' => '<= 10 minutes', 'value' => round(((int) $durationCounts->get('le_10', 0) / $total) * 100, 1), 'color' => '#ef4444'],
                ['label' => '11 - 19 minutes', 'value' => round(((int) $durationCounts->get('min_11_19', 0) / $total) * 100, 1), 'color' => '#facc15'],
                ['label' => '20 minutes', 'value' => round(((int) $durationCounts->get('exact_20', 0) / $total) * 100, 1), 'color' => '#22c55e'],
                ['label' => '> 20 minutes', 'value' => round(((int) $durationCounts->get('gt_20', 0) / $total) * 100, 1), 'color' => '#3b82f6'],
            ];
        } else {
            $this->pieChartData = [];
        }

        $this->onlineStats = $this->loadOnlineStats(now());
    }

    private function durationToMinutes(?string $duration): float
    {
        if (!$duration) {
            return 0;
        }

        $parts = array_map('intval', explode(':', $duration));

        if (count($parts) === 3) {
            [$hours, $minutes, $seconds] = $parts;
            return ($hours * 60) + $minutes + ($seconds / 60);
        }

        if (count($parts) === 2) {
            [$minutes, $seconds] = $parts;
            return $minutes + ($seconds / 60);
        }

        return (float) ($parts[0] ?? 0);
    }

    private function getDurationCategory(?string $duration): string
    {
        $minutes = (int) floor($this->durationToMinutes($duration));

        if ($minutes <= 10) {
            return 'le_10';
        }

        if ($minutes <= 19) {
            return 'min_11_19';
        }

        if ($minutes === 20) {
            return 'exact_20';
        }

        return 'gt_20';
    }

    private function loadOnlineStats(Carbon $date): array
    {
        $activeIps = InsIbmsDevice::active()
            ->pluck('ip_address')
            ->filter()
            ->values();

        if ($activeIps->isEmpty()) {
            return $this->getEmptyOnlineStats();
        }

        $project = Project::whereIn('ip', $activeIps)->first();
        if (!$project) {
            return $this->getEmptyOnlineStats();
        }

        $workingHoursService = app(WorkingHoursService::class);
        $workingWindow = $workingHoursService->resolveProjectWorkingWindow($project->id, $date);

        if (!empty($workingWindow)) {
            $start = $workingWindow['start'];
            $end = $workingWindow['end'];
        } else {
            $defaultHours = config('bpm.working_hours');
            $start = $date->copy()->setTime((int) $defaultHours['start'], 0);
            $end = $date->copy()->setTime((int) $defaultHours['end'], 0);
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $calculator = app(UptimeCalculatorService::class);
        $stats = $calculator->calculateStats($project->name, $start, $end);

        $formatter = app(DurationFormatterService::class);

        return [
            'online_percentage' => $this->sanitizeChartValue($stats['online_percentage'] ?? 0),
            'offline_percentage' => $this->sanitizeChartValue($stats['offline_percentage'] ?? 0),
            'timeout_percentage' => $this->sanitizeChartValue($stats['timeout_percentage'] ?? 0),
            'online_time' => $formatter->format($stats['online_duration'] ?? 0),
            'offline_time' => $formatter->format($stats['offline_duration'] ?? 0),
            'timeout_time' => $formatter->format($stats['timeout_duration'] ?? 0),
        ];
    }

    private function sanitizeChartValue($value): float
    {
        if ($value === null || !is_numeric($value)) {
            return 0;
        }

        $float = (float) $value;

        return is_finite($float) ? round($float, 2) : 0;
    }

    private function getEmptyOnlineStats(): array
    {
        return [
            'online_percentage' => 0,
            'offline_percentage' => 0,
            'timeout_percentage' => 0,
            'online_time' => '0 seconds',
            'offline_time' => '0 seconds',
            'timeout_time' => '0 seconds',
        ];
    }
}; ?>
<div>
    <div class="p-6 min-h-screen" wire:init="loadData" wire:poll.40s="loadData">
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
                        <div id="batchApexChart" wire:ignore></div>
                    @else
                        <div class="h-[350px] flex items-center justify-center text-gray-500 dark:text-white">No Data Available</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-8">
            <div class="xl:col-span-3">
                <div class="w-full bg-white dark:bg-neutral-800 rounded-2xl shadow-sm p-6 border border-slate-200/70 dark:border-neutral-700">
                    <h1 class="text-center text-xl font-bold text-gray-800 dark:text-white mb-6">Online System Monitoring</h1>
                    <div id="onlineSystemMonitoringChart" class="h-[200px] mb-3" wire:ignore></div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-2 bg-green-50 dark:bg-green-900/20 rounded">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded-full bg-green-500"></div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Online</span>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-green-600 dark:text-green-400">{{ $onlineStats['online_percentage'] ?? 0 }}%</div>
                                <div class="text-xs text-gray-500">{{ $onlineStats['online_time'] ?? '0 seconds' }}</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-900/20 rounded">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded-full bg-gray-400"></div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Offline</span>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-600 dark:text-gray-400">{{ $onlineStats['offline_percentage'] ?? 0 }}%</div>
                                <div class="text-xs text-gray-500">{{ $onlineStats['offline_time'] ?? '0 seconds' }}</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-2 bg-orange-50 dark:bg-orange-900/20 rounded">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded-full bg-orange-500"></div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Timeout (RTO)</span>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-orange-600 dark:text-orange-400">{{ $onlineStats['timeout_percentage'] ?? 0 }}%</div>
                                <div class="text-xs text-gray-500">{{ $onlineStats['timeout_time'] ?? '0 seconds' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Donut Chart Section -->
            <div class="xl:col-span-9 overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex flex-col gap-4 border-b border-slate-200/70 px-2 py-2 dark:border-neutral-700 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="mt-1 text-xl font-bold text-slate-800 dark:text-white">Evaluation Percentage</h2>
                    </div>
                </div>
    
                @if ($hasData)
                    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($chartData as $index => $machine)
                            <div class="rounded-2xl border border-slate-200/70 bg-slate-50/80 p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md dark:border-neutral-700 dark:bg-neutral-900/40">
                                <div class="mb-4 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-lg font-semibold text-slate-800 dark:text-white">{{ $machine['machine'] }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-300">Distribution by cycle duration</p>
                                    </div>
                                </div>
    
                                <div class="rounded-2xl p-2 dark:bg-neutral-800/80">
                                    <div id="machinePieChart-{{ $index }}" class="h-[120px]" wire:ignore></div>
                                </div>
    
                                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div class="flex items-center justify-between rounded-xl bg-red-50 px-3 py-2 text-red-700 dark:bg-red-900/20 dark:text-red-200">
                                        <span class="flex items-center gap-2 text-xs font-medium"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>&le; 10 min</span>
                                        <span class="text-xs font-semibold">{{ $machine['le_10'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between rounded-xl bg-yellow-50 px-3 py-2 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-200">
                                        <span class="flex items-center gap-2 text-xs font-medium"><span class="h-2.5 w-2.5 rounded-full bg-yellow-400"></span>11 - 19 min</span>
                                        <span class="text-xs font-semibold">{{ $machine['min_11_19'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between rounded-xl bg-green-50 px-3 py-2 text-green-700 dark:bg-green-900/20 dark:text-green-200">
                                        <span class="flex items-center gap-2 text-xs font-medium"><span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>20 min</span>
                                        <span class="text-xs font-semibold">{{ $machine['exact_20'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between rounded-xl bg-blue-50 px-3 py-2 text-blue-700 dark:bg-blue-900/20 dark:text-blue-200">
                                        <span class="flex items-center gap-2 text-xs font-medium"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>&gt; 20 min</span>
                                        <span class="text-xs font-semibold">{{ $machine['gt_20'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex h-[370px] items-center justify-center px-6 text-center text-gray-500 dark:text-white">
                        <div>
                            <p class="text-base font-semibold text-slate-700 dark:text-slate-200">No data available yet</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This section will update automatically once batch records are collected.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@script
<script>
    (() => {
        const hasData = @js($hasData);
        const chartData = @js($chartData);
        const onlineStats = @js($onlineStats);
        const ibmsChartColors = ['#ef4444', '#facc15', '#22c55e', '#3b82f6'];
        const monitoringColors = ['#4ade80', '#9ca3af', '#f59e0b'];
        const componentRoot = document.currentScript?.closest('[wire\\:id]') || document;

        const findInComponent = (selector) => {
            return componentRoot.querySelector(selector);
        };

        const getThemeConfig = () => {
            const isDarkMode = document.documentElement.classList.contains('dark');

            return {
                isDarkMode,
                textColor: isDarkMode ? '#e6edf3' : '#0f172a',
                gridColor: isDarkMode ? '#374151' : '#e5e7eb',
                strokeColor: isDarkMode ? '#1f2937' : '#ffffff',
                mutedTextColor: isDarkMode ? '#cbd5e1' : '#64748b',
            };
        };

        const showEmptyState = (container, message) => {
            if (!container) {
                return;
            }

            if (container._apexChart) {
                container._apexChart.destroy();
                container._apexChart = null;
            }

            container.innerHTML = `<div class="flex h-full items-center justify-center text-sm font-medium text-slate-500 dark:text-slate-400">${message}</div>`;
        };

        function renderOnlineSystemMonitoringChart() {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const container = findInComponent('#onlineSystemMonitoringChart');
            if (!container) {
                return;
            }

            const { textColor } = getThemeConfig();
            const monitoringSeries = [
                {
                    label: 'Online',
                    value: Number(onlineStats.online_percentage) || 0,
                    durationLabel: onlineStats.online_time || '0 seconds',
                    color: monitoringColors[0]
                },
                {
                    label: 'Offline',
                    value: Number(onlineStats.offline_percentage) || 0,
                    durationLabel: onlineStats.offline_time || '0 seconds',
                    color: monitoringColors[1]
                },
                {
                    label: 'Timeout (RTO)',
                    value: Number(onlineStats.timeout_percentage) || 0,
                    durationLabel: onlineStats.timeout_time || '0 seconds',
                    color: monitoringColors[2]
                },
            ];

            const total = monitoringSeries.reduce((sum, item) => sum + item.value, 0);
            if (total <= 0) {
                showEmptyState(container, 'No monitoring data');
                return;
            }

            if (container._apexChart) {
                container._apexChart.destroy();
            }

            container.innerHTML = '';

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
                dataLabels: { enabled: true },
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
                            return value.toFixed(2) + '% - ' + monitoringSeries[seriesIndex].durationLabel;
                        }
                    }
                }
            };

            container._apexChart = new ApexCharts(container, donutOptions);
            container._apexChart.render();
        }

        function renderBatchChart() {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const container = findInComponent('#batchApexChart');
            if (!container) {
                return;
            }

            const { isDarkMode, textColor, gridColor } = getThemeConfig();

            if (container._apexChart) {
                container._apexChart.destroy();
            }

            container.innerHTML = '';

            const series = [
                { name: '<= 10 minutes', data: chartData.map((d) => Number(d.le_10) || 0) },
                { name: '11 - 19 minutes', data: chartData.map((d) => Number(d.min_11_19) || 0) },
                { name: '20 minutes', data: chartData.map((d) => Number(d.exact_20) || 0) },
                { name: '> 20 minutes', data: chartData.map((d) => Number(d.gt_20) || 0) },
            ];

            const totalBars = series.reduce((sum, item) => {
                return sum + item.data.reduce((inner, val) => inner + val, 0);
            }, 0);

            if (totalBars <= 0) {
                showEmptyState(container, 'No batch data');
                return;
            }

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
                    borderColor: gridColor
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
                stroke: { width: 1, colors: ['#ffffff'] },
                xaxis: {
                    categories: chartData.map((d) => d.machine),
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

            const { isDarkMode, textColor, strokeColor, mutedTextColor } = getThemeConfig();

            chartData.forEach((machine, index) => {
                const container = findInComponent('#machinePieChart-' + index);
                if (!container) {
                    return;
                }

                if (container._apexChart) {
                    container._apexChart.destroy();
                }

                const series = [
                    Number(machine.le_10) || 0,
                    Number(machine.min_11_19) || 0,
                    Number(machine.exact_20) || 0,
                    Number(machine.gt_20) || 0,
                ];

                const total = series.reduce((sum, value) => sum + value, 0);
                if (total <= 0) {
                    showEmptyState(container, 'No batch data');
                    return;
                }

                container.innerHTML = '';

                const options = {
                    series,
                    chart: {
                        type: 'donut',
                        height: 350,
                        background: 'transparent',
                        animations: { enabled: true, speed: 350 },
                        toolbar: { show: false },
                        foreColor: textColor
                    },
                    labels: ['<= 10 minutes', '11 - 19 minutes', '20 minutes', '> 20 minutes'],
                    colors: ibmsChartColors,
                    legend: { show: false },
                    stroke: { width: 4, colors: [strokeColor] },
                    states: {
                        hover: {
                            filter: {
                                type: 'lighten',
                                value: 0.06
                            }
                        }
                    },
                    plotOptions: {
                        pie: {
                            expandOnClick: false,
                            donut: {
                                size: '70%',
                                labels: {
                                    show: true,
                                    name: {
                                        show: true,
                                        offsetY: 16,
                                        fontSize: '12px',
                                        color: mutedTextColor
                                    },
                                    value: {
                                        show: true,
                                        offsetY: -12,
                                        fontSize: '20px',
                                        fontWeight: 700,
                                        color: textColor,
                                        formatter: function (val) {
                                            return Number(val).toFixed(1) + '%';
                                        }
                                    },
                                }
                            }
                        }
                    },
                    dataLabels: {
                        enabled: true,
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
            try {
                renderOnlineSystemMonitoringChart();
            } catch (error) {
                console.error('Failed to render online monitoring chart', error);
            }

            if (!hasData) {
                return;
            }

            try {
                renderBatchChart();
            } catch (error) {
                console.error('Failed to render batch chart', error);
            }

            try {
                renderMachinePieCharts();
            } catch (error) {
                console.error('Failed to render machine pie charts', error);
            }
        }

        function ensureApexChartsAndRender(retries = 20) {
            if (typeof ApexCharts !== 'undefined') {
                window.requestAnimationFrame(renderAllCharts);
                return;
            }

            if (retries > 0) {
                window.setTimeout(() => ensureApexChartsAndRender(retries - 1), 150);
            }
        }

        ensureApexChartsAndRender();
    })();
</script>
@endscript
