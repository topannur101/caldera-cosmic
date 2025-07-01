<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    
    public int $batchId = 0;
    public array $batch = [];

    #[On('batch-detail-load')]
    public function loadBatch($id)
    {
        $this->batchId = $id;
        $this->loadMockBatchData();
    }

    private function loadMockBatchData(): void
    {
        // Mock batch detail data
        $this->batch = [
            'id' => $this->batchId,
            'rubber_batch_code' => 'RB240501A',
            'device_line' => 3,
            'recipe_name' => 'AF1 GS (ONE COLOR)',
            'model' => 'AF1',
            'color' => 'WHITE',
            'mcs' => 'GS',
            'started_at' => '2024-05-01 08:15:00',
            'ended_at' => '2024-05-01 08:45:30',
            'duration' => '00:30:30',
            'shift' => 1,
            'measurement_count' => 1830,
            'avg_left' => 3.05,
            'avg_right' => 3.02,
            'sd_left' => 0.08,
            'sd_right' => 0.09,
            'mae_left' => 0.05,
            'mae_right' => 0.02,
            'correction_rate' => 0.15,
            'quality_status' => 'pass',
            'worker_override' => false,
            'operator_name' => 'John Doe',
            'operator_emp_id' => 'EMP001',
            'recipe_details' => [
                'std_min' => 3.0,
                'std_max' => 3.1,
                'std_mid' => 3.05,
                'pfc_min' => 3.4,
                'pfc_max' => 3.6
            ],
            'rubber_batch_details' => [
                'composition' => [7.5, 12.3, 45.2, 15.1, 8.9, 6.7, 4.3],
                'open_mill_data' => [
                    'eval' => 'on_time',
                    'kwh_usage' => 15.7,
                    'duration' => '00:12:45'
                ]
            ]
        ];

        // Generate mock chart data
        $this->generateChartData();
    }

    private function generateChartData(): void
    {
        $measurements = [];
        $baseTime = strtotime($this->batch['started_at']);
        
        for ($i = 0; $i < 100; $i++) {
            $measurements[] = [
                'timestamp' => date('H:i:s', $baseTime + ($i * 18)), // Every 18 seconds
                'sensor_left' => 3.05 + (rand(-15, 15) / 100),
                'sensor_right' => 3.02 + (rand(-15, 15) / 100),
                'is_correcting' => rand(0, 100) < 15, // 15% correction rate
            ];
        }

        $chartData = [
            'measurements' => $measurements,
            'recipe' => $this->batch['recipe_details']
        ];

        $this->js("
            updateBatchChart(" . json_encode($chartData) . ");
        ");
    }

    public function downloadBatchData(): void
    {
        $this->js('toast("' . __('Data batch diunduh') . '", { type: "success" })');
    }

    public function printBatch(): void
    {
        $this->dispatch('batch-print-prepare', $this->batch);
    }
};

?>

<div class="p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Detail Gilingan') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')">
            <i class="icon-x"></i>
        </x-text-button>
    </div>

    @if($batch)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mt-6">
            {{-- Chart Area --}}
            <div class="col-span-2">
                <div class="h-80 overflow-hidden" id="batch-chart-container" wire:ignore></div>
                
                {{-- Quality Metrics Table --}}
                <table class="table table-xs text-sm text-center mt-6">
                    <tr class="text-xs uppercase text-neutral-500 dark:text-neutral-400 border-b border-neutral-300 dark:border-neutral-700">
                        <td>{{ __('Metrik') }}</td>
                        <td>{{ __('Kiri') }}</td>
                        <td>{{ __('Kanan') }}</td>
                        <td>{{ __('Standar') }}</td>
                    </tr>
                    <tr>
                        <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('AVG') }}</td>
                        <td class="font-mono">{{ number_format($batch['avg_left'], 2) }}</td>
                        <td class="font-mono">{{ number_format($batch['avg_right'], 2) }}</td>
                        <td class="font-mono">{{ $batch['recipe_details']['std_mid'] }}</td>
                    </tr>
                    <tr>
                        <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('SD') }}</td>
                        <td class="font-mono">{{ number_format($batch['sd_left'], 2) }}</td>
                        <td class="font-mono">{{ number_format($batch['sd_right'], 2) }}</td>
                        <td class="font-mono">{{ $batch['recipe_details']['std_min'] }} - {{ $batch['recipe_details']['std_max'] }}</td>
                    </tr>
                    <tr>
                        <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('MAE') }}</td>
                        <td class="font-mono">{{ number_format($batch['mae_left'], 2) }}</td>
                        <td class="font-mono">{{ number_format($batch['mae_right'], 2) }}</td>
                        <td class="text-neutral-400">-</td>
                    </tr>
                </table>
            </div>

            {{-- Batch Information --}}
            <div>
                <div class="grid grid-cols-1 gap-6">
                    {{-- Basic Info --}}
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Informasi Batch') }}</div>
                        <div class="space-y-2 text-sm">
                            <div>
                                <span class="text-neutral-500">{{ __('Kode:') }}</span>
                                <span class="font-medium">{{ $batch['rubber_batch_code'] }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Line:') }}</span>
                                <span class="font-medium">{{ $batch['device_line'] }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Model:') }}</span>
                                <span class="font-medium">{{ $batch['model'] }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Warna:') }}</span>
                                <span class="font-medium">{{ $batch['color'] }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('MCS:') }}</span>
                                <span class="font-medium">{{ $batch['mcs'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Timing --}}
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Waktu Proses') }}</div>
                        <div class="space-y-2 text-sm">
                            <div>
                                <span class="text-neutral-500">{{ __('Mulai:') }}</span>
                                <span class="font-mono">{{ $batch['started_at'] }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Selesai:') }}</span>
                                <span class="font-mono">{{ $batch['ended_at'] }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Durasi:') }}</span>
                                <span class="font-mono">{{ $batch['duration'] }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Shift:') }}</span>
                                <span class="font-medium">{{ $batch['shift'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Recipe & Operator --}}
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Resep & Operator') }}</div>
                        <div class="space-y-2 text-sm">
                            <div>
                                <span class="text-neutral-500">{{ __('Resep:') }}</span>
                                <span class="font-medium">{{ $batch['recipe_name'] }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Status:') }}</span>
                                @if($batch['worker_override'])
                                    <span class="text-yellow-600">{{ __('Override') }}</span>
                                @else
                                    <span class="text-green-600">{{ __('Rekomendasi') }}</span>
                                @endif
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Operator:') }}</span>
                                <span class="font-medium">{{ $batch['operator_name'] }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('ID:') }}</span>
                                <span class="font-mono">{{ $batch['operator_emp_id'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Quality Status --}}
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Status Kualitas') }}</div>
                        <div class="space-y-2 text-sm">
                            <div>
                                @if($batch['quality_status'] === 'pass')
                                    <span class="inline-flex px-3 py-1 text-sm bg-green-100 text-green-800 rounded-full">
                                        <i class="icon-check-circle mr-1"></i>{{ __('Lulus') }}
                                    </span>
                                @else
                                    <span class="inline-flex px-3 py-1 text-sm bg-red-100 text-red-800 rounded-full">
                                        <i class="icon-x-circle mr-1"></i>{{ __('Gagal') }}
                                    </span>
                                @endif
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Koreksi:') }}</span>
                                <span class="font-mono">{{ number_format($batch['correction_rate'] * 100, 1) }}%</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Pembacaan:') }}</span>
                                <span class="font-mono">{{ number_format($batch['measurement_count']) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Integration Data --}}
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Data Open Mill') }}</div>
                        <div class="space-y-2 text-sm">
                            <div>
                                <span class="text-neutral-500">{{ __('Evaluasi:') }}</span>
                                <span class="font-medium capitalize">{{ str_replace('_', ' ', $batch['rubber_batch_details']['open_mill_data']['eval']) }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Energi:') }}</span>
                                <span class="font-mono">{{ $batch['rubber_batch_details']['open_mill_data']['kwh_usage'] }} kWh</span>
                            </div>
                            <div>
                                <span class="text-neutral-500">{{ __('Durasi OM:') }}</span>
                                <span class="font-mono">{{ $batch['rubber_batch_details']['open_mill_data']['duration'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex gap-x-3 justify-end items-end mt-6 pt-4 border-t border-neutral-200 dark:border-neutral-700">
            <x-secondary-button type="button" wire:click="downloadBatchData">
                <i class="icon-download me-2"></i>{{ __('Unduh Data') }}
            </x-secondary-button>
            <x-primary-button type="button" wire:click="printBatch">
                <i class="icon-printer me-2"></i>{{ __('Cetak') }}
            </x-primary-button>
        </div>
    @endif

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>

<script>
    function updateBatchChart(data) {
        // Mock chart implementation - will be replaced with actual charting library
        const container = document.getElementById('batch-chart-container');
        if (container) {
            const measurements = data.measurements;
            const recipe = data.recipe;
            
            container.innerHTML = `
                <div class="flex items-center justify-center h-full bg-neutral-50 dark:bg-neutral-700 rounded">
                    <div class="text-center">
                        <div class="text-lg font-medium mb-2">Batch Chart Placeholder</div>
                        <div class="text-sm text-neutral-500 mb-2">
                            ${measurements.length} measurements from ${measurements[0].timestamp} to ${measurements[measurements.length-1].timestamp}
                        </div>
                        <div class="text-xs text-neutral-400">
                            Standard Range: ${recipe.std_min} - ${recipe.std_max} mm
                        </div>
                        <div class="text-xs text-neutral-400 mt-1">
                            Final Values: L:${measurements[measurements.length-1].sensor_left.toFixed(2)} R:${measurements[measurements.length-1].sensor_right.toFixed(2)}
                        </div>
                    </div>
                </div>
            `;
        }
    }
</script>