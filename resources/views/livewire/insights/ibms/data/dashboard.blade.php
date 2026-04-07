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
            ->where('duration', '>=', '00:00:20') // Filter out records with duration less than 20 seconds
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
                'lt_5' => (int) $durationCounts->get('lt_5', 0),
                'min_5_10' => (int) $durationCounts->get('min_5_10', 0),
                'min_11_15' => (int) $durationCounts->get('min_11_15', 0),
                'min_15_20' => (int) $durationCounts->get('min_15_20', 0),
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
                ['label' => '< 5 minutes', 'value' => round(((int) $durationCounts->get('lt_5', 0) / $total) * 100, 1), 'color' => '#ef4444'],
                ['label' => '5 - 10 minutes', 'value' => round(((int) $durationCounts->get('min_5_10', 0) / $total) * 100, 1), 'color' => '#f97316'],
                ['label' => '11 - 15 minutes', 'value' => round(((int) $durationCounts->get('min_11_15', 0) / $total) * 100, 1), 'color' => '#facc15'],
                ['label' => '15 - 20 minutes', 'value' => round(((int) $durationCounts->get('min_15_20', 0) / $total) * 100, 1), 'color' => '#22c55e'],
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
        $minutes = $this->durationToMinutes($duration);

        if ($minutes < 5) {
            return 'lt_5';
        }

        if ($minutes <= 10) {
            return 'min_5_10';
        }

        if ($minutes <= 15) {
            return 'min_11_15';
        }

        if ($minutes <= 20) {
            return 'min_15_20';
        }

        return 'gt_20';
    }

    private function loadOnlineStats(Carbon $date): array
    {
        $device = InsIbmsDevice::active()->first();

        if (!$device) {
            return $this->getEmptyOnlineStats();
        }

        $project = Project::where('ip', $device->ip_address)->first();
        if (!$project) {
            return $this->getEmptyOnlineStats();
        }

        $workingHoursService = app(WorkingHoursService::class);
        $workingHours = $workingHoursService->getProjectWorkingHours($project->id);

        if (!empty($workingHours)) {
            $start = $date->copy()->setTime(Carbon::parse($workingHours[0]['start_time'])->hour, Carbon::parse($workingHours[0]['start_time'])->minute);
            $end = $date->copy()->setTime(Carbon::parse($workingHours[0]['end_time'])->hour, Carbon::parse($workingHours[0]['end_time'])->minute);
        } else {
            $defaultHours = config('bpm.working_hours');
            $start = $date->copy()->setTime($defaultHours['start'], 0);
            $end = $date->copy()->setTime($defaultHours['end'], 0);
        }

        $calculator = app(UptimeCalculatorService::class);
        $stats = $calculator->calculateStats($project->name, $start, $end);

        $formatter = app(DurationFormatterService::class);

        return [
            'online_percentage' => $stats['online_percentage'],
            'offline_percentage' => $stats['offline_percentage'],
            'timeout_percentage' => $stats['timeout_percentage'],
            'online_time' => $formatter->format($stats['online_duration']),
            'offline_time' => $formatter->format($stats['offline_duration']),
            'timeout_time' => $formatter->format($stats['timeout_duration']),
        ];
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
                <div id="onlineSystemMonitoringChart" class="h-[190px] mb-3"></div>
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
        <div class="col-span-4 bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
            <h2 class="text-center text-xl font-bold text-gray-800 dark:text-white">Evaluation Percentage</h2>
            @if ($hasData)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 px-2">
                    @foreach ($chartData as $index => $machine)
                        <div class="text-center mt-3">
                            <div class="text-center">
                                <p class="text-gray-800 dark:text-white text-xl font-bold leading-tight">{{ $machine['machine'] }}</p>
                                <div id="machinePieChart-{{ $index }}" class="h-[200px]"></div>
                            </div>
                            <!-- detail standard or not standard -->
                            <div class="text-gray-500 dark:text-white text-sm mt-2 gap-2 flex flex-col items-center">
                                <div class="flex items-center gap-1 px-3 py-1 rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    <p>&lt; 5 min: {{ $machine['lt_5'] }} batches</p>
                                </div>
                                <div class="flex items-center gap-1 px-3 py-1 rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                    <p>5 - 10 min: {{ $machine['min_5_10'] }} batches</p>
                                </div>
                                <div class="flex items-center gap-1 px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    <p>11 - 15 min: {{ $machine['min_11_15'] }} batches</p>
                                </div>
                                <div class="flex items-center gap-1 px-3 py-1 rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <p>15 - 20 min: {{ $machine['min_15_20'] }} batches</p>
                                </div>
                                <div class="flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    <p>&gt; 20 min: {{ $machine['gt_20'] }} batches</p>
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
    const onlineStats = @json($onlineStats);
    const isDarkMode = document.documentElement.classList.contains('dark');
    const textColor = isDarkMode ? '#e6edf3' : '#0f172a';

    const ibmsChartColors = ['#ef4444', '#f97316', '#facc15', '#22c55e', '#3b82f6'];
    const monitoringColors = ['#4ade80', '#9ca3af', '#f59e0b'];

    function renderOnlineSystemMonitoringChart() {
        if (typeof ApexCharts === 'undefined') {
            return;
        }

        const container = document.querySelector('#onlineSystemMonitoringChart');
        if (!container) {
            return;
        }
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

        if (container._apexChart) {
            container._apexChart.destroy();
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

        const container = document.querySelector('#batchApexChart');
        if (!container) {
            return;
        }

        if (container._apexChart) {
            container._apexChart.destroy();
        }

        const series = [
            { name: '< 5 minutes', data: chartData.map(d => d.lt_5) },
            { name: '5 - 10 minutes', data: chartData.map(d => d.min_5_10) },
            { name: '11 - 15 minutes', data: chartData.map(d => d.min_11_15) },
            { name: '15 - 20 minutes', data: chartData.map(d => d.min_15_20) },
            { name: '> 20 minutes', data: chartData.map(d => d.gt_20) },
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
                Number(machine.lt_5) || 0,
                Number(machine.min_5_10) || 0,
                Number(machine.min_11_15) || 0,
                Number(machine.min_15_20) || 0,
                Number(machine.gt_20) || 0,
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
                labels: ['< 5 minutes', '5 - 10 minutes', '11 - 15 minutes', '15 - 20 minutes', '> 20 minutes'],
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
        renderOnlineSystemMonitoringChart();

        if (!hasData) {
            return;
        }

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
