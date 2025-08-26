<?php

use App\Models\InsDwpCount;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount()
    {
        // Set default date range to current week
        $this->dateFrom = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->dateTo = Carbon::now()->endOfWeek()->format('Y-m-d');
    }

    public function getLineChartData()
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $data = InsDwpCount::summaryBetween($from, $to);

        return [
            'labels' => collect($data)->pluck('line')->toArray(),
            'datasets' => [
                [
                    'label' => 'Total Incremental',
                    'data' => collect($data)->pluck('total_incremental')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                ]
            ]
        ];
    }

    public function getDailyChartData()
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        // Get all lines
        $lines = InsDwpCount::distinct('line')->pluck('line');
        $dates = [];
        $datasets = [];

        // Generate all dates in range
        $current = $from->copy();
        while ($current <= $to) {
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
                $dailyCount = InsDwpCount::where('line', $line)
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

        return [
            'labels' => $dates,
            'datasets' => $datasets
        ];
    }

    public function refreshData()
    {
        $this->dispatch('refresh-charts');
    }
};

?>

<div>
<div class="space-y-6">
    <!-- Date Range Filter -->
    <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                    {{ __("Date From") }}
                </label>
                <input type="date" wire:model="dateFrom" class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                    {{ __("Date To") }}
                </label>
                <input type="date" wire:model="dateTo" class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
            </div>
            
            <div>
                <button wire:click="refreshData" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                    {{ __("Update Charts") }}
                </button>
            </div>
        </div>
    </div>

    <!-- Line Summary Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">
            {{ __("Summary by Line") }}
        </h3>
        <div class="h-96">
            <canvas id="lineChart" wire:ignore></canvas>
        </div>
    </div>

    <!-- Daily Trend Chart -->
    <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">
            {{ __("Daily Trends") }}
        </h3>
        <div class="h-96">
            <canvas id="dailyChart" wire:ignore></canvas>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('livewire:initialized', function() {
        let lineChart, dailyChart;

        function initCharts() {
            // Line Chart (Bar Chart)
            const lineCtx = document.getElementById('lineChart').getContext('2d');
            const lineData = @json($this->getLineChartData());
            
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
            const dailyCtx = document.getElementById('dailyChart').getContext('2d');
            const dailyData = @json($this->getDailyChartData());
            
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

        initCharts();

        // Listen for refresh event
        Livewire.on('refresh-charts', function() {
            setTimeout(initCharts, 100);
        });
    });
</script>
@endpush
</div>
</div>