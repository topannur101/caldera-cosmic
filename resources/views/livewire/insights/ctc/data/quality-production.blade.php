<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\InsCtcMachine;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public array $chartOptions = [];

    public function mount()
    {
        $start = Carbon::now()->subDays(30);
        $end = Carbon::now();

        $machines = InsCtcMachine::with(['ins_ctc_metrics' => function($q) use ($start,$end){
            $q->whereBetween('created_at', [$start,$end]);
        }])->orderBy('line')->get();

        $data = [];
        foreach($machines as $m){
            $count = $m->ins_ctc_metrics->count();
            if($count === 0) continue;
            $avgMae = round($m->ins_ctc_metrics->avg('t_mae'),2);
            $data[] = [ 'x' => $count, 'y' => $avgMae, 'label' => 'Line '.$m->line ];
        }

        $this->chartOptions = [
            'type' => 'scatter',
            'data' => [
                'datasets' => [
                    [
                        'label' => __('Quality vs Produksi'),
                        'data' => $data,
                        'backgroundColor' => '#D64550',
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'tooltip' => [
                        'callbacks' => [
                            'label' => 'function(ctx){return ctx.raw.label+": "+ctx.parsed.x+" batch, MAE "+ctx.parsed.y;}'
                        ]
                    ],
                    'title' => [
                        'display' => true,
                        'text' => __('Kualitas vs Produksi')
                    ]
                ],
                'scales' => [
                    'x' => [ 'title' => [ 'display' => true, 'text' => 'Batch' ]],
                    'y' => [ 'title' => [ 'display' => true, 'text' => 'MAE' ]]
                ]
            ]
        ];
    }
};


<div class="h-80" wire:ignore>
    <canvas id="quality-prod-chart"></canvas>
</div>

@script
<script>
function renderQualityProd(){
    const ctx = document.getElementById('quality-prod-chart');
    if(!ctx) return;
    if(window.qualityProdChart) window.qualityProdChart.destroy();
    window.qualityProdChart = new Chart(ctx, @js($chartOptions));
}

document.addEventListener('livewire:navigated', renderQualityProd);
renderQualityProd();
</script>
@endscript

