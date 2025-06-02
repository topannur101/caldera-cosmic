<?php

namespace App;

use Carbon\Carbon;

class InsLdc
{
    /**
     * Generate Chart.js options for defect analysis (VN - QT)
     */
    public static function getDefectAnalysisChartOptions(array $hides): array
    {
        // Group hides by material and calculate defect percentages
        $materialData = [];
        
        foreach ($hides as $hide) {
            $material = $hide['group_material'] ?? 'Unknown';
            $defectPercent = $hide['area_vn'] > 0 ? (($hide['area_vn'] - $hide['area_qt']) / $hide['area_vn']) * 100 : 0;
            
            if (!isset($materialData[$material])) {
                $materialData[$material] = [
                    'defects' => [],
                    'total_vn' => 0,
                    'total_qt' => 0,
                    'count' => 0
                ];
            }
            
            $materialData[$material]['defects'][] = $defectPercent;
            $materialData[$material]['total_vn'] += $hide['area_vn'];
            $materialData[$material]['total_qt'] += $hide['area_qt'];
            $materialData[$material]['count']++;
        }
        
        // Calculate average defect percentage per material
        $labels = [];
        $data = [];
        $backgroundColors = [];
        $colors = [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)', 
            'rgba(255, 205, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)'
        ];
        
        $colorIndex = 0;
        foreach ($materialData as $material => $materialInfo) {
            $avgDefect = $materialInfo['total_vn'] > 0 ? 
                (($materialInfo['total_vn'] - $materialInfo['total_qt']) / $materialInfo['total_vn']) * 100 : 0;
            
            $labels[] = $material;
            $data[] = round($avgDefect, 2);
            $backgroundColors[] = $colors[$colorIndex % count($colors)];
            $colorIndex++;
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Defect Rate (%)'),
                        'data' => $data,
                        'backgroundColor' => $backgroundColors,
                        'borderColor' => array_map(fn($color) => str_replace('0.8', '1', $color), $backgroundColors),
                        'borderWidth' => 1
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Defect Rate by Material'),
                        'color' => session('bg') === 'dark' ? '#FFF' : '#000'
                    ],
                    'legend' => [
                        'display' => false
                    ]
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => __('Defect Rate (%)'),
                            'color' => session('bg') === 'dark' ? '#FFF' : '#000'
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                            'callback' => 'function(value) { return value + "%"; }'
                        ],
                        'grid' => [
                            'color' => session('bg') === 'dark' ? '#404040' : '#e5e5e5'
                        ]
                    ],
                    'x' => [
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ],
                        'grid' => [
                            'display' => false
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate Chart.js options for grade vs QT correlation
     */
    public static function getGradeQtCorrelationChartOptions(array $hides): array
    {
        // Group by grade and calculate average QT percentage
        $gradeData = [1 => [], 2 => [], 3 => [], 4 => [], 5 => []];
        
        foreach ($hides as $hide) {
            $grade = $hide['grade'] ?? null;
            if ($grade && isset($gradeData[$grade]) && $hide['area_vn'] > 0) {
                $qtPercent = ($hide['area_qt'] / $hide['area_vn']) * 100;
                $gradeData[$grade][] = $qtPercent;
            }
        }
        
        $labels = [];
        $data = [];
        
        foreach ($gradeData as $grade => $qtPercentages) {
            if (!empty($qtPercentages)) {
                $labels[] = __('Grade') . ' ' . $grade;
                $data[] = round(array_sum($qtPercentages) / count($qtPercentages), 2);
            }
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Average QT Percentage'),
                        'data' => $data,
                        'borderColor' => 'rgba(214, 69, 80, 1)',
                        'backgroundColor' => 'rgba(214, 69, 80, 0.1)',
                        'borderWidth' => 2,
                        'fill' => true,
                        'tension' => 0.4
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Grade vs QT Percentage Correlation'),
                        'color' => session('bg') === 'dark' ? '#FFF' : '#000'
                    ]
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'max' => 100,
                        'title' => [
                            'display' => true,
                            'text' => __('QT Percentage (%)'),
                            'color' => session('bg') === 'dark' ? '#FFF' : '#000'
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                            'callback' => 'function(value) { return value + "%"; }'
                        ],
                        'grid' => [
                            'color' => session('bg') === 'dark' ? '#404040' : '#e5e5e5'
                        ]
                    ],
                    'x' => [
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ],
                        'grid' => [
                            'display' => false
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate Chart.js options for measurement difference analysis (VN - AB)
     */
    public static function getMeasurementDifferenceChartOptions(array $hides): array
    {
        // Group by machine code (first 2 characters of hide code)
        $machineData = [];
        
        foreach ($hides as $hide) {
            $machineCode = substr($hide['code'], 0, 2); // XA, XB, XC
            $difference = $hide['area_vn'] - $hide['area_ab'];
            $diffPercent = $hide['area_vn'] > 0 ? ($difference / $hide['area_vn']) * 100 : 0;
            
            if (!isset($machineData[$machineCode])) {
                $machineData[$machineCode] = [];
            }
            
            $machineData[$machineCode][] = $diffPercent;
        }
        
        $labels = [];
        $avgData = [];
        $minData = [];
        $maxData = [];
        
        foreach ($machineData as $machine => $differences) {
            if (!empty($differences)) {
                $labels[] = $machine;
                $avgData[] = round(array_sum($differences) / count($differences), 2);
                $minData[] = round(min($differences), 2);
                $maxData[] = round(max($differences), 2);
            }
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Average Difference (%)'),
                        'data' => $avgData,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.8)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'borderWidth' => 1
                    ],
                    [
                        'label' => __('Min Difference (%)'),
                        'data' => $minData,
                        'backgroundColor' => 'rgba(75, 192, 192, 0.8)',
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'borderWidth' => 1
                    ],
                    [
                        'label' => __('Max Difference (%)'),
                        'data' => $maxData,
                        'backgroundColor' => 'rgba(255, 99, 132, 0.8)',
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                        'borderWidth' => 1
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('VN vs AB Measurement Difference by Machine'),
                        'color' => session('bg') === 'dark' ? '#FFF' : '#000'
                    ]
                ],
                'scales' => [
                    'y' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Difference (%)'),
                            'color' => session('bg') === 'dark' ? '#FFF' : '#000'
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                            'callback' => 'function(value) { return value + "%"; }'
                        ],
                        'grid' => [
                            'color' => session('bg') === 'dark' ? '#404040' : '#e5e5e5'
                        ]
                    ],
                    'x' => [
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3'
                        ],
                        'grid' => [
                            'display' => false
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate Chart.js options for quality distribution pie chart
     */
    public static function getQualityDistributionChartOptions(array $hides): array
    {
        $gradeCount = [
            'No Grade' => 0,
            'Grade 1' => 0,
            'Grade 2' => 0,
            'Grade 3' => 0,
            'Grade 4' => 0,
            'Grade 5' => 0
        ];
        
        foreach ($hides as $hide) {
            $grade = $hide['grade'] ?? null;
            if ($grade) {
                $gradeCount["Grade $grade"]++;
            } else {
                $gradeCount['No Grade']++;
            }
        }
        
        // Remove zero counts
        $gradeCount = array_filter($gradeCount);
        
        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => array_keys($gradeCount),
                'datasets' => [
                    [
                        'data' => array_values($gradeCount),
                        'backgroundColor' => [
                            'rgba(201, 203, 207, 0.8)', // No Grade - Gray
                            'rgba(75, 192, 92, 0.8)',   // Grade 1 - Green
                            'rgba(54, 162, 235, 0.8)',  // Grade 2 - Blue  
                            'rgba(255, 205, 86, 0.8)',  // Grade 3 - Yellow
                            'rgba(255, 159, 64, 0.8)',  // Grade 4 - Orange
                            'rgba(255, 99, 132, 0.8)'   // Grade 5 - Red
                        ],
                        'borderWidth' => 2,
                        'borderColor' => session('bg') === 'dark' ? '#374151' : '#ffffff'
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Quality Grade Distribution'),
                        'color' => session('bg') === 'dark' ? '#FFF' : '#000'
                    ],
                    'legend' => [
                        'position' => 'bottom',
                        'labels' => [
                            'color' => session('bg') === 'dark' ? '#FFF' : '#000'
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function getLinePerformanceChartOptions(array $lineStats): array
    {
        $lines = array_keys($lineStats);
        $throughput = array_column($lineStats, 'total_hides');
        $avgDefect = array_column($lineStats, 'avg_defect_rate');
        $avgDifference = array_column($lineStats, 'avg_difference_rate');
        $avgUtilization = array_column($lineStats, 'avg_utilization');

        return [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(fn($line) => "Line $line", $lines),
                'datasets' => [
                    [
                        'label' => __('Throughput (Hides)'),
                        'data' => $throughput,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.7)',
                        'yAxisID' => 'y'
                    ],
                    [
                        'label' => __('Avg Defect Rate (%)'),
                        'data' => $avgDefect,
                        'backgroundColor' => 'rgba(255, 99, 132, 0.7)',
                        'yAxisID' => 'y1'
                    ],
                    [
                        'label' => __('Avg Difference Rate (%)'),
                        'data' => $avgDifference,
                        'backgroundColor' => 'rgba(255, 206, 86, 0.7)',
                        'yAxisID' => 'y1'
                    ],
                    [
                        'label' => __('Avg Utilization (%)'),
                        'data' => $avgUtilization,
                        'backgroundColor' => 'rgba(75, 192, 192, 0.7)',
                        'yAxisID' => 'y1'
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'interaction' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
                'scales' => [
                    'x' => [
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ],
                    'y' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'left',
                        'title' => [
                            'display' => true,
                            'text' => __('Throughput'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ],
                    'y1' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'right',
                        'title' => [
                            'display' => true,
                            'text' => __('Percentage (%)'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'drawOnChartArea' => false,
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ]
                ],
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Line Performance Overview'),
                        'color' => session('bg') === 'dark' ? '#f5f5f5' : '#171717',
                    ],
                    'legend' => [
                        'display' => true,
                        'labels' => [
                            'color' => session('bg') === 'dark' ? '#a3a3a3' : '#525252',
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function getShiftPerformanceChartOptions(array $shiftStats): array
    {
        $shifts = array_keys($shiftStats);
        $throughput = array_column($shiftStats, 'total_hides');
        $avgDefect = array_column($shiftStats, 'avg_defect_rate');
        $avgDifference = array_column($shiftStats, 'avg_difference_rate');

        return [
            'type' => 'radar',
            'data' => [
                'labels' => array_map(fn($shift) => __('Shift') . " $shift", $shifts),
                'datasets' => [
                    [
                        'label' => __('Throughput (Normalized)'),
                        'data' => self::normalizeArray($throughput),
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    ],
                    [
                        'label' => __('Quality Score (100 - Defect Rate)'),
                        'data' => array_map(fn($rate) => 100 - $rate, $avgDefect),
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    ],
                    [
                        'label' => __('Accuracy Score (100 - Difference Rate)'),
                        'data' => array_map(fn($rate) => 100 - $rate, $avgDifference),
                        'borderColor' => 'rgba(255, 206, 86, 1)',
                        'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'r' => [
                        'beginAtZero' => true,
                        'max' => 100,
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'color' => session('bg') === 'dark' ? '#404040' : '#e5e5e5',
                        ],
                        'angleLines' => [
                            'color' => session('bg') === 'dark' ? '#404040' : '#e5e5e5',
                        ]
                    ]
                ],
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Shift Performance Comparison'),
                        'color' => session('bg') === 'dark' ? '#f5f5f5' : '#171717',
                    ],
                    'legend' => [
                        'display' => true,
                        'labels' => [
                            'color' => session('bg') === 'dark' ? '#a3a3a3' : '#525252',
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function getThroughputTrendChartOptions(array $dailyStats): array
    {
        $dates = array_keys($dailyStats);
        $throughput = array_column($dailyStats, 'total_hides');
        $avgDefect = array_column($dailyStats, 'avg_defect_rate');

        return [
            'type' => 'line',
            'data' => [
                'labels' => array_map(fn($date) => Carbon::parse($date)->format('M d'), $dates),
                'datasets' => [
                    [
                        'label' => __('Daily Throughput'),
                        'data' => $throughput,
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'backgroundColor' => 'rgba(54, 162, 235, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                        'yAxisID' => 'y'
                    ],
                    [
                        'label' => __('Daily Avg Defect Rate (%)'),
                        'data' => $avgDefect,
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                        'backgroundColor' => 'rgba(255, 99, 132, 0.1)',
                        'fill' => false,
                        'tension' => 0.4,
                        'yAxisID' => 'y1'
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'interaction' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
                'scales' => [
                    'x' => [
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ],
                    'y' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'left',
                        'title' => [
                            'display' => true,
                            'text' => __('Throughput'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ],
                    'y1' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'right',
                        'title' => [
                            'display' => true,
                            'text' => __('Defect Rate (%)'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'drawOnChartArea' => false,
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ]
                ],
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Daily Production Trends'),
                        'color' => session('bg') === 'dark' ? '#f5f5f5' : '#171717',
                    ],
                    'legend' => [
                        'display' => true,
                        'labels' => [
                            'color' => session('bg') === 'dark' ? '#a3a3a3' : '#525252',
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function getStyleAnalysisChartOptions(array $styleStats): array
    {
        $styles = array_slice(array_keys($styleStats), 0, 10); // Top 10 styles
        $efficiency = array_slice(array_column($styleStats, 'avg_utilization'), 0, 10);
        $defectRates = array_slice(array_column($styleStats, 'avg_defect_rate'), 0, 10);
        $volume = array_slice(array_column($styleStats, 'total_hides'), 0, 10);

        return [
            'type' => 'scatter',
            'data' => [
                'datasets' => [
                    [
                        'label' => __('Style Performance'),
                        'data' => array_map(function($i) use ($efficiency, $defectRates, $volume, $styles) {
                            return [
                                'x' => $efficiency[$i],
                                'y' => $defectRates[$i],
                                'r' => max(5, min(25, $volume[$i] / 10)), // Bubble size based on volume
                                'style' => $styles[$i]
                            ];
                        }, array_keys($styles)),
                        'backgroundColor' => array_map(function($i) {
                            $colors = [
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 206, 86, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(153, 102, 255, 0.7)',
                                'rgba(255, 159, 64, 0.7)',
                                'rgba(199, 199, 199, 0.7)',
                                'rgba(83, 102, 255, 0.7)',
                                'rgba(255, 99, 255, 0.7)',
                                'rgba(99, 255, 132, 0.7)'
                            ];
                            return $colors[$i % count($colors)];
                        }, array_keys($styles))
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Utilization Rate (%)'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ],
                    'y' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Defect Rate (%)'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ]
                ],
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Style Efficiency vs Quality (Bubble size = Volume)'),
                        'color' => session('bg') === 'dark' ? '#f5f5f5' : '#171717',
                    ],
                    'legend' => [
                        'display' => false
                    ],
                    'tooltip' => [
                        'callbacks' => [
                            'label' => 'function(context) {
                                const data = context.parsed;
                                const style = context.raw.style;
                                return style + ": " + data.x.toFixed(1) + "% util, " + data.y.toFixed(1) + "% defect";
                            }'
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function getMaterialAnalysisChartOptions(array $materialStats): array
    {
        $materials = array_keys($materialStats);
        $utilization = array_column($materialStats, 'avg_utilization');
        $defectRates = array_column($materialStats, 'avg_defect_rate');
        $wasteRates = array_column($materialStats, 'avg_waste_rate');

        return [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(function($material) {
                    return strlen($material) > 20 ? substr($material, 0, 17) . '...' : $material;
                }, $materials),
                'datasets' => [
                    [
                        'label' => __('Utilization Rate (%)'),
                        'data' => $utilization,
                        'backgroundColor' => 'rgba(75, 192, 192, 0.7)',
                    ],
                    [
                        'label' => __('Defect Rate (%)'),
                        'data' => $defectRates,
                        'backgroundColor' => 'rgba(255, 99, 132, 0.7)',
                    ],
                    [
                        'label' => __('Selisih VN-AB (%)'),
                        'data' => $wasteRates,
                        'backgroundColor' => 'rgba(255, 206, 86, 0.7)',
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'indexAxis' => 'y',
                'scales' => [
                    'x' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => __('Percentage (%)'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ],
                    'y' => [
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ]
                ],
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Material Performance Analysis'),
                        'color' => session('bg') === 'dark' ? '#f5f5f5' : '#171717',
                    ],
                    'legend' => [
                        'display' => true,
                        'labels' => [
                            'color' => session('bg') === 'dark' ? '#a3a3a3' : '#525252',
                        ]
                    ]
                ]
            ]
        ];
    }

    private static function normalizeArray(array $data): array
    {
        if (empty($data)) return [];
        
        $max = max($data);
        $min = min($data);
        $range = $max - $min;
        
        if ($range == 0) return array_fill(0, count($data), 50);
        
        return array_map(function($value) use ($min, $range) {
            return round((($value - $min) / $range) * 100, 2);
        }, $data);
    }

    public static function getMachineAccuracyChartOptions(array $machineData): array
    {
        $labels = [];
        $avgVarianceData = [];
        $colors = [];
        
        foreach ($machineData as $machine => $data) {
            $labels[] = $machine;
            $avgVarianceData[] = $data['avg_variance'];
            
            // Color coding based on variance level
            if ($data['avg_variance'] > 2.0) {
                $colors[] = 'rgba(255, 99, 132, 0.8)'; // Red for high variance
            } elseif ($data['avg_variance'] > 1.0) {
                $colors[] = 'rgba(255, 205, 86, 0.8)'; // Yellow for medium variance
            } else {
                $colors[] = 'rgba(75, 192, 192, 0.8)'; // Green for low variance
            }
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Rata-rata Varians (SF)'),
                        'data' => $avgVarianceData,
                        'backgroundColor' => $colors,
                        'borderColor' => array_map(function($color) {
                            return str_replace('0.8', '1', $color);
                        }, $colors),
                        'borderWidth' => 1
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Akurasi Pengukuran per Mesin (VN vs AB)')
                    ],
                    'legend' => [
                        'display' => false
                    ]
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => __('Varians Rata-rata (SF)')
                        ]
                    ],
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Mesin')
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Machine Utilization Chart Options
     */
    public static function getMachineUtilizationChartOptions(array $machineData): array
    {
        $labels = [];
        $volumeData = [];
        $colors = ['rgba(54, 162, 235, 0.8)', 'rgba(255, 99, 132, 0.8)', 'rgba(255, 205, 86, 0.8)'];

        foreach ($machineData as $machine => $data) {
            $labels[] = $machine;
            $volumeData[] = $data['total_hides'];
        }

        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => $volumeData,
                        'backgroundColor' => array_slice($colors, 0, count($labels)),
                        'borderWidth' => 2
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Distribusi Volume per Mesin')
                    ],
                    'legend' => [
                        'position' => 'right'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Machine Quality Output Chart Options
     */
    public static function getMachineQualityChartOptions(array $machineData): array
    {
        $labels = [];
        $qtPercentageData = [];

        foreach ($machineData as $machine => $data) {
            $labels[] = $machine;
            $qtPercentageData[] = $data['avg_qt_percentage'];
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Rata-rata QT (%)'),
                        'data' => $qtPercentageData,
                        'backgroundColor' => 'rgba(153, 102, 255, 0.8)',
                        'borderColor' => 'rgba(153, 102, 255, 1)',
                        'borderWidth' => 1
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Kualitas Output per Mesin (Area QT)')
                    ],
                    'legend' => [
                        'display' => false
                    ]
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'max' => 100,
                        'title' => [
                            'display' => true,
                            'text' => __('Persentase QT (%)')
                        ]
                    ],
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Mesin')
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Accuracy Trend Chart Options
     */
    public static function getAccuracyTrendChartOptions(array $trendData): array
    {
        $labels = [];
        $datasets = [];
        $colors = ['rgba(255, 99, 132, 1)', 'rgba(54, 162, 235, 1)', 'rgba(255, 205, 86, 1)'];
        $colorIndex = 0;

        // Group data by machine
        $machineGroups = [];
        foreach ($trendData as $data) {
            $machine = $data['machine'];
            if (!isset($machineGroups[$machine])) {
                $machineGroups[$machine] = [];
            }
            $machineGroups[$machine][] = $data;
        }

        // Get all unique dates for labels
        $allDates = [];
        foreach ($trendData as $data) {
            $allDates[] = $data['date'];
        }
        $labels = array_unique($allDates);
        sort($labels);

        // Create dataset for each machine
        foreach ($machineGroups as $machine => $machineData) {
            $dataPoints = [];
            
            foreach ($labels as $date) {
                $found = false;
                foreach ($machineData as $point) {
                    if ($point['date'] === $date) {
                        $dataPoints[] = $point['variance'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $dataPoints[] = null;
                }
            }

            $datasets[] = [
                'label' => $machine,
                'data' => $dataPoints,
                'borderColor' => $colors[$colorIndex % count($colors)],
                'backgroundColor' => str_replace('1)', '0.2)', $colors[$colorIndex % count($colors)]),
                'fill' => false,
                'tension' => 0.1
            ];
            $colorIndex++;
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => $datasets
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('Tren Akurasi Mesin dari Waktu ke Waktu')
                    ],
                    'legend' => [
                        'position' => 'top'
                    ]
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => __('Varians (SF)')
                        ]
                    ],
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Tanggal')
                        ]
                    ]
                ]
            ]
        ];
    }
}