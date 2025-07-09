<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\InsCtcMachine;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public array $stats = [];
    public array $chartOptions = [];

    public function mount()
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        $start = Carbon::now()->subDays(7);
        $end = Carbon::now();

        $machines = InsCtcMachine::orderBy('line')->get();
        foreach ($machines as $machine) {
            $avg = $machine->average_metrics_between($start, $end);
            $this->stats[] = [
                'line' => $machine->line,
                'mae' => $avg['mae_combined'],
                'ssd' => $avg['ssd_combined'],
            ];
        }

        $labels = array_map(fn($s) => 'Line ' . $s['line'], $this->stats);
        $maeData = array_column($this->stats, 'mae');
        $ssdData = array_column($this->stats, 'ssd');

        $this->chartOptions = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'MAE',
                        'data' => $maeData,
                        'backgroundColor' => 'rgba(59,130,246,0.6)',
                        'borderColor' => '#3B82F6',
                        'borderWidth' => 1,
                    ],
                    [
                        'label' => 'SSD',
                        'data' => $ssdData,
                        'backgroundColor' => 'rgba(239,68,68,0.6)',
                        'borderColor' => '#EF4444',
                        'borderWidth' => 1,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Performa Mesin (7 Hari Terakhir)'),
                    ],
                ],
            ],
        ];
    }
};


<div>
    <div class="h-80 mb-6" wire:ignore>
        <canvas id="machine-performance-chart"></canvas>
    </div>

    <div class="overflow-x-auto">
        <table class="table table-sm text-sm w-full">
            <thead>
                <tr class="text-xs uppercase text-neutral-500 border-b">
                    <th class="px-4 py-2">{{ __('Line') }}</th>
                    <th class="px-4 py-2">MAE</th>
                    <th class="px-4 py-2">SSD</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stats as $s)
                <tr class="border-b border-neutral-100 dark:border-neutral-700">
                    <td class="px-4 py-2 font-mono">{{ $s['line'] }}</td>
                    <td class="px-4 py-2">{{ number_format($s['mae'],2) }}</td>
                    <td class="px-4 py-2">{{ number_format($s['ssd'],2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@script
<script>
function renderMachinePerformance(){
    const ctx = document.getElementById('machine-performance-chart');
    if(!ctx) return;
    if(window.machinePerfChart) window.machinePerfChart.destroy();
    window.machinePerfChart = new Chart(ctx, @js($chartOptions));
}

document.addEventListener('livewire:navigated', renderMachinePerformance);
renderMachinePerformance();
</script>
@endscript

