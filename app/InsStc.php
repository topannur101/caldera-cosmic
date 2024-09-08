<?php

namespace App;

use Carbon\Carbon;

class InsStc
{
    public static function getChartOptions($logs, $xzones, $yzones, $ymax, $ymin)
    {
        $chartData = array_map(function ($log) {
            return [Self::parseDate($log['taken_at']), $log['temp']];
        }, $logs);
        $chartDataJs = json_encode($chartData);

        return [
            'chart' => [
                'height' => '100%',
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
                    'name' => __('Suhu'),
                    'data' => json_decode($chartDataJs, true),
                    'color' => '#00BBF9',
                ],
            ],
            'xaxis' => [
                'type' => 'datetime',
                'labels' => [
                    'datetimeUTC' => false,
                ],
            ],
            'yaxis' => [
                'title' => [
                    'text' => '°C',
                ],
                'max' => $ymax,
                'min' => $ymin,
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 1,
            ],
            'tooltip' => [
                'x' => [
                    'format' => 'dd MMM yyyy HH:mm',
                ],
            ],
            'annotations' => [
                'xaxis' => self::generateXAnnotations($xzones, $logs),
                'yaxis' => self::generateYAnnotations($yzones),
                'points' => self::generatePointAnnotations($xzones, $yzones, $logs),
            ],
            'grid' => [
                'yaxis' => [
                    'lines' => [
                        'show' => false,
                    ],
                ],
            ],
        ];
    }

    public static function extractTemps(array $data, string $key): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            return [];
        }
    
        return array_map(function($item) {
            return isset($item[3]) && is_numeric($item[3]) ? (float)$item[3] : null;
        }, $data[$key]);
    }
    

    private static function generateXAnnotations($xzones, $logs)
    {
        $annotations = [];
        $previousCount = 0;

        foreach ($xzones as $zone => $count) {
            if ($count > 0) {
                $position = $previousCount + $count;

                if (isset($logs[$position])) {
                    $annotations[] = [
                        'x' => self::parseDate($logs[$position]['taken_at']),
                        'borderColor' => '#bcbcbc',
                        'label' => [
                            'style' => [
                                'color' => 'transparent',
                                'background' => 'transparent',
                            ],
                            'text' => '',
                        ],
                    ];
                }

                $previousCount += $count;
            }
        }

        return $annotations;
    }

    private static function generateYAnnotations($yzones)
    {
        $annotations = [];
        foreach ($yzones as $index => $value) {
            $annotations[] = [
                'y' => $value,
                'borderColor' => '#bcbcbc',
                'label' => [
                    'borderColor' => 'transparent',
                    'style' => [
                        'color' => '#bcbcbc',
                        'background' => 'transparent',
                    ],
                    'text' => $value . '°C',
                ],
            ];
        }
        return $annotations;
    }

    private static function generatePointAnnotations($xzones, $yzones, $logs)
    {
        $pointAnnotations = [];
        $preheatCount = $xzones['preheat'];
        $currentIndex = $preheatCount;
        $zoneNames = ['zone_1', 'zone_2', 'zone_3', 'zone_4'];
        $yzonesReversed = array_reverse($yzones);

        foreach ($zoneNames as $index => $zoneName) {
            $zoneCount = $xzones[$zoneName];
            $midpointIndex = $currentIndex + floor($zoneCount / 2);

            if (isset($logs[$midpointIndex])) {
                $yValue = ($yzonesReversed[$index] + $yzonesReversed[$index + 1]) / 2;

                $pointAnnotations[] = [
                    'x' => self::parseDate($logs[$midpointIndex]['taken_at']),
                    'y' => $yValue,
                    'marker' => [
                        'size' => 0,
                        'strokeWidth' => 0,
                    ],
                    'label' => [
                        'borderWidth' => 0,
                        'text' => 'Z' . ($index + 1) . ': ',
                        'style' => [
                            'background' => '#00BBF9',
                            'color' => '#ffffff',
                        ],
                    ],
                ];
            }

            $currentIndex += $zoneCount;
        }

        return $pointAnnotations;
    }

    public static function parseDate($dateString)
    {
        return Carbon::parse($dateString)->timestamp * 1000;
    }

    public static function sliceZoneData(array $data, array $xzones, string $targetZone): ?array
    {
        $zoneOrder = ['preheat', 'zone_1', 'zone_2', 'zone_3', 'zone_4'];
    
        if (!isset($xzones[$targetZone])) {
            return null; // Target zone not found
        }
    
        $startIndex = 0;
        foreach ($zoneOrder as $zone) {
            if ($zone === $targetZone) {
                break;
            }
            if (isset($xzones[$zone])) {
                $startIndex += $xzones[$zone];
            }
        }
    
        if ($startIndex >= count($data)) {
            return null; // Start index out of bounds
        }
    
        $length = $xzones[$targetZone];
        return array_slice($data, $startIndex, $length);
    }
    
}
