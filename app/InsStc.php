<?php

namespace App;

use Carbon\Carbon;
use InvalidArgumentException;

class InsStc
{
    private static array $sectionRatios = [
        'preheat' => 0.09,
        'section_1' => 0.10,
        'section_2' => 0.10,
        'section_3' => 0.10,
        'section_4' => 0.10,
        'section_5' => 0.10,
        'section_6' => 0.10,
        'section_7' => 0.10,
        'section_8' => 0.10,
        'postheat' => 0.09,
    ];

    public static function getMediansbySection(array $values): array
    {
        // Validate input values
        if (empty($values)) {
            throw new InvalidArgumentException('Values array cannot be empty.');
        }

        $totalValues = count($values);
        $sections = [];
        $startIndex = 0;

        // Divide values into sections based on default ratios
        foreach (self::$sectionRatios as $section => $sectionRatio) {
            $sectionCount = (int) floor($totalValues * $sectionRatio);
            $sections[$section] = array_slice($values, $startIndex, $sectionCount);
            $startIndex += $sectionCount;
        }

        // Calculate medians for each section
        $medians = [];
        foreach ($sections as $section => $sectionValues) {
            if (!empty($sectionValues)) {
                sort($sectionValues); // Sort the section values to calculate median
                $count = count($sectionValues);
                $middle = (int) floor($count / 2);

                if ($count % 2 === 0) {
                    // Even count: Average the two middle values
                    $median = ($sectionValues[$middle - 1] + $sectionValues[$middle]) / 2;
                } else {
                    // Odd count: Take the middle value
                    $median = $sectionValues[$middle];
                }

                $medians[$section] = $median;
            } else {
                $medians[$section] = null; // No data in this section
            }
        }
        return $medians;
    }

    public static function flattenDLogs(array $dLogs): array
    {
        // Sort the dlogs array by the 'taken_at' field
        usort($dLogs, function ($a, $b) {
            return strtotime($a['taken_at']) <=> strtotime($b['taken_at']);
        });

        // Extract the 'temp' values from the sorted array
        return array_map(function ($dLog) {
            return $dLog['temp'];
        }, $dLogs);

    }

    public static function getMediansfromDLogs(array $dLogs): array
    {
        $flattenedDLogs = self::flattenDLogs($dLogs);
        $medians = self::getMediansBySection($flattenedDLogs);
        return $medians;
    }

    public static function getDLogsChartOptions($data) 
    {
        return [
            'chart' => [
                'type' => 'line',
                'animations' => [
                    'enabled' => false, // Disable animations for better performance with multiple series
                ],
                'toolbar' => [
                    'show' => true,
                    'tools' => [
                        'download' => true,
                        'selection' => true,
                        'zoom' => true,
                        'zoomin' => true,
                        'zoomout' => true,
                        'pan' => true,
                        'reset' => true,
                    ],
                ],
            ],
            'series' => $data,
            'xaxis' => [
                'type' => 'numeric',
                'labels' => [
                    'datetimeUTC' => false,
                ],
                'title' => [
                    'text' => 'Time (HH:MM)'
                ],
                'tickAmount' => 10,
            ],
            'yaxis' => [
                'title' => [
                    'text' => 'Temperature (°C)',
                ],
                'decimalsInFloat' => 1,
            ],
            'tooltip' => [
                'enabled' => true,
                'shared' => false,
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2,
            ],
            'legend' => [
                'show' => true,
                'position' => 'top',
                'horizontalAlign' => 'center',
            ],
            'grid' => [
                'borderColor' => '#e7e7e7',
                'row' => [
                    'colors' => ['#f3f3f3', 'transparent'],
                    'opacity' => 0.5
                ],
            ],
        ];
    }

    public static function getChartOptions($logs, $xzones, $yzones, $ymax, $ymin, $width, $height)
    {
        $chartData = array_map(function ($log) {
            return [Self::parseDate($log['taken_at']), $log['temp']];
        }, $logs);
        $chartDataJs = json_encode($chartData);

        return [
            'chart' => [
                'redrawOnParentResize' => true,
                'width' => $width . '%',
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
        $zoneOrder = ['preheat', 'zone_1', 'zone_2', 'zone_3', 'zone_4', 'postheat'];
        
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

    public static function sections(string $axis): array
    {
        switch ($axis) {
            case 'x':
                return [
                    'preheat'   => 5,
                    'section_1' => 6,
                    'section_2' => 6,
                    'section_3' => 6,
                    'section_4' => 6,
                    'section_5' => 6,
                    'section_6' => 6,
                    'section_7' => 6,
                    'section_8' => 6,
                    'postheat'  => 5
                ];
                break;
            case 'y':
                return [ 40, 50, 60, 70, 80 ];
                break;
            
            default:
                return [];
                break;
        }        
    }

    public static function zones(string $axis): array
    {
        switch ($axis) {
            case 'x':
                return [
                    'preheat'   => 5,
                    'zone_1'    => 12,
                    'zone_2'    => 12,
                    'zone_3'    => 12,
                    'zone_4'    => 12,
                    'postheat'  => 5
                ];
                break;
            case 'y':
                return [ 40, 50, 60, 70, 80 ];
                break;
            
            default:
                return [];
                break;
        }
    }
    
}
