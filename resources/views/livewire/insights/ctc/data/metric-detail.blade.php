<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsCtcMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

new class extends Component {
    public int $id = 0;
    public bool $header = true;
    public array $batch = [
        "id" => 0,
        "rubber_batch_code" => "",
        "machine_line" => "",
        "mcs" => "",

        // Recipe information
        "recipe_id" => 0,
        "recipe_name" => "",
        "recipe_target" => 0,
        "recipe_std_min" => 0,
        "recipe_std_max" => 0,
        "recipe_scale" => 0,

        // Performance metrics
        "t_avg_left" => 0,
        "t_avg_right" => 0,
        "t_avg" => 0,
        "t_mae_left" => 0,
        "t_mae_right" => 0,
        "t_mae" => 0,
        "t_ssd_left" => 0,
        "t_ssd_right" => 0,
        "t_ssd" => 0,
        "t_balance" => 0,

        // Correction metrics
        "correction_uptime" => 0,
        "correction_rate" => 0,

        // Quality
        "quality_status" => "fail",

        // Timing and data
        "data" => "",
        "started_at" => "",
        "ended_at" => "",
        "duration" => "",
        "shift" => "",

        // Correction counts
        "corrections_left" => 0,
        "corrections_right" => 0,
        "corrections_total" => 0,
    ];

    public $metric = null;
    // Computed property untuk check download permission
    public function getCanDownloadBatchCsvProperty(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }
        
        // Superuser selalu bisa
        if ($user->id === 1) {
            return true;
        }
        
        try {
            $auth = \App\Models\InsCtcAuth::where('user_id', $user->id)->first();
            
            if ($auth) {
                $actions = json_decode($auth->actions ?? '[]', true);
                return in_array('batch-detail-download', $actions);
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function mount()
    {
        if ($this->id) {
            $this->loadMetric($this->id);
            $this->header = false;
        }
    }

    #[On("metric-detail-load")]
    public function loadMetric($id)
    {
        $this->id = $id;
        $this->metric = InsCtcMetric::with(["ins_ctc_machine", "ins_ctc_recipe", "ins_rubber_batch"])->find($id);

        if ($this->metric) {
            $correctionsByType = $this->countCorrectionsByType($this->metric->data);
            
            $this->batch = [
                "id" => $this->metric->id,
                "rubber_batch_code" => $this->metric->ins_rubber_batch->code ?? "N/A",
                "machine_line" => $this->metric->ins_ctc_machine->line ?? "N/A",
                "mcs" => $this->metric->ins_rubber_batch->mcs ?? "N/A",

                // Recipe information
                "recipe_id" => $this->metric->ins_ctc_recipe->id ?? "N/A",
                "recipe_name" => $this->metric->ins_ctc_recipe->name ?? "N/A",
                "recipe_target" => $this->metric->ins_ctc_recipe->std_mid ?? 0,
                "recipe_std_min" => $this->metric->ins_ctc_recipe->std_min ?? 0,
                "recipe_std_max" => $this->metric->ins_ctc_recipe->std_max ?? 0,
                "recipe_scale" => $this->metric->ins_ctc_recipe->scale ?? 0,

                // Performance metrics
                "t_avg_left" => $this->metric->t_avg_left,
                "t_avg_right" => $this->metric->t_avg_right,
                "t_avg" => $this->metric->t_avg,
                "t_mae_left" => $this->metric->t_mae_left,
                "t_mae_right" => $this->metric->t_mae_right,
                "t_mae" => $this->metric->t_mae,
                "t_ssd_left" => $this->metric->t_ssd_left,
                "t_ssd_right" => $this->metric->t_ssd_right,
                "t_ssd" => $this->metric->t_ssd,
                "t_balance" => $this->metric->t_balance,

                // Correction metrics
                "correction_uptime" => $this->metric->correction_uptime,
                "correction_rate" => $this->metric->correction_rate,

                // Quality
                "quality_status" => $this->metric->t_mae <= 1.0 ? "pass" : "fail",

                // Timing and data
                "data" => $this->metric->data,
                "started_at" => $this->getStartedAt($this->metric->data),
                "ended_at" => $this->getEndedAt($this->metric->data),
                "duration" => $this->calculateDuration($this->metric->data),
                "shift" => $this->determineShift($this->metric->data),

                // Correction counts
                "corrections_left" => $this->countCorrections($this->metric->data, "left"),
                "corrections_right" => $this->countCorrections($this->metric->data, "right"),
                "corrections_total" => $this->countCorrections($this->metric->data, "total"),
                
                // TAMBAHAN: Correction counts by type
                "thick_left" => $correctionsByType['thick_left'],
                "thick_right" => $correctionsByType['thick_right'],
                "thin_left" => $correctionsByType['thin_left'],
                "thin_right" => $correctionsByType['thin_right'],
            ];

            $this->generateChart();
        } else {
            $this->handleNotFound();
        }
    }

    private function getStartedAt($data): string
    {
        if (! $data || ! is_array($data) || count($data) === 0) {
            return "N/A";
        }

        $firstTimestamp = $data[0][0] ?? null;

        if (! $firstTimestamp) {
            return "N/A";
        }

        try {
            return Carbon::parse($firstTimestamp)->format("H:i:s");
        } catch (Exception $e) {
            return "N/A";
        }
    }

    private function getEndedAt($data): string
    {
        if (! $data || ! is_array($data) || count($data) === 0) {
            return "N/A";
        }

        $lastTimestamp = $data[count($data) - 1][0] ?? null;

        if (! $lastTimestamp) {
            return "N/A";
        }

        try {
            return Carbon::parse($lastTimestamp)->format("H:i:s");
        } catch (Exception $e) {
            return "N/A";
        }
    }

    private function calculateDuration($data): string
    {
        if (! $data || ! is_array($data) || count($data) < 2) {
            return "00:00:00";
        }

        $firstTimestamp = $data[0][0] ?? null;
        $lastTimestamp = $data[count($data) - 1][0] ?? null;

        if (! $firstTimestamp || ! $lastTimestamp) {
            return "00:00:00";
        }

        try {
            $start = Carbon::parse($firstTimestamp);
            $end = Carbon::parse($lastTimestamp);
            $interval = $start->diff($end);

            return sprintf("%02d:%02d:%02d", $interval->h, $interval->i, $interval->s);
        } catch (Exception $e) {
            return "00:00:00";
        }
    }

    private function determineShift($data): string
    {
        if (! $data || ! is_array($data) || count($data) === 0) {
            return "N/A";
        }

        $firstTimestamp = $data[0][0] ?? null;

        if (! $firstTimestamp) {
            return "N/A";
        }

        try {
            $hour = Carbon::parse($firstTimestamp)->format("H");
            $hour = (int) $hour;

            if ($hour >= 6 && $hour < 14) {
                return "1";
            } elseif ($hour >= 14 && $hour < 22) {
                return "2";
            } else {
                return "3";
            }
        } catch (Exception $e) {
            return "N/A";
        }
    }

    private function countCorrections($data, $type = "total"): int
    {
        if (! $data || ! is_array($data)) {
            return 0;
        }

        $leftCount = 0;
        $rightCount = 0;

        foreach ($data as $point) {
            // Data format: [timestamp, is_correcting, action_left, action_right, sensor_left, sensor_right, recipe_id, std_min, std_max, std_mid]
            $actionLeft = $point[2] ?? 0;
            $actionRight = $point[3] ?? 0;

            if ($actionLeft == 1 || $actionLeft == 2) {
                // 1=thin, 2=thick
                $leftCount++;
            }
            if ($actionRight == 1 || $actionRight == 2) {
                // 1=thin, 2=thick
                $rightCount++;
            }
        }

        switch ($type) {
            case "left":
                return $leftCount;
            case "right":
                return $rightCount;
            case "total":
            default:
                return $leftCount + $rightCount;
        }
    }

    private function calculateEffectiveChange($data, $dataIndex, $side): ?float
    {
        if ($dataIndex < 0 || $dataIndex >= count($data)) {
            return null;
        }
        
        $currentPoint = $data[$dataIndex];
        $currentValue = $side === 'left' ? ($currentPoint[4] ?? 0) : ($currentPoint[5] ?? 0);
        
        // Cari nilai 3-8 point ke depan untuk melihat efek dari trigger
        $futureValue = null;
        $searchRange = min(8, count($data) - $dataIndex - 1);
        
        for ($i = 3; $i <= $searchRange; $i++) {
            $futurePoint = $data[$dataIndex + $i];
            $futureAction = $side === 'left' ? ($futurePoint[2] ?? 0) : ($futurePoint[3] ?? 0);
            $futureVal = $side === 'left' ? ($futurePoint[4] ?? 0) : ($futurePoint[5] ?? 0);
            
            // Ambil nilai saat tidak ada trigger baru atau di point ke-5
            if ($futureAction == 0 || $i == 5) {
                $futureValue = $futureVal;
                break;
            }
        }
        
        if ($futureValue === null) {
            return null;
        }
        
        // Hitung perubahan absolut
        $change = abs($futureValue - $currentValue);
        return $change;
    }

    public function downloadCsv()
    {
        if (!$this->canDownloadBatchCsv) {
            $this->js('toast("' . __("Anda tidak memiliki akses untuk mengunduh rincian batch") . '", { type: "danger" })');
            return;
        }
    
        if (!$this->metric) {
            $this->js('toast("' . __("Data tidak ditemukan") . '", { type: "danger" })');
            return;
        }

        $batchCode = $this->batch["rubber_batch_code"];
        $line = $this->batch["machine_line"];

        $safeBatchCode = preg_replace('/[^A-Za-z0-9_\-]/', '_', $batchCode);
        $safeLine = preg_replace('/[^A-Za-z0-9_\-]/', '_', $line);

        $timestamp = now()->format('Ymd_His');
        $filename = "batch_{$safeBatchCode}_line{$safeLine}_{$timestamp}.csv";

        $data = $this->metric->data;
        $batchInfo = $this->batch;
        
        return Response::streamDownload(function () use ($data, $batchInfo) {
            $file = fopen('php://output', 'w');
            
            // BOM untuk Excel UTF-8 support
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header CSV
            fputcsv($file, [
                'No',
                'Timestamp',
                'Waktu',
                'Sensor_Kiri_mm',
                'Sensor_Kanan_mm',
                'Trigger_Kiri',
                'Trigger_Kanan',
                'Trigger_Kiri_Jenis',
                'Trigger_Kanan_Jenis',
                'Perubahan_Kiri_mm',     
                'Perubahan_Kanan_mm',     
                'Dampak_Kiri_Persen',     
                'Dampak_Kanan_Persen',    
                'Std_Min',
                'Std_Max',
                'Std_Mid',
                'Is_Correcting',
                'Batch_Code',
                'Line',
                'MCS',
                'Recipe_ID',
                'Recipe_Name',
                'Shift',
            ]);
            
            // Data rows
            foreach ($data as $index => $point) {
                // Data format: [timestamp, is_correcting, action_left, action_right, sensor_left, sensor_right, recipe_id, std_min, std_max, std_mid]
                $timestamp = $point[0] ?? '';
                $isCorrecting = $point[1] ?? 0;
                $actionLeft = $point[2] ?? 0;
                $actionRight = $point[3] ?? 0;
                $sensorLeft = $point[4] ?? 0;
                $sensorRight = $point[5] ?? 0;
                $recipeId = $point[6] ?? 0;
                $stdMin = $point[7] ?? 0;
                $stdMax = $point[8] ?? 0;
                $stdMid = $point[9] ?? 0;
                
                // Parse waktu
                $waktu = '';
                try {
                    $waktu = \Carbon\Carbon::parse($timestamp)->format('H:i:s');
                } catch (\Exception $e) {
                    $waktu = '';
                }
                
                // Convert action code to label
                $triggerLeftLabel = $this->getActionLabel($actionLeft);
                $triggerRightLabel = $this->getActionLabel($actionRight);

                // Hitung perubahan efektif untuk kiri
                $changeLeft = 0;
                $percentLeft = 0;
                if ($actionLeft == 1 || $actionLeft == 2) {
                    $effectiveChange = $this->calculateEffectiveChange($data, $index, 'left');
                    if ($effectiveChange !== null && $effectiveChange > 0) {
                        $changeLeft = $effectiveChange;
                        $percentLeft = $sensorLeft > 0 ? ($effectiveChange / $sensorLeft) * 100 : 0;
                    }
                }
                // Hitung perubahan efektif untuk kanan
                $changeRight = 0;
                $percentRight = 0;
                if ($actionRight == 1 || $actionRight == 2) {
                    $effectiveChange = $this->calculateEffectiveChange($data, $index, 'right');
                    if ($effectiveChange !== null && $effectiveChange > 0) {
                        $changeRight = $effectiveChange;
                        $percentRight = $sensorRight > 0 ? ($effectiveChange / $sensorRight) * 100 : 0;
                    }
                }
                
                fputcsv($file, [
                    $index + 1, // No
                    $timestamp, // Timestamp full
                    $waktu, // Waktu HH:mm:ss
                    number_format($sensorLeft, 2, '.', ''), // Sensor Kiri
                    number_format($sensorRight, 2, '.', ''), // Sensor Kanan
                    $actionLeft, // Trigger Kiri (code)
                    $actionRight, // Trigger Kanan (code)
                    $triggerLeftLabel, // Trigger Kiri (label)
                    $triggerRightLabel, // Trigger Kanan (label)
                    number_format($changeLeft, 2, '.', ''),     
                    number_format($changeRight, 2, '.', ''),     
                    number_format($percentLeft, 1, '.', ''),     
                    number_format($percentRight, 1, '.', ''),    
                    number_format($stdMin, 2, '.', ''), // Std Min
                    number_format($stdMax, 2, '.', ''), // Std Max
                    number_format($stdMid, 2, '.', ''), // Std Mid
                    $isCorrecting, // Is Correcting
                    $batchInfo['rubber_batch_code'], // Batch Code
                    $batchInfo['machine_line'], // Line
                    $batchInfo['mcs'], // MCS
                    $recipeId, // Recipe ID
                    $batchInfo['recipe_name'], // Recipe Name
                    $batchInfo['shift'], // Shift
                ]);
            }
            
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // Helper method untuk convert action code ke label
    private function getActionLabel($actionCode): string
    {
        switch ($actionCode) {
            case 1:
                return 'Menipiskan';
            case 2:
                return 'Menebalkan';
            default:
                return '-';
        }
    }

    private function generateChart(): void
    {
        if (empty($this->batch["data"])) {
            return;
        }

        $chartData = $this->prepareChartData($this->batch["data"]);
        $chartOptions = $this->getChartOptions();
        
        $rawDataJson = json_encode($this->batch["data"]);

        $this->js(
            "
            const chartData = " . json_encode($chartData) . ";
            const chartOptions = " . json_encode($chartOptions) . ";
            const rawData = " . $rawDataJson . ";

            // Fungsi untuk mencari data point berdasarkan timestamp
            function findDataPointIndex(timestamp) {
                for (let i = 0; i < rawData.length; i++) {
                    const pointTimestamp = new Date(rawData[i][0]);
                    const targetTimestamp = new Date(timestamp);
                    if (Math.abs(pointTimestamp - targetTimestamp) < 1000) {
                        return i;
                    }
                }
                return -1;
            }

            // Fungsi untuk menghitung perubahan efektif setelah trigger
            function calculateEffectiveChange(dataIndex, side) {
                if (dataIndex < 0 || dataIndex >= rawData.length) return null;
                
                const currentPoint = rawData[dataIndex];
                const currentValue = side === 'left' ? currentPoint[4] : currentPoint[5];
                
                // Cari nilai 3-8 point ke depan untuk melihat efek dari trigger
                let futureValue = null;
                let searchRange = Math.min(8, rawData.length - dataIndex - 1);
                
                for (let i = 3; i <= searchRange; i++) {
                    const futurePoint = rawData[dataIndex + i];
                    const futureAction = side === 'left' ? futurePoint[2] : futurePoint[3];
                    const futureVal = side === 'left' ? futurePoint[4] : futurePoint[5];
                    
                    // Ambil nilai saat tidak ada trigger baru atau di point ke-5
                    if (futureAction === 0 || i === 5) {
                        futureValue = futureVal;
                        break;
                    }
                }
                
                if (futureValue === null) return null;
                
                // Hitung perubahan absolut
                const change = Math.abs(futureValue - currentValue);
                return change;
            }

            // Configure time formatting
            chartOptions.scales.x.ticks = {
                callback: function(value, index, values) {
                    const date = new Date(value);
                    return date.toLocaleTimeString('id-ID', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    });
                }
            };

            // TOOLTIP CONFIGURATION - Dengan Info Detail
            chartOptions.plugins.tooltip = {
                callbacks: {
                    title: function(context) {
                        if (!context[0]) return '';
                        const date = new Date(context[0].parsed.x);
                        return date.toLocaleTimeString('id-ID', {
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false
                        });
                    },
                    label: function(context) {
                        const point = context.raw;
                        let lines = [];
                        
                        // Baris 1: Nilai sensor
                        lines.push(context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' mm');
                        
                        // Jika ada trigger, tampilkan detail
                        if (point && point.action && (point.action === 1 || point.action === 2)) {
                            const side = point.side;
                            const dataIndex = findDataPointIndex(point.x);
                            
                            // Jenis trigger
                            const emoji = point.action === 2 ? '▲' : '▼';
                            const actionType = point.action === 2 ? 'Menebalkan' : 'Menipiskan';
                            
                            lines.push(''); // Empty line untuk spacing
                            lines.push(emoji + actionType);
                            
                            // Hitung perubahan efektif
                            if (dataIndex >= 0) {
                                const effectiveChange = calculateEffectiveChange(dataIndex, side);
                                if (effectiveChange !== null && effectiveChange > 0) {
                                    lines.push('📊 ' + effectiveChange.toFixed(2) + ' mm');
                                    
                                    // Persentase perubahan
                                    const percentChange = ((effectiveChange / context.parsed.y) * 100).toFixed(1);
                                    lines.push('📈 ' + percentChange + '%');
                                }
                            }
                        }
                        
                        return lines;
                    },
                    labelColor: function(context) {
                        // Warna kotak sesuai dataset (biru untuk kiri, merah untuk kanan)
                        return {
                            borderColor: context.dataset.borderColor,
                            backgroundColor: context.dataset.borderColor,
                            borderWidth: 2
                        };
                    }
                }
            };

            // DATALABELS - Hanya Simbol Tanpa Angka
            chartOptions.plugins.datalabels = {
                display: function(context) {
                    const point = context.dataset.data[context.dataIndex];
                    return point && point.action && (point.action === 1 || point.action === 2);
                },
                formatter: function(value, context) {
                    const point = context.dataset.data[context.dataIndex];
                    if (!point || !point.action) return '';
                    
                    // HANYA SIMBOL, TANPA ANGKA
                    return point.action === 2 ? '▲' : '▼';
                },
                color: function(context) {
                    return context.dataset.borderColor;  // Warna sesuai dataset
                },
                align: function(context) {
                    const point = context.dataset.data[context.dataIndex];
                    return point && point.action === 2 ? 'top' : 'bottom';
                },
                offset: 6,
                font: {
                    size: 12,
                    weight: 'bold'
                }
            };

            // Render chart
            const chartContainer = \$wire.\$el.querySelector('#batch-chart-container');
            if (!chartContainer) {
                console.error('Chart container not found');
                return;
            }
            
            // Destroy existing chart if any
            const existingCanvas = chartContainer.querySelector('#batch-chart');
            if (existingCanvas) {
                const existingChart = Chart.getChart('batch-chart');
                if (existingChart) {
                    existingChart.destroy();
                }
            }
            
            chartContainer.innerHTML = '';
            const canvas = document.createElement('canvas');
            canvas.id = 'batch-chart';
            chartContainer.appendChild(canvas);

            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded');
                return;
            }
            
            if (typeof ChartDataLabels === 'undefined') {
                console.error('ChartDataLabels plugin not loaded');
                return;
            }

            const chart = new Chart(canvas, {
                type: 'line',
                data: chartData,
                options: chartOptions,
                plugins: [ChartDataLabels]
            });
            
            console.log('Chart rendered successfully');
        ",
        );
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
            $actionLeft = $point[2] ?? 0;
            $actionRight = $point[3] ?? 0;
            $sensorLeft = $point[4] ?? 0;
            $sensorRight = $point[5] ?? 0;

            // New std values from positions 7, 8, 9
            $stdMin = $point[7] ?? null;
            $stdMax = $point[8] ?? null;
            $stdMid = $point[9] ?? null;

            if ($timestamp && ($sensorLeft > 0 || $sensorRight > 0)) {
                $parsedTime = Carbon::parse($timestamp);

                $chartData[] = [
                    "x" => $parsedTime,
                    "y" => $sensorLeft,
                    "side" => "left",
                    "action" => $actionLeft,
                ];
                $chartData[] = [
                    "x" => $parsedTime,
                    "y" => $sensorRight,
                    "side" => "right",
                    "action" => $actionRight,
                ];

                // Add std data only if values exist
                if ($stdMin !== null) {
                    $stdMinData[] = [
                        "x" => $parsedTime,
                        "y" => $stdMin,
                    ];
                }
                if ($stdMax !== null) {
                    $stdMaxData[] = [
                        "x" => $parsedTime,
                        "y" => $stdMax,
                    ];
                }
                if ($stdMid !== null) {
                    $stdMidData[] = [
                        "x" => $parsedTime,
                        "y" => $stdMid,
                    ];
                }
            }
        }

        // Separate left and right data
        $leftData = array_filter($chartData, fn ($item) => $item["side"] === "left");
        $rightData = array_filter($chartData, fn ($item) => $item["side"] === "right");

        // Build datasets array starting with original sensor data
        $datasets = [
            [
                "label" => "Sensor Kiri",
                "data" => array_values($leftData),
                "borderColor" => "#3B82F6",
                "backgroundColor" => "rgba(59, 130, 246, 0.1)",
                "tension" => 0.1,
                "pointRadius" => 2,
                "pointHoverRadius" => 3,
                "borderWidth" => 1,
            ],
            [
                "label" => "Sensor Kanan",
                "data" => array_values($rightData),
                "borderColor" => "#EF4444",
                "backgroundColor" => "rgba(239, 68, 68, 0.1)",
                "tension" => 0.1,
                "pointRadius" => 2,
                "pointHoverRadius" => 3,
                "borderWidth" => 1,
            ],
        ];

        // Add std datasets only if we have data
        if (! empty($stdMinData)) {
            $datasets[] = [
                "label" => "Std Min",
                "data" => $stdMinData,
                "borderColor" => "#9CA3AF",
                "backgroundColor" => "transparent",
                "tension" => 0.1,
                "pointRadius" => 0,
                "pointHoverRadius" => 2,
                "borderWidth" => 1,
            ];
        }

        if (! empty($stdMaxData)) {
            $datasets[] = [
                "label" => "Std Max",
                "data" => $stdMaxData,
                "borderColor" => "#9CA3AF",
                "backgroundColor" => "transparent",
                "tension" => 0.1,
                "pointRadius" => 0,
                "pointHoverRadius" => 2,
                "borderWidth" => 1,
            ];
        }

        if (! empty($stdMidData)) {
            $datasets[] = [
                "label" => "Std Mid",
                "data" => $stdMidData,
                "borderColor" => "#9CA3AF",
                "backgroundColor" => "transparent",
                "tension" => 0.1,
                "pointRadius" => 0,
                "pointHoverRadius" => 2,
                "borderWidth" => 1,
                "borderDash" => [5, 5], // Dashed line
            ];
        }

        return [
            "datasets" => $datasets,
        ];
    }

    private function getChartOptions(): array
    {
        return [
            "responsive" => true,
            "maintainAspectRatio" => false,
            "scales" => [
                "x" => [
                    "type" => "time",
                    "title" => [
                        "display" => true,
                        "text" => "Waktu",
                    ],
                ],
                "y" => [
                    "title" => [
                        "display" => true,
                        "text" => "Ketebalan (mm)",
                    ],
                    "min" => 0,
                    "max" => 6,
                ],
            ],
            "plugins" => [
                "datalabels" => [
                    "display" => true,
                    "anchor" => "end",
                    "align" => "top",
                ],
                "legend" => [
                    "display" => true,
                    "position" => "top",
                ],
                "zoom" => [
                    "zoom" => [
                        "wheel" => [
                            "enabled" => true,
                        ],
                        "pinch" => [
                            "enabled" => true,
                        ],
                        "mode" => "xy", // or 'y', 'xy'
                    ],
                    "pan" => [
                        "enabled" => true,
                        "mode" => "xy", // or 'y', 'xy'
                    ],
                ],
            ],
        ];
    }

    private function countCorrectionsByType($data): array
    {
        if (! $data || ! is_array($data)) {
            return [
                'thick_left' => 0,
                'thick_right' => 0,
                'thin_left' => 0,
                'thin_right' => 0,
            ];
        }

        $thickLeft = 0;
        $thickRight = 0;
        $thinLeft = 0;
        $thinRight = 0;

        foreach ($data as $point) {
            // Data format: [timestamp, is_correcting, action_left, action_right, sensor_left, sensor_right, ...]
            $actionLeft = isset($point[2]) ? (int)$point[2] : 0;
            $actionRight = isset($point[3]) ? (int)$point[3] : 0;

            // Left side corrections
            if ($actionLeft === 2) { // thick = menebalkan
                $thickLeft++;
            } elseif ($actionLeft === 1) { // thin = menipiskan
                $thinLeft++;
            }

            // Right side corrections
            if ($actionRight === 2) { // thick = menebalkan
                $thickRight++;
            } elseif ($actionRight === 1) { // thin = menipiskan
                $thinRight++;
            }
        }

        return [
            'thick_left' => $thickLeft,
            'thick_right' => $thickRight,
            'thin_left' => $thinLeft,
            'thin_right' => $thinRight,
        ];
    }

    private function handleNotFound(): void
    {
        $this->js('toast("' . __("Data metrik tidak ditemukan") . '", { type: "danger" })');
        $this->dispatch("updated");
    }
};

?>

<div class="p-6">
    @if ($header)
        <div class="flex justify-between items-start mb-6">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Rincian Batch") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Side: Chart + Data Table (2 columns) -->
        <div class="col-span-2 space-y-6">
            <!-- Chart Container -->
            <div class="h-80 overflow-hidden" id="batch-chart-container" wire:key="batch-chart-container" wire:ignore></div>

            <!-- Performance Data Table -->
            <table class="table table-xs text-sm text-center mt-6">
                <tr class="text-xs uppercase text-neutral-500 dark:text-neutral-400 border-b border-neutral-300 dark:border-neutral-700">
                    <td></td>
                    <td>{{ __("Ki") }}</td>
                    <td>{{ __("Ka") }}</td>
                    <td></td>
                    <td>{{ __("Evaluasi") }}</td>
                </tr>
                <!-- AVG Evaluation Row -->
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __("AVG") }}</td>
                    <td>{{ number_format($batch["t_avg_left"], 2) }}</td>
                    <td>{{ number_format($batch["t_avg_right"], 2) }}</td>
                    <td>{{ number_format($batch["t_avg"], 2) }}</td>
                    <td>
                        @php
                            $avgEval = $metric?->avg_evaluation;
                        @endphp

                        <div class="flex items-center gap-2">
                            <i class="{{ $avgEval["is_good"] ?? false ? "icon-circle-check text-green-500" : "icon-circle-x text-red-500" }}"></i>
                            <span class="{{ $avgEval["color"] ?? "" }} text-xs font-medium">{{ ucfirst($avgEval["status"] ?? "") }}</span>
                        </div>
                    </td>
                </tr>

                <!-- MAE Evaluation Row -->
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __("MAE") }}</td>
                    <td>{{ number_format($batch["t_mae_left"], 2) }}</td>
                    <td>{{ number_format($batch["t_mae_right"], 2) }}</td>
                    <td>{{ number_format($batch["t_mae"], 2) }}</td>
                    <td>
                        @php
                            $maeEval = $metric?->mae_evaluation;
                        @endphp

                        <div class="flex items-center gap-2">
                            <i class="{{ $maeEval["is_good"] ?? false ? "icon-circle-check text-green-500" : "icon-circle-x text-red-500" }}"></i>
                            <span class="{{ $maeEval["color"] ?? "" }} text-xs font-medium">{{ ucfirst($maeEval["status"] ?? "") }}</span>
                        </div>
                    </td>
                </tr>

                <!-- SSD Evaluation Row -->
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __("SSD") }}</td>
                    <td>{{ number_format($batch["t_ssd_left"], 2) }}</td>
                    <td>{{ number_format($batch["t_ssd_right"], 2) }}</td>
                    <td>{{ number_format($batch["t_ssd"], 2) }}</td>
                    <td>
                        @php
                            $ssdEval = $metric?->ssd_evaluation;
                        @endphp

                        <div class="flex items-center gap-2">
                            <i class="{{ $ssdEval["is_good"] ?? false ? "icon-circle-check text-green-500" : "icon-circle-x text-red-500" }}"></i>
                            <span class="{{ $ssdEval["color"] ?? "" }} text-xs font-medium">{{ ucfirst($ssdEval["status"] ?? "") }}</span>
                        </div>
                    </td>
                </tr>

                <!-- Correction Evaluation Row -->
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __("Koreksi") }}</td>
                    <td>{{ $batch["corrections_left"] }}</td>
                    <td>{{ $batch["corrections_right"] }}</td>
                    <td>{{ $batch["corrections_total"] }}</td>
                    <td>
                        @php
                            $correctionEval = $metric?->correction_evaluation;
                        @endphp

                        <div class="flex items-center gap-2">
                            <i class="{{ $correctionEval["is_good"] ?? false ? "icon-circle-check text-green-500" : "icon-circle-x text-red-500" }}"></i>
                            <span class="{{ $correctionEval["color"] ?? "" }} text-xs font-medium">{{ ucfirst($correctionEval["status"] ?? "") }}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Right Side: Info Panels (1 column) -->
        <div class="space-y-6">
            <!-- Batch Information -->
            <div>
                <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __("Informasi Batch") }}</div>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-neutral-500">{{ __("Batch:") }}</span>
                        <span class="font-medium">{{ $batch["rubber_batch_code"] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500">{{ __("MCS:") }}</span>
                        <span class="font-medium">{{ $batch["mcs"] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500">{{ __("Line:") }}</span>
                        <span class="font-medium">{{ $batch["machine_line"] }}</span>
                    </div>
                </div>
            </div>

            <!-- Timing Information -->
            <div>
                <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __("Waktu Proses") }}</div>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-neutral-500">{{ __("Mulai:") }}</span>
                        <span class="font-mono">{{ $batch["started_at"] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500">{{ __("Selesai:") }}</span>
                        <span class="font-mono">{{ $batch["ended_at"] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500">{{ __("Durasi:") }}</span>
                        <span class="font-mono">{{ $batch["duration"] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500">{{ __("Shift:") }}</span>
                        <span class="font-medium">{{ $batch["shift"] }}</span>
                    </div>
                </div>
            </div>

            <!-- Correction & Quality -->
            <div>
                <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __("Koreksi") }}</div>
                <div class="space-y-2 text-sm">
                    <div class="flex gap-x-3">
                        <div>
                            <span class="text-neutral-500">CU:</span>
                            <span class="font-mono">{{ $batch["correction_uptime"] }}%</span>
                        </div>
                        <div>
                            <span class="text-neutral-500">CR:</span>
                            <span class="font-mono">{{ $batch["correction_rate"] }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recipe Information -->
            <div>
                <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __("Resep") }}</div>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-neutral-500">{{ __("ID:") }}</span>
                        <span class="font-medium">{{ $batch["recipe_id"] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500">{{ __("Nama:") }}</span>
                        <span class="font-medium">{{ $batch["recipe_name"] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if ($this->canDownloadBatchCsv)
        <div class="pt-6 mt-6">
            <div class="flex flex-col sm:flex-row justify-end items-start sm:items-center gap-4">
                <x-secondary-button wire:click="downloadCsv" class="whitespace-nowrap">
                    <i class="icon-download"></i>
                    <span class="ml-2">{{ __("Download CSV") }}</span>
                </x-secondary-button>
            </div>
        </div>
    @endif
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
