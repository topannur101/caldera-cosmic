<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsCtcMetric;
use Carbon\Carbon;

new class extends Component {
    
    public int $id = 0;
    public array $batch = [
        'id' => 0,
        'rubber_batch_code' => '',
        'machine_line' => '',
        'mcs' => '',
    
        // Recipe information
        'recipe_id' => 0,
        'recipe_name' => '',
        'recipe_target' => 0,
        'recipe_std_min' => 0,
        'recipe_std_max' => 0,
        'recipe_scale' => 0,
    
        // Performance metrics
        't_avg_left' => 0,
        't_avg_right' => 0,
        't_avg' => 0,
        't_mae_left' => 0,
        't_mae_right' => 0,
        't_mae' => 0,
        't_ssd_left' => 0,
        't_ssd_right' => 0,
        't_ssd' => 0,
        't_balance' => 0,
    
        // Correction metrics
        'correction_uptime' => 0,
        'correction_rate' => 0,
    
        // Quality
        'quality_status' => 'fail',
    
        // Timing and data
        'data' => '',
        'started_at' => '',
        'ended_at' => '',
        'duration' => '',
        'shift' => '',
    
        // Correction counts
        'corrections_left' => 0,
        'corrections_right' => 0,
        'corrections_total' => 0,
    ];

    public $metric = null;

    #[On('metric-detail-load')]
    public function loadMetric($id)
    {
        $this->id = $id;
        $this->metric = InsCtcMetric::with(['ins_ctc_machine', 'ins_ctc_recipe', 'ins_rubber_batch'])->find($id);

        if ($this->metric) {
            $this->batch = [
                'id' => $this->metric->id,
                'rubber_batch_code' => $this->metric->ins_rubber_batch->code ?? 'N/A',
                'machine_line' => $this->metric->ins_ctc_machine->line ?? 'N/A',
                'mcs' => $this->metric->ins_rubber_batch->mcs ?? 'N/A',
                
                // Recipe information
                'recipe_id' => $this->metric->ins_ctc_recipe->id ?? 'N/A',
                'recipe_name' => $this->metric->ins_ctc_recipe->name ?? 'N/A',
                'recipe_target' => $this->metric->ins_ctc_recipe->std_mid ?? 0,
                'recipe_std_min' => $this->metric->ins_ctc_recipe->std_min ?? 0,
                'recipe_std_max' => $this->metric->ins_ctc_recipe->std_max ?? 0,
                'recipe_scale' => $this->metric->ins_ctc_recipe->scale ?? 0,
                
                // Performance metrics
                't_avg_left' => $this->metric->t_avg_left,
                't_avg_right' => $this->metric->t_avg_right,
                't_avg' => $this->metric->t_avg,
                't_mae_left' => $this->metric->t_mae_left,
                't_mae_right' => $this->metric->t_mae_right,
                't_mae' => $this->metric->t_mae,
                't_ssd_left' => $this->metric->t_ssd_left,
                't_ssd_right' => $this->metric->t_ssd_right,
                't_ssd' => $this->metric->t_ssd,
                't_balance' => $this->metric->t_balance,
                
                // Correction metrics
                'correction_uptime' => $this->metric->correction_uptime,
                'correction_rate' => $this->metric->correction_rate,
                
                // Quality
                'quality_status' => $this->metric->t_mae <= 1.0 ? 'pass' : 'fail',
                
                // Timing and data
                'data' => $this->metric->data,
                'started_at' => $this->getStartedAt($this->metric->data),
                'ended_at' => $this->getEndedAt($this->metric->data),
                'duration' => $this->calculateDuration($this->metric->data),
                'shift' => $this->determineShift($this->metric->data),
                
                // Correction counts
                'corrections_left' => $this->countCorrections($this->metric->data, 'left'),
                'corrections_right' => $this->countCorrections($this->metric->data, 'right'),
                'corrections_total' => $this->countCorrections($this->metric->data, 'total'),
            ];

            $this->generateChart();
        } else {
            $this->handleNotFound();
        }
    }

    private function getStartedAt($data): string
    {
        if (!$data || !is_array($data) || count($data) === 0) {
            return 'N/A';
        }

        $firstTimestamp = $data[0][0] ?? null;
        
        if (!$firstTimestamp) {
            return 'N/A';
        }

        try {
            return Carbon::parse($firstTimestamp)->format('H:i:s');
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    private function getEndedAt($data): string
    {
        if (!$data || !is_array($data) || count($data) === 0) {
            return 'N/A';
        }

        $lastTimestamp = $data[count($data) - 1][0] ?? null;
        
        if (!$lastTimestamp) {
            return 'N/A';
        }

        try {
            return Carbon::parse($lastTimestamp)->format('H:i:s');
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    private function calculateDuration($data): string
    {
        if (!$data || !is_array($data) || count($data) < 2) {
            return '00:00:00';
        }

        $firstTimestamp = $data[0][0] ?? null;
        $lastTimestamp = $data[count($data) - 1][0] ?? null;

        if (!$firstTimestamp || !$lastTimestamp) {
            return '00:00:00';
        }

        try {
            $start = Carbon::parse($firstTimestamp);
            $end = Carbon::parse($lastTimestamp);
            $interval = $start->diff($end);
            
            return sprintf('%02d:%02d:%02d', 
                $interval->h, 
                $interval->i, 
                $interval->s
            );
        } catch (Exception $e) {
            return '00:00:00';
        }
    }

    private function determineShift($data): string
    {
        if (!$data || !is_array($data) || count($data) === 0) {
            return 'N/A';
        }

        $firstTimestamp = $data[0][0] ?? null;
        
        if (!$firstTimestamp) {
            return 'N/A';
        }

        try {
            $hour = Carbon::parse($firstTimestamp)->format('H');
            $hour = (int) $hour;
            
            if ($hour >= 6 && $hour < 14) {
                return '1';
            } elseif ($hour >= 14 && $hour < 22) {
                return '2';
            } else {
                return '3';
            }
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    private function countCorrections($data, $type = 'total'): int
    {
        if (!$data || !is_array($data)) {
            return 0;
        }

        $leftCount = 0;
        $rightCount = 0;

        foreach ($data as $point) {
            // Data format: [timestamp, is_correcting, action_left, action_right, sensor_left, sensor_right, recipe_id, std_min, std_max, std_mid]
            $actionLeft = $point[2] ?? 0;
            $actionRight = $point[3] ?? 0;

            if ($actionLeft == 1 || $actionLeft == 2) { // 1=thin, 2=thick
                $leftCount++;
            }
            if ($actionRight == 1 || $actionRight == 2) { // 1=thin, 2=thick
                $rightCount++;
            }
        }

        switch ($type) {
            case 'left':
                return $leftCount;
            case 'right':
                return $rightCount;
            case 'total':
            default:
                return $leftCount + $rightCount;
        }
    }

    private function generateChart(): void
    {
        if (empty($this->batch['data'])) {
            return;
        }

        // Prepare data for Chart.js
        $chartData = $this->prepareChartData($this->batch['data']);
        $chartOptions = $this->getChartOptions();

        $this->js("
            const chartData = " . json_encode($chartData) . ";
            const chartOptions = " . json_encode($chartOptions) . ";

            // Configure time formatting with callback for en-US locale
            chartOptions.scales.x.ticks = {
                callback: function(value, index, values) {
                    const date = new Date(value);
                    return date.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    });
                }
            };
            
            // Add tooltip configuration
            chartOptions.plugins.tooltip = {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' mm';
                    },
                    title: function(context) {
                        if (!context[0]) return '';
                        const date = new Date(context[0].parsed.x);
                        return date.toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false
                        });
                    }
                }
            };

            // Render chart
            const chartContainer = \$wire.\$el.querySelector('#batch-chart-container');
            chartContainer.innerHTML = '';
            const canvas = document.createElement('canvas');
            canvas.id = 'batch-chart';
            chartContainer.appendChild(canvas);
            
            const chart = new Chart(canvas, {
                type: 'line',
                data: chartData,
                options: chartOptions
            });
        ");
    }

    private function prepareChartData($data): array
    {
        // Transform data for Chart.js
        $chartData = [];
        $stdMinData = [];
        $stdMaxData = [];
        $stdMidData = [];

        foreach ($data as $point) {
            // Data format: [timestamp, is_correcting, action_left, action_right, sensor_left, sensor_right, recipe_id, std_min, std_max, std_mid]
            $timestamp = $point[0] ?? null;
            $sensorLeft = $point[4] ?? 0;
            $sensorRight = $point[5] ?? 0;
            
            // New std values from positions 7, 8, 9
            $stdMin = $point[7] ?? null;
            $stdMax = $point[8] ?? null;
            $stdMid = $point[9] ?? null;

            if ($timestamp && ($sensorLeft > 0 || $sensorRight > 0)) {
                $parsedTime = Carbon::parse($timestamp);
                
                $chartData[] = [
                    'x' => $parsedTime,
                    'y' => $sensorLeft,
                    'side' => 'left'
                ];
                $chartData[] = [
                    'x' => $parsedTime,
                    'y' => $sensorRight,
                    'side' => 'right'
                ];

                // Add std data only if values exist
                if ($stdMin !== null) {
                    $stdMinData[] = [
                        'x' => $parsedTime,
                        'y' => $stdMin
                    ];
                }
                if ($stdMax !== null) {
                    $stdMaxData[] = [
                        'x' => $parsedTime,
                        'y' => $stdMax
                    ];
                }
                if ($stdMid !== null) {
                    $stdMidData[] = [
                        'x' => $parsedTime,
                        'y' => $stdMid
                    ];
                }
            }
        }

        // Separate left and right data
        $leftData = array_filter($chartData, fn($item) => $item['side'] === 'left');
        $rightData = array_filter($chartData, fn($item) => $item['side'] === 'right');

        // Build datasets array starting with original sensor data
        $datasets = [
            [
                'label' => 'Sensor Kiri',
                'data' => array_values($leftData),
                'borderColor' => '#3B82F6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                'tension' => 0.1,
                'pointRadius' => 1,
                'pointHoverRadius' => 3,
            ],
            [
                'label' => 'Sensor Kanan',
                'data' => array_values($rightData),
                'borderColor' => '#EF4444',
                'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                'tension' => 0.1,
                'pointRadius' => 1,
                'pointHoverRadius' => 3,
            ]
        ];

        // Add std datasets only if we have data
        if (!empty($stdMinData)) {
            $datasets[] = [
                'label' => 'Std Min',
                'data' => $stdMinData,
                'borderColor' => '#9CA3AF',
                'backgroundColor' => 'transparent',
                'tension' => 0.1,
                'pointRadius' => 0,
                'pointHoverRadius' => 2,
                'borderWidth' => 1,
            ];
        }

        if (!empty($stdMaxData)) {
            $datasets[] = [
                'label' => 'Std Max',
                'data' => $stdMaxData,
                'borderColor' => '#9CA3AF',
                'backgroundColor' => 'transparent',
                'tension' => 0.1,
                'pointRadius' => 0,
                'pointHoverRadius' => 2,
                'borderWidth' => 1,
            ];
        }

        if (!empty($stdMidData)) {
            $datasets[] = [
                'label' => 'Std Mid',
                'data' => $stdMidData,
                'borderColor' => '#9CA3AF',
                'backgroundColor' => 'transparent',
                'tension' => 0.1,
                'pointRadius' => 0,
                'pointHoverRadius' => 2,
                'borderWidth' => 1,
                'borderDash' => [5, 5], // Dashed line
            ];
        }

        return [
            'datasets' => $datasets
        ];
    }

    private function getChartOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => [
                    'type' => 'time',
                    'title' => [
                        'display' => true,
                        'text' => 'Waktu'
                    ]
                ],
                'y' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Ketebalan (mm)'
                    ],
                    'min' => 1,
                    'max' => 5
                ]
            ],
            'plugins' => [
                'datalabels' => [
                    'display' => false
                ],
                'legend' => [
                    'display' => true,
                    'position' => 'top'
                ]
            ]
        ];
    }

    private function handleNotFound(): void
    {
        $this->js('toast("' . __('Data metrik tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }
};

?>

<div class="p-6">    
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Rincian Batch') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Side: Chart + Data Table (2 columns) -->
        <div class="col-span-2 space-y-6">
            <!-- Chart Container -->
            <div class="h-80 overflow-hidden bg-white dark:bg-neutral-800 rounded-lg border"
                id="batch-chart-container" wire:key="batch-chart-container" wire:ignore>
            </div>

            <!-- Performance Data Table -->
            <table class="table table-xs text-sm text-center mt-6">
                <tr class="text-xs uppercase text-neutral-500 dark:text-neutral-400 border-b border-neutral-300 dark:border-neutral-700">
                    <td></td>
                    <td>{{ __('Kiri') }}</td>
                    <td>{{ __('Kanan') }}</td>
                    <td>{{ __('Gabungan') }}</td>
                    <td>{{ __('Evaluasi') }}</td>
                </tr>
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('AVG') }}</td>
                    <td>{{ number_format($batch['t_avg_left'], 2) }}</td>
                    <td>{{ number_format($batch['t_avg_right'], 2) }}</td>
                    <td>{{ number_format($batch['t_avg'], 2) }}</td>
                    <td>
                        @php
                            $avgEval = $metric?->avg_evaluation;
                        @endphp
                        <div class="flex items-center gap-2">
                            <i class="icon-check-circle {{ $avgEval['icon_color'] ?? '' }}"></i>
                            <span class="{{ $avgEval['color'] ?? '' }} text-xs font-medium">{{ ucfirst($avgEval['status'] ?? '') }}</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('MAE') }}</td>
                    <td>{{ number_format($batch['t_mae_left'], 2) }}</td>
                    <td>{{ number_format($batch['t_mae_right'], 2) }}</td>
                    <td>{{ number_format($batch['t_mae'], 2) }}</td>
                    <td>
                        @php
                            $maeEval = $metric?->mae_evaluation;
                        @endphp
                        <div class="flex items-center gap-2">
                            <i class="icon-check-circle {{ $maeEval['icon_color'] ?? '' }}"></i>
                            <span class="{{ $maeEval['color'] ?? '' }} text-xs font-medium">{{ ucfirst($maeEval['status'] ?? '') }}</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('SSD') }}</td>
                    <td>{{ number_format($batch['t_ssd_left'], 2) }}</td>
                    <td>{{ number_format($batch['t_ssd_right'], 2) }}</td>
                    <td>{{ number_format($batch['t_ssd'], 2) }}</td>
                    <td>
                        @php
                            $ssdEval = $metric?->ssd_evaluation;
                        @endphp
                        <div class="flex items-center gap-2">
                            <i class="icon-check-circle {{ $ssdEval['icon_color'] ?? '' }}"></i>
                            <span class="{{ $ssdEval['color'] ?? '' }} text-xs font-medium">{{ ucfirst($ssdEval['status'] ?? '') }}</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('Koreksi') }}</td>
                    <td>{{ $batch['corrections_left'] }}</td>
                    <td>{{ $batch['corrections_right'] }}</td>
                    <td>{{ $batch['corrections_total'] }}</td>
                    <td>
                        @php
                            $correctionEval = $metric?->correction_evaluation;
                        @endphp
                        <div class="flex items-center gap-2">
                            <i class="icon-check-circle {{ $correctionEval['icon_color'] ?? '' }}"></i>
                            <span class="{{ $correctionEval['color'] ?? '' }} text-xs font-medium">{{ ucfirst($correctionEval['status'] ?? '') }}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Right Side: Info Panels (1 column) -->
        <div class="space-y-6">
            <!-- Batch Information -->
            <div>
                <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Informasi Batch') }}</div>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-neutral-500">{{ __('Batch:') }}</span>
                        <span class="font-medium">{{ $batch['rubber_batch_code'] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500">{{ __('MCS:') }}</span>
                        <span class="font-medium">{{ $batch['mcs'] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500">{{ __('Line:') }}</span>
                        <span class="font-medium">{{ $batch['machine_line'] }}</span>
                    </div>
                </div>
            </div>

            <!-- Timing Information -->
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

            <!-- Correction & Quality -->
            <div>
                <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Keseimbangan & Koreksi') }}</div>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-neutral-500">{{ __('BAL:') }}</span>
                        <span class="font-mono">{{  number_format($batch['t_balance'], 2) }} mm</span>
                    </div>
                    <div class="flex gap-x-3">
                        <div>
                            <span class="text-neutral-500">{{ __('CU:') }}</span>
                            <span class="font-mono">{{ $batch['correction_uptime'] }}%</span>
                        </div>
                        <div>
                            <span class="text-neutral-500">{{ __('CR:') }}</span>
                            <span class="font-mono">{{ $batch['correction_rate'] }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recipe Information -->
            <div>
                <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Informasi Resep') }}</div>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-neutral-500">{{ __('Recipe ID:') }}</span>
                        <span class="font-medium">{{ $batch['recipe_id'] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500">{{ __('Name:') }}</span>
                        <span class="font-medium">{{ $batch['recipe_name'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>