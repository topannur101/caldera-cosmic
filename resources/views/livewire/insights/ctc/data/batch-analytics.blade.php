<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\InsCtcMetric;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public array $chartOptions = [];

    public function mount()
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        $start = Carbon::now()->subDays(7);
        $end = Carbon::now();

        $metrics = InsCtcMetric::selectRaw('DATE(created_at) as dt, AVG(t_mae) as mae, AVG(t_ssd) as ssd')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('dt')
            ->orderBy('dt')
            ->get();

        $labels = $metrics->pluck('dt')->map(fn($d) => Carbon::parse($d)->format('d M'))->toArray();
        $maeData = $metrics->pluck('mae')->toArray();
        $ssdData = $metrics->pluck('ssd')->toArray();

        $this->chartOptions = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'MAE',
                        'data' => $maeData,
                        'borderColor' => '#3B82F6',
                        'backgroundColor' => 'rgba(59,130,246,0.2)',
                        'tension' => 0.4,
                        'fill' => true,
                    ],
                    [
                        'label' => 'SSD',
                        'data' => $ssdData,
                        'borderColor' => '#F59E0B',
                        'backgroundColor' => 'rgba(245,158,11,0.2)',
                        'tension' => 0.4,
                        'fill' => true,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Tren MAE & SSD Mingguan'),
                    ],
                ],
            ],
        ];
    }
};
?>



<div>
    <div class="h-80" wire:ignore>
        <canvas id="batch-analytics-chart"></canvas>
    </div>
</div>

@script
<script>
function renderBatchAnalytics() {
    const ctx = document.getElementById('batch-analytics-chart');
    if (!ctx) return;
    if (window.batchAnalyticsChart) window.batchAnalyticsChart.destroy();
    window.batchAnalyticsChart = new Chart(ctx, @js($chartOptions));
}

document.addEventListener('livewire:navigated', renderBatchAnalytics);
renderBatchAnalytics();
</script>
@endscript

