<?php

namespace App;

use Carbon\Carbon;
use InvalidArgumentException;

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
                'redrawOnParentResize' => true,
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
                    'color' => '#D64550',
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
            $slicedZone = InsStc::sliceZoneData($logs, $xzones, $zoneName);

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
                        'text' => 'Z' . ($index + 1) . ': ' . InsStc::medianTemp($slicedZone),
                        'style' => [
                            'background' => '#D64550',
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

    public static function sliceZoneData(array $logs, array $xzones, string $selectedZone): array
    {
        $zoneOrder = ['preheat', 'zone_1', 'zone_2', 'zone_3', 'zone_4'];
        
        if (!in_array($selectedZone, $zoneOrder)) {
            throw new InvalidArgumentException("Invalid zone selected: $selectedZone");
        }
        
        $startIndex = 0;
        $endIndex = 0;
        
        foreach ($zoneOrder as $zone) {
            if (!isset($xzones[$zone])) {
                continue;
            }
            
            $zoneSize = $xzones[$zone];
            $endIndex = $startIndex + $zoneSize;
            
            if ($zone === $selectedZone) {
                // Ensure we don't exceed the array bounds
                $endIndex = min($endIndex, count($logs));
                return array_slice($logs, $startIndex, $endIndex - $startIndex);
            }
            
            $startIndex = $endIndex;
        }
        
        // If we get here, the selected zone wasn't found or had no logs
        return [];
    }

    public static function medianTemp(array $data): float
    {
        $temperatures = array_map(function($item) {
            return is_numeric($item['temp']) ? (float)$item['temp'] : null;
        }, $data);
    
        $temperatures = array_filter($temperatures, function($temp) {
            return $temp !== null;
        });
    
        $count = count($temperatures);
    
        if ($count === 0) {
            return 0;
        }
    
        sort($temperatures);
    
        $middle = floor($count / 2);
    
        if ($count % 2 === 0) {
            return ($temperatures[$middle - 1] + $temperatures[$middle]) / 2;
        } else {
            return $temperatures[$middle];
        }
    }

    public static function duration($start_time, $end_time): string
    {
        $x = Carbon::parse($start_time);
        $y = Carbon::parse($end_time);
        return $x->diff($y)->forHumans([
            'parts' => 2,
            'join' => true,
            'short' => false,
        ]);
    }

    public static function positionHuman(string $position): string
    {
        $positionHuman = __('Tak diketahui');
        switch ($position) {
            case 'upper':
                $positionHuman = __('Atas');
                break;
            case 'lower':
                $positionHuman = __('Bawah');
                break;
        }
        return $positionHuman;
    }
    
}
