<?php

namespace App;

use Illuminate\Support\Carbon;

class InsOmv
{
    public static function getRunningTimeChartOptions($data)
    {
        $lines = array_map(function($line) {
            return "Line " . intval($line);
        }, array_keys($data->toArray()));
        
        return [
            'series' => [
                [
                    'name' => __('Terlalu awal'),
                    'data' => $data->pluck('too_soon')->values(),
                    'color' => '#FF8080',  // Darker shade of pastel red
                ],
                [
                    'name' => __('Tepat waktu'),
                    'data' => $data->pluck('on_time')->values(),
                    'color' => '#80CC80',  // Darker shade of pastel green
                ],
                [
                    'name' => __('Tepat waktu') . ' (' . __('manual') .')',
                    'data' => $data->pluck('on_time_manual')->values(),
                    'color' => '#FFB366',  // Darker shade of pastel orange
                ],
                [
                    'name' => __('Terlambat'),
                    'data' => $data->pluck('too_late')->values(),
                    'color' => '#FF8080',  // Same darker shade of pastel red
                ],
            ],
            'chart' => [
                'type' => 'bar',
                'height' => '100%',
                'background' => 'transparent',
                'stacked' => true,
                'toolbar' => [
                    'show' => true,
                    'tools' => [
                        'download' => '<img src="/icon-download.svg" width="18">',
                        'zoom' => '<img src="/icon-zoom-in.svg" width="18">',
                        'zoomin' => false,
                        'zoomout' => false,
                        'pan' => '<img src="/icon-hand.svg" width="20">',
                        'reset' => '<img src="/icon-zoom-out.svg" width="18">',
                    ],
                ],
            ],
            'dataLabels' => [
                'formatter' => null,
                'background' => [
                    'enabled' => false,
                ]
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => true,
                    'dataLabels' => [
                        'total' => [
                            'enabled' => true,
                            'offsetX' => 0,
                            'style' => [
                                'fontSize' => '13px',
                                'fontWeight' => 900,
                                'color' => session('bg') == 'dark' ? '#FFF' : null
                            ],
                        ],
                    ],
                ],
            ],
            'stroke' => [
                'width' => 0
            ],
            'theme' => [
                'mode' => session('bg'),
            ],
            'tooltip' => [
                'y'=> [
                    'formatter' => null
                ],
            ],
            'xaxis' => [
                'categories' => $lines,
                'title' => [
                    'text' => __('Jam'),
                ],
                'labels' => [
                    'formatter' => null,
                ],
            ],
            'fill' => [
                'opacity' => 1,
            ],
            'legend' => [
                'position' => 'top',
                'horizontalAlign' => 'left',
                'offsetX' => 40,
                'markers' => [
                    'strokeWidth' => 0,
                ]
            ],
        ];
    }

    public static function getChartOptions(array $amps, Carbon $start_at, array $step_durations, array $capture_points, int $height) 
    {
        // Create a base datetime at 00:00:00
        $base_time = Carbon::today();

        $chart_data = array_merge(
            array_map(function ($amp) use ($base_time) {
                // Add the seconds to 00:00:00
                $taken_at = $base_time->copy()->addSeconds($amp['taken_at'])->timestamp * 1000;
                
                return [
                    'taken_at' => $taken_at,
                    'value' => $amp['value'],
                ];
            }, $amps)
        );
        array_unshift($step_durations, 0);
    
        $x_annos = array_map(function($duration, $index) use ($base_time, $step_durations) {
            $timestamp = $base_time->copy()->addSeconds($duration)->timestamp * 1000;
            $isLast = $index === array_key_last($step_durations);
            
            return [
                'x' => $timestamp,
                'borderColor' => $isLast ? '#FF0000' : '#008080',
                'label' => [
                    'borderWidth' => 0,
                    'style' => [
                        'background' => $isLast ? '#FF0000' : '#008080',
                        'color' => '#fff',
                    ],
                    'text' => $isLast ? __('Standar') : __('Langkah') . ' ' . ($index + 1),
                ],
            ];
        }, $step_durations, array_keys($step_durations));
    
        return [
            'chart' => [
                'redrawOnParentResize' => true,
                'background' => 'transparent',
                'height' => $height .'%',
                'type' => 'line',
                'toolbar' => [
                    'show' => true,
                    'tools' => [
                        'download' => '<img src="/icon-download.svg" width="18">',
                        'zoom' => '<img src="/icon-zoom-in.svg" width="18">',
                        'zoomin' => false,
                        'zoomout' => false,
                        'pan' => '<img src="/icon-hand.svg" width="20">',
                        'reset' => '<img src="/icon-zoom-out.svg" width="18">',
                    ],
                ],
                'animations' => [
                    'enabled' => true,
                    'easing' => 'easeout',
                    'speed' => 400,
                    'animateGradually' => [
                        'enabled' => false,
                    ],
                ],
            ],
            'series' => [
                [
                    'name' => __('Arus listrik'),
                    'data' => array_map(function($item) {
                        return ['x' => $item['taken_at'], 'y' => $item['value']];
                    }, $chart_data),
                    'color' => '#D64550',
                ],
            ],
            'theme' => [
                'mode' => session('bg'),
            ],
            'xaxis' => [
                'type' => 'datetime',
                'labels' => [
                    'datetimeUTC' => true,
                    'datetimeFormatter' => [
                        'year' => 'yyyy',
                        'month' => "MMM 'yy",
                        'day' => 'dd MMM',
                        'hour' => 'mm:ss',  // Changed to show minutes:seconds
                        'minute' => 'mm:ss', // Changed to show minutes:seconds
                        'second' => 'mm:ss'  // Changed to show minutes:seconds
                    ],
                ],
            ],
            'yaxis' => [
                'min' => 0,
                'max' => 300,
                'title' => [
                    'text' => __('Arus listrik'),
                ]
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 1,
            ],
            'annotations' => [
                'xaxis' => $x_annos,
            ],
            'tooltip' => [
                'x' => [
                    'format' => 'mm:ss'
                ]
            ]
        ];
    }
    
}
