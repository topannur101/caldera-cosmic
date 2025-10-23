<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\InsDwpCount;
use Carbon\Carbon;

new class extends Component {
    public int $id = 0;
    public array $detail = [];

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public string $line = "g5";

    #[Url]
    public string $mechine = "";

    #[Url]
    public ?int $device_id = null;

    #[Url]
    public string $position = "L";

    public function mount()
    {
        if (!$this->start_at) {
            $this->start_at = now()->subHours(24)->format('Y-m-d');
        }
        if (!$this->end_at) {
            $this->end_at = now()->format('Y-m-d');
        }
    }

    #[On("pressure-detail-load")]
    public function loadPresureDetail($id)
    {
        $this->id = $id;
        $data = InsDwpCount::find($id);
        if ($data) {
            $this->detail = $data->toArray();
            $this->mechine = $data->mechine;
            $this->position = $data->position;
            $this->loadChartData();
        }
    }

    public function updatedStartAt() { $this->loadChartData(); }
    public function updatedEndAt() { $this->loadChartData(); }
    public function updatedMechine() { $this->loadChartData(); }
    public function updatedPosition() { $this->loadChartData(); }

    private function loadChartData()
    {
        if (empty($this->mechine)) return;

        $start = Carbon::parse($this->start_at)->startOfDay();
        $end = Carbon::parse($this->end_at)->endOfDay();

        $records = InsDwpCount::select('created_at', 'pv')
            ->where('mechine', $this->mechine)
            ->where('position', $this->position)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'asc')
            ->get();

        $labels = [];
        $toeHeelValues = [];
        $sideValues = [];

        foreach ($records as $record) {
            $pv = json_decode($record->pv, true);
            $toeHeel = is_array($pv) && isset($pv[0]) && is_numeric($pv[0]) ? (float)$pv[0] : 0;
            $side    = is_array($pv) && isset($pv[1]) && is_numeric($pv[1]) ? (float)$pv[1] : 0;

            $labels[] = $record->created_at->toISOString();
            $toeHeelValues[] = $toeHeel;
            $sideValues[] = $side;
        }

        $this->dispatch('update-pressure-chart', [
            'labels' => $labels,
            'toeHeel' => $toeHeelValues,
            'side' => $sideValues,
        ]);
    }
};
?>

<div class="p-4">
    <h1 class="text-xl font-semibold">Pressure Monitoring: {{ $this->mechine }} ({{ $this->position }})</h1>

    <div class="flex gap-4 mb-4 flex-wrap">
        <input type="date" wire:model.live="start_at" class="border rounded p-2">
        <input type="date" wire:model.live="end_at" class="border rounded p-2">
        <select wire:model.live="position" class="border rounded p-2">
            <option value="L">Left</option>
            <option value="R">Right</option>
        </select>
    </div>

    <div class="h-80">
        <canvas id="pressureChart"></canvas>
    </div>

    @script
    <script>
        let pressureChart;

        function renderPressureChart(data) {
            const ctx = document.getElementById('pressureChart').getContext('2d');

            if (pressureChart) {
                pressureChart.destroy();
            }

            pressureChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Toe/Heel (kg)',
                            data: data.toeHeel,
                            borderColor: 'rgba(54, 162, 235, 1)', // Blue
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            fill: false,
                            tension: 0.1,
                            pointRadius: 2
                        },
                        {
                            label: 'Side (kg)',
                            data: data.side,
                            borderColor: 'rgba(255, 99, 132, 1)', // Red
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            fill: false,
                            tension: 0.1,
                            pointRadius: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'hour',
                                tooltipFormat: 'MMM d, HH:mm',
                                displayFormats: {
                                    hour: 'HH:mm'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Time'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Pressure (kg)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                }
            });
        }

        $wire.on('update-pressure-chart', (data) => {
            renderPressureChart(data);
        });
    </script>
    @endscript
</div>