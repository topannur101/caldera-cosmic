<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\InsCtcMetric;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public array $chartOptions = [];

    public function mount()
    {
        $start = Carbon::now()->subDays(7);
        $end = Carbon::now();

        $data = InsCtcMetric::selectRaw('DATE(created_at) as dt, AVG(correction_uptime) as uptime, AVG(correction_rate) as rate')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('dt')
            ->orderBy('dt')
            ->get();

        $labels = $data->pluck('dt')->map(fn($d) => Carbon::parse($d)->format('d M'))->toArray();
        $uptime = $data->pluck('uptime')->toArray();
        $rate = $data->pluck('rate')->toArray();

        $this->chartOptions = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Uptime (%)'),
                        'data' => $uptime,
                        'borderColor' => '#10B981',
                        'backgroundColor' => 'rgba(16,185,129,0.2)',
                        'tension' => 0.4,
                        'fill' => true,
                    ],
                    [
                        'label' => __('Rate (%)'),
                        'data' => $rate,
                        'borderColor' => '#6366F1',
                        'backgroundColor' => 'rgba(99,102,241,0.2)',
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
                        'text' => __('Efisiensi Koreksi (7 Hari)'),
                    ],
                ],
            ],
        ];
    }
};


<div>
    <div class="h-80" wire:ignore>
        <canvas id="correction-eff-chart"></canvas>
    </div>
</div>

@script
<script>
function renderCorrection(){
    const ctx = document.getElementById('correction-eff-chart');
    if(!ctx) return;
    if(window.correctionChart) window.correctionChart.destroy();
    window.correctionChart = new Chart(ctx, @js($chartOptions));
}

document.addEventListener('livewire:navigated', renderCorrection);
renderCorrection();
</script>
@endscript

