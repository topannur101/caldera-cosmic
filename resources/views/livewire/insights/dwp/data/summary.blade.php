<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\InsDwpCount;
use App\Models\InsDwpDevice;
use Carbon\Carbon;
use App\Traits\HasDateRangeFilter;

new #[Layout("layouts.app")] class extends Component {
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public $device_id;

    #[Url]
    public string $line = "";

    public array $devices = [];
    public array $summaryStats = [];
    public array $lineChartData = [];
    public array $dailyChartData = [];

    public function mount()
    {
        if (! $this->start_at || ! $this->end_at) {
            $this->setThisWeek();
        }

        $this->devices = InsDwpDevice::orderBy("name")
            ->get()
            ->pluck("name", "id")
            ->toArray();
    }

    private function getCountsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsDwpCount::whereBetween("created_at", [$start, $end]);

        if ($this->device_id) {
            $device = InsDwpDevice::find($this->device_id);
            if ($device) {
                $deviceLines = $device->getLines();
                $query->whereIn("line", $deviceLines);
            }
        }

        if ($this->line) {
            $query->where("line", "like", "%" . strtoupper(trim($this->line)) . "%");
        }

        return $query;
    }

    private function calculateSummaryStats()
    {
        $counts = $this->getCountsQuery()->get();
        $lines = $counts->pluck('line')->unique();
        
        $this->summaryStats = [
            "total_lines" => $lines->count(),
            "total_records" => $counts->count(),
            "total_incremental" => $counts->sum('incremental'),
            "avg_incremental_per_line" => $lines->count() > 0 ? round($counts->sum('incremental') / $lines->count(), 2) : 0,
        ];
    }

    private function generateLineChartData()
    {
        $counts = $this->getCountsQuery()->get();
        $lineSummary = $counts->groupBy('line')->map(function ($lineCounts) {
            return $lineCounts->sum('incremental');
        });

        $this->lineChartData = [
            'labels' => $lineSummary->keys()->toArray(),
            'datasets' => [
                [
                    'label' => 'Total Incremental',
                    'data' => $lineSummary->values()->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                ]
            ]
        ];
    }

    private function generateDailyChartData()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at);

        // Get filtered lines based on current filters
        $lines = $this->getCountsQuery()->distinct('line')->pluck('line');
        $dates = [];
        $datasets = [];

        // Generate all dates in range
        $current = $start->copy();
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        // Color palette for different lines
        $colors = [
            'rgba(59, 130, 246, 0.6)',   // Blue
            'rgba(16, 185, 129, 0.6)',   // Green  
            'rgba(245, 101, 101, 0.6)',  // Red
            'rgba(251, 191, 36, 0.6)',   // Yellow
            'rgba(139, 92, 246, 0.6)',   // Purple
            'rgba(236, 72, 153, 0.6)',   // Pink
        ];

        foreach ($lines as $index => $line) {
            $lineData = [];
            
            foreach ($dates as $date) {
                $dailyCount = $this->getCountsQuery()
                    ->where('line', $line)
                    ->whereDate('created_at', $date)
                    ->sum('incremental');
                
                $lineData[] = $dailyCount;
            }

            $color = $colors[$index % count($colors)];
            $borderColor = str_replace('0.6', '1', $color);

            $datasets[] = [
                'label' => $line,
                'data' => $lineData,
                'backgroundColor' => $color,
                'borderColor' => $borderColor,
                'borderWidth' => 2,
                'tension' => 0.4,
            ];
        }

        $this->dailyChartData = [
            'labels' => $dates,
            'datasets' => $datasets
        ];
    }

    #[On("updated")]
    public function update()
    {
        $this->calculateSummaryStats();
        $this->generateLineChartData();
        $this->generateDailyChartData();
        $this->generateCharts();
    }

    private function generateCharts()
    {
        $this->dispatch('refresh-charts', [
            'lineChartData' => $this->lineChartData,
            'dailyChartData' => $this->dailyChartData,
        ]);
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
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Device") }}</label>
                    <x-select wire:model.live="device_id" class="w-full lg:w-32">
                        <option value=""></option>
                        @foreach ($devices as $id => $deviceName)
                            <option value="{{ $id }}">{{ $deviceName }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
                    <x-text-input wire:model.live.debounce.500ms="line" class="w-full lg:w-32" />
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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Total Lines") }}</div>
            <div class="text-2xl font-bold">{{ number_format($summaryStats["total_lines"] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Total Records") }}</div>
            <div class="text-2xl font-bold">{{ number_format($summaryStats["total_records"] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Total Incremental") }}</div>
            <div class="text-2xl font-bold">{{ number_format($summaryStats["total_incremental"] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-sm text-neutral-500 mb-1">{{ __("Avg per Line") }}</div>
            <div class="text-2xl font-bold">{{ number_format($summaryStats["avg_incremental_per_line"] ?? 0, 2) }}</div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Line Summary Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">
                {{ __("Summary by Line") }}
            </h3>
            <div class="h-96">
                <canvas id="lineChart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Daily Trend Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">
                {{ __("Daily Trends") }}
            </h3>
            <div class="h-96">
                <canvas id="dailyChart" wire:ignore></canvas>
            </div>
        </div>
    </div>
</div>

@script
    <script>
        let lineChart, dailyChart;

        function initCharts(lineData, dailyData) {
            // Line Chart (Bar Chart)
            const lineCtx = document.getElementById('lineChart');
            if (lineChart) lineChart.destroy();
            
            lineChart = new Chart(lineCtx, {
                type: 'bar',
                data: lineData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Daily Chart (Line Chart)
            const dailyCtx = document.getElementById('dailyChart');
            if (dailyChart) dailyChart.destroy();
            
            dailyChart = new Chart(dailyCtx, {
                type: 'line',
                data: dailyData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // Listen for refresh event
        $wire.on('refresh-charts', function(event) {
            const data = event[0] || event;
            initCharts(data.lineChartData, data.dailyChartData);
        });

        // Initial load
        $wire.$dispatch('updated');
    </script>
@endscript
</div>