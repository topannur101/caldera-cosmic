<?php

namespace App;

use Carbon\Carbon;
use InvalidArgumentException;

class InsStc
{
    private static array $sectionRatios = [
        'preheat' => 0.08,
        'section_1' => 0.11,
        'section_2' => 0.11,
        'section_3' => 0.11,
        'section_4' => 0.11,
        'section_5' => 0.11,
        'section_6' => 0.11,
        'section_7' => 0.11,
        'section_8' => 0.11,
        'postheat' => 0.06,
    ];

    public static function groupValuesBySection($values): array
    {
        $totalValues = count($values);
        $sections = [];
        $startIndex = 0;

        // Divide values into sections based on default ratios
        foreach (self::$sectionRatios as $section => $sectionRatio) {
            $sectionCount = (int) round($totalValues * $sectionRatio, 0);
            $sections[$section] = array_slice($values, $startIndex, $sectionCount);
            $startIndex += $sectionCount;
        }

        return $sections;
    }

    public static function getMediansBySection(array $values): array
    {
        // Validate input values
        if (empty($values)) {
            throw new InvalidArgumentException('Values array cannot be empty.');
        }
        
        $sections = self::groupValuesBySection($values);

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

                $medians[$section] = (int) round($median, 0);
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

    public static function calculateSVP(array $hb_values, array $sv_values, int $formula_id): array
    {
        $HBTargets = [ 77.5, 72.5, 67.5, 62.5, 57.5, 52.5, 47.5, 42.5 ];

        // Validate input arrays have same length
        if (count($hb_values) !== count($HBTargets) || count($sv_values) !== count($HBTargets)) {
            throw new \InvalidArgumentException('Input arrays must match HB Targets length');
        }

        $svp_results = [];

        foreach ($hb_values as $index => $hb_value) {
            $hb_target = $HBTargets[$index];
            $sv_value = (int) $sv_values[$index];

            // Handle special case for zero SV values
            if ($sv_value == 0) {
                $svp_results[] = [
                    'absolute' => 0,
                    'relative' => ''
                ];
                continue;
            }

            $adjusted_sv = $sv_value;
            $relative = 0;

            switch ($formula_id) {
                case '411':
                    $diff = $hb_value - $hb_target;
                    $adjusted_sv = (int) max(0, round($sv_value - $diff, 0));
                    break;
                case '412':
                    $diff = ($hb_value - $hb_target) / 2;
                    $adjusted_sv = (int) max(0, round($sv_value - $diff, 0));
                    break;
                
                case '421':
                    if ($hb_value < $hb_target) {
                        $ratio = $hb_value > 0 ? ($hb_value / ($hb_target > 0 ? $hb_target : $hb_value)) : 0;
                        $adjusted_sv = (int) max(0, round($sv_value + ($sv_value * (1 - $ratio))));
                    } else if ($hb_value > $hb_target) {
                        $ratio = $hb_target > 0 ? ($hb_target / ($hb_value > 0 ? $hb_value : $hb_target)) : 0;
                        $adjusted_sv = (int) max(0, round($sv_value - ($sv_value * (1 - $ratio))));
                    }
                    
                    break;
            }

            // Calculate relative difference between adjusted and original SV
            $relative = $adjusted_sv - $sv_value;
            $relative = $relative > 0 ? '+' . abs($relative) : ( $relative < 0 ? '-' . abs($relative) : '' );

            $svp_results[] = [
                'absolute' => $adjusted_sv,
                'relative' => $relative ?: null
            ];
        }

        return $svp_results;
    }

    public static function getStandardZoneChartOptions($chartData, $width, $height)
    {
        $ymax = 85;
        $ymin = 35;

        $logs = [ [ 'taken_at' => 1*60, 'temp' => 75 ], [ 'taken_at' => 2*60, 'temp' => 75 ], [ 'taken_at' => 3*60, 'temp' => 75 ], [ 'taken_at' => 4*60, 'temp' => 75 ], [ 'taken_at' => 5*60, 'temp' => 75 ], [ 'taken_at' => 6*60, 'temp' => 75 ], [ 'taken_at' => 7*60, 'temp' => 75 ], [ 'taken_at' => 8*60, 'temp' => 75 ], [ 'taken_at' => 9*60, 'temp' => 75 ], [ 'taken_at' => 10*60, 'temp' => 75 ], [ 'taken_at' => 11*60, 'temp' => 75 ], [ 'taken_at' => 12*60, 'temp' => 75 ], [ 'taken_at' => 13*60, 'temp' => 75 ], [ 'taken_at' => 14*60, 'temp' => 75 ], [ 'taken_at' => 15*60, 'temp' => 75 ], [ 'taken_at' => 16*60, 'temp' => 75 ], [ 'taken_at' => 17*60, 'temp' => 75 ], [ 'taken_at' => 18*60, 'temp' => 65 ], [ 'taken_at' => 19*60, 'temp' => 65 ], [ 'taken_at' => 20*60, 'temp' => 65 ], [ 'taken_at' => 21*60, 'temp' => 65 ], [ 'taken_at' => 22*60, 'temp' => 65 ], [ 'taken_at' => 23*60, 'temp' => 65 ], [ 'taken_at' => 24*60, 'temp' => 65 ], [ 'taken_at' => 25*60, 'temp' => 65 ], [ 'taken_at' => 26*60, 'temp' => 65 ], [ 'taken_at' => 27*60, 'temp' => 65 ], [ 'taken_at' => 28*60, 'temp' => 65 ], [ 'taken_at' => 29*60, 'temp' => 65 ], [ 'taken_at' => 30*60, 'temp' => 55 ], [ 'taken_at' => 31*60, 'temp' => 55 ], [ 'taken_at' => 32*60, 'temp' => 55 ], [ 'taken_at' => 33*60, 'temp' => 55 ], [ 'taken_at' => 34*60, 'temp' => 55 ], [ 'taken_at' => 35*60, 'temp' => 55 ], [ 'taken_at' => 36*60, 'temp' => 55 ], [ 'taken_at' => 37*60, 'temp' => 55 ], [ 'taken_at' => 38*60, 'temp' => 55 ], [ 'taken_at' => 39*60, 'temp' => 55 ], [ 'taken_at' => 40*60, 'temp' => 55 ], [ 'taken_at' => 41*60, 'temp' => 55 ], [ 'taken_at' => 42*60, 'temp' => 45 ], [ 'taken_at' => 43*60, 'temp' => 45 ], [ 'taken_at' => 44*60, 'temp' => 45 ], [ 'taken_at' => 45*60, 'temp' => 45 ], [ 'taken_at' => 46*60, 'temp' => 45 ], [ 'taken_at' => 47*60, 'temp' => 45 ], [ 'taken_at' => 48*60, 'temp' => 45 ], [ 'taken_at' => 49*60, 'temp' => 45 ], [ 'taken_at' => 50*60, 'temp' => 45 ], [ 'taken_at' => 51*60, 'temp' => 45 ], [ 'taken_at' => 52*60, 'temp' => 45 ], [ 'taken_at' => 53*60, 'temp' => 45 ], [ 'taken_at' => 54*60, 'temp' => 45 ], [ 'taken_at' => 55*60, 'temp' => 45 ], [ 'taken_at' => 56*60, 'temp' => 45 ], [ 'taken_at' => 57*60, 'temp' => 45 ], [ 'taken_at' => 58*60, 'temp' => 45 ] ];       

        $zones = [
            'zone_1' => ['section_1', 'section_2'],
            'zone_2' => ['section_3', 'section_4'],
            'zone_3' => ['section_5', 'section_6'],
            'zone_4' => ['section_7', 'section_8'],
        ];

        $temps = array_map(fn($item) => $item['temp'], $logs);
        $sections = Self::groupValuesBySection($temps);
        $xzones = array_map('count', $sections);
        $yzones = [ 40, 50, 60, 70, 80 ];

        $chartData = $chartData->map(function ($group) {
            // Sort the group by taken_at to ensure chronological order
            $sortedGroup = $group->sortBy('taken_at');
            
            // Get the first timestamp as the reference point
            $firstTimestamp = strtotime($sortedGroup->first()['taken_at']);

            $firstLog       = $sortedGroup->first();
            $line           = sprintf('%02d', $firstLog->ins_stc_d_sum->ins_stc_machine->line);
            $position       = $firstLog->ins_stc_d_sum->position;
            $taken_at       = Carbon::parse($firstLog->taken_at)->format('mdH');
            $positionSymbol = $position == 'upper' ? '△' : ($position == 'lower' ? '▽' : '');
    
            return [
                'name' => $line . ' ' . $positionSymbol . ' ' . $taken_at,
                'data' => $sortedGroup->map(function ($item) use ($firstTimestamp) {
                    return [
                        'x' => (strtotime($item['taken_at']) - $firstTimestamp) * 1000, // Convert to milliseconds
                        'y' => $item['temp']
                    ];
                })->toArray()
            ];
        })->values()->toArray();

        return [
            'chart' => [
                'redrawOnParentResize' => true,
                'background' => 'transparent',
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
            'theme' => [
                'mode' => session('bg'),
            ],
            'series' => $chartData,
            'xaxis' => [
                'type' => 'datetime',
                'labels' => [
                    'show' => true,
                    'datetimeUTC' => true
                ],
            ],
            'yaxis' => [
                'title' => [
                    'text' => '°C',
                    'style' => [
                        'color' => session('bg') == 'dark' ? '#FFF' : null,
                    ],
                ],
                'max' => $ymax,
                'min' => $ymin,
                'labels' => [
                    'datetimeUTC' => false,
                    'style' => [
                        'colors' => session('bg') == 'dark' ? '#FFF' : null,
                    ],
                ],
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 1,
            ],
            'legend' => [
                'show' => true,
                'position' => 'right',
            ],
            'annotations' => [
                'xaxis' => self::generateXAnnotations($zones, $xzones, $logs),
                'yaxis' => self::generateYAnnotations($yzones),
                'points' => self::generateZoneAnnotations($zones, $yzones, $logs),
            ],
            'tooltip' => [
                'enabled' => false,
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

    public static function getBoxplotChartOptions($chartData, $width, $height)
    {
        $ymax = 85;
        $ymin = 35;
        $logs = [ [ 'taken_at' => 1*60, 'temp' => 75 ], [ 'taken_at' => 2*60, 'temp' => 75 ], [ 'taken_at' => 3*60, 'temp' => 75 ], [ 'taken_at' => 4*60, 'temp' => 75 ], [ 'taken_at' => 5*60, 'temp' => 75 ], [ 'taken_at' => 6*60, 'temp' => 75 ], [ 'taken_at' => 7*60, 'temp' => 75 ], [ 'taken_at' => 8*60, 'temp' => 75 ], [ 'taken_at' => 9*60, 'temp' => 75 ], [ 'taken_at' => 10*60, 'temp' => 75 ], [ 'taken_at' => 11*60, 'temp' => 75 ], [ 'taken_at' => 12*60, 'temp' => 75 ], [ 'taken_at' => 13*60, 'temp' => 75 ], [ 'taken_at' => 14*60, 'temp' => 75 ], [ 'taken_at' => 15*60, 'temp' => 75 ], [ 'taken_at' => 16*60, 'temp' => 75 ], [ 'taken_at' => 17*60, 'temp' => 75 ], [ 'taken_at' => 18*60, 'temp' => 65 ], [ 'taken_at' => 19*60, 'temp' => 65 ], [ 'taken_at' => 20*60, 'temp' => 65 ], [ 'taken_at' => 21*60, 'temp' => 65 ], [ 'taken_at' => 22*60, 'temp' => 65 ], [ 'taken_at' => 23*60, 'temp' => 65 ], [ 'taken_at' => 24*60, 'temp' => 65 ], [ 'taken_at' => 25*60, 'temp' => 65 ], [ 'taken_at' => 26*60, 'temp' => 65 ], [ 'taken_at' => 27*60, 'temp' => 65 ], [ 'taken_at' => 28*60, 'temp' => 65 ], [ 'taken_at' => 29*60, 'temp' => 65 ], [ 'taken_at' => 30*60, 'temp' => 55 ], [ 'taken_at' => 31*60, 'temp' => 55 ], [ 'taken_at' => 32*60, 'temp' => 55 ], [ 'taken_at' => 33*60, 'temp' => 55 ], [ 'taken_at' => 34*60, 'temp' => 55 ], [ 'taken_at' => 35*60, 'temp' => 55 ], [ 'taken_at' => 36*60, 'temp' => 55 ], [ 'taken_at' => 37*60, 'temp' => 55 ], [ 'taken_at' => 38*60, 'temp' => 55 ], [ 'taken_at' => 39*60, 'temp' => 55 ], [ 'taken_at' => 40*60, 'temp' => 55 ], [ 'taken_at' => 41*60, 'temp' => 55 ], [ 'taken_at' => 42*60, 'temp' => 45 ], [ 'taken_at' => 43*60, 'temp' => 45 ], [ 'taken_at' => 44*60, 'temp' => 45 ], [ 'taken_at' => 45*60, 'temp' => 45 ], [ 'taken_at' => 46*60, 'temp' => 45 ], [ 'taken_at' => 47*60, 'temp' => 45 ], [ 'taken_at' => 48*60, 'temp' => 45 ], [ 'taken_at' => 49*60, 'temp' => 45 ], [ 'taken_at' => 50*60, 'temp' => 45 ], [ 'taken_at' => 51*60, 'temp' => 45 ], [ 'taken_at' => 52*60, 'temp' => 45 ], [ 'taken_at' => 53*60, 'temp' => 45 ], [ 'taken_at' => 54*60, 'temp' => 45 ], [ 'taken_at' => 55*60, 'temp' => 45 ], [ 'taken_at' => 56*60, 'temp' => 45 ], [ 'taken_at' => 57*60, 'temp' => 45 ], [ 'taken_at' => 58*60, 'temp' => 45 ] ];       
        $yzones = [ 37.5, 42.5, 47.5, 52.5, 57.5, 62.5, 67.5, 72.5, 77.5 ];

        // Initialize sections to collect temperatures
        $sectionTemperatures = [
            'preheat' => [],
            'section_1' => [],
            'section_2' => [],
            'section_3' => [],
            'section_4' => [],
            'section_5' => [],
            'section_6' => [],
            'section_7' => [],
            'section_8' => [],
            'postheat' => []
        ];

        // Iterate through each element in chartData
        $chartData->each(function ($group) use (&$sectionTemperatures) {
            // Group temperatures for this specific group
            $groupSections = Self::groupValuesBySection($group->pluck('temp')->toArray());
            
            // Aggregate temperatures for each section
            foreach ($sectionTemperatures as $section => $temps) {
                if (isset($groupSections[$section])) {
                    $sectionTemperatures[$section] = array_merge($sectionTemperatures[$section], $groupSections[$section]);
                }
            }
        });

        // Function to calculate boxplot statistics
        $calculateBoxplotStats = function($values) {
            if (empty($values)) {
                return [0, 0, 0, 0, 0];
            }

            // Sort the values
            sort($values);
            $count = count($values);
            
            // Calculate quartiles
            $min = $values[0];
            $max = $values[$count - 1];
            
            // Median (Q2)
            $medianIndex = floor(($count - 1) / 2);
            $median = $count % 2 ? $values[$medianIndex] : 
                ($values[$medianIndex] + $values[$medianIndex + 1]) / 2;
            
            // Q1 and Q3
            $q1Index = floor(($count - 1) / 4);
            $q3Index = floor(3 * ($count - 1) / 4);
            $q1 = $count % 4 ? $values[$q1Index] : 
                ($values[$q1Index] + $values[$q1Index + 1]) / 2;
            $q3 = $count % 4 ? $values[$q3Index] : 
                ($values[$q3Index] + $values[$q3Index + 1]) / 2;

            return [
                round($min, 0),
                round($q1, 0),
                round($median, 0),
                round($q3, 0),
                round($max, 0),
            ];
        };

        // Prepare boxplot data
        $boxplotData = collect($sectionTemperatures)
            ->map(function($temps, $sectionName) use ($calculateBoxplotStats) {
                return [
                    'x' => $sectionName,
                    'y' => $calculateBoxplotStats($temps)
                ];
            })
            ->values()
            ->toArray();

        return [
            'chart' => [
                'redrawOnParentResize' => true,
                'background' => 'transparent',
                'width' => $width . '%',
                'height' => $height .'%',
                'type' => 'boxPlot',
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
            'theme' => [
                'mode' => session('bg'),
            ],
            'series' => [
                [
                    'name' => 'box',
                    'type' => 'boxPlot',
                    'data' => $boxplotData
                ]
            ],
            'xaxis' => [
                'type' => 'category',
            ],
            'yaxis' => [
                'title' => [
                    'text' => '°C',
                    'style' => [
                        'color' => session('bg') == 'dark' ? '#FFF' : null,
                    ],
                ],
                'max' => $ymax,
                'min' => $ymin,
                'labels' => [
                    'datetimeUTC' => false,
                    'style' => [
                        'colors' => session('bg') == 'dark' ? '#FFF' : null,
                    ],
                ],
            ],
            'annotations' => [
                'points' => self::generateSectionAnnotations($yzones, $logs),
            ],
            'legend' => [
                'show' => true,
                'showForSingleSeries' => true,
                'position' => 'right',
                'customLegendItems' => [ __('Standar') ],
                'markers' => [
                    'fillColors' => ['#D64550']
                ]
            ],
            'plotOptions' => [
                'boxPlot' => [
                    'colors' => [
                        'upper' => '#A8D8EA',
                        'lower' => '#A8EAC1'
                    ]
                ]
            ]
        ];
    }

    public static function getChartJsOptions($logs, array $sv_temps = []) 
    {
        $temps = array_map(fn($item) => $item['temp'], $logs);
        $sections = self::groupValuesBySection($temps);
        
        $zones = [
            'zone_1' => ['section_1', 'section_2'],
            'zone_2' => ['section_3', 'section_4'],
            'zone_3' => ['section_5', 'section_6'],
            'zone_4' => ['section_7', 'section_8'],
        ];

        $xzones = array_map('count', $sections);
        $yzones = [80, 70, 60, 50, 40];
        $ymax = 85;
        $ymin = 35;

        // Transform data for Chart.js
        $chartData = array_map(function ($log) {
            return [
                'x' => Carbon::parse($log['taken_at']),
                'y' => $log['temp']
            ];
        }, $logs);

        return [
            'type' => 'line',
            'data' => [
                'datasets' => [
                    [
                        'label' => __('Suhu'),
                        'data' => $chartData,
                        'borderColor' => '#D64550',
                        'borderWidth' => 1,
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'interaction' => [
                    'intersect' => false,
                    'mode' => 'index',
                ],
                'scales' => [
                    'x' => [
                        'type' => 'time',
                        'time' => [
                            'displayFormats' => [
                                'hour' => 'HH:mm',
                                'minute' => 'HH:mm'
                            ]
                        ],
                        'grid' => [
                            'display' => false
                        ],                        
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ],
                    'y' => [
                        'min' => $ymin,
                        'max' => $ymax,
                        'title' => [
                            'display' => true,
                            'text' => '°C',
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
                    'annotation' => [
                        'annotations' => array_merge(
                            self::generateChartJsXAnnotations($zones, $xzones, $logs),
                            self::generateChartJsYAnnotations($yzones),
                            self::generateChartJsZoneAnnotations($zones, $yzones, $logs),
                            self::generateChartJsSVAnnotations($sections, $sv_temps, $logs)
                        )
                    ],
                    'legend' => [
                        'display' => false
                    ]
                ]
            ]
        ];
    }

    private static function generateChartJsXAnnotations($zones, $xzones, $logs) 
    {
        $annotations = [];
        $previousCount = $xzones['preheat'];

        // Initial border after preheat
        $annotations[] = [
            'type' => 'line',
            'xMin' => Carbon::parse($logs[$previousCount]['taken_at']),
            'xMax' => Carbon::parse($logs[$previousCount]['taken_at']),
            'borderColor' => session('bg') === 'dark' ? '#404040' : '#e5e5e5',
            'borderWidth' => 1,
        ];

        foreach ($zones as $zoneSections) {
            $lastSectionPosition = $previousCount;
            foreach ($zoneSections as $section) {
                $lastSectionPosition += $xzones[$section];
            }

            if (isset($logs[$lastSectionPosition])) {
                $annotations[] = [
                    'type' => 'line',
                    'xMin' => Carbon::parse($logs[$lastSectionPosition]['taken_at']),
                    'xMax' => Carbon::parse($logs[$lastSectionPosition]['taken_at']),
                    'borderColor' => session('bg') === 'dark' ? '#404040' : '#e5e5e5',
                    'borderWidth' => 1,
                ];
            }

            $previousCount = $lastSectionPosition;
        }

        return $annotations;
    }

    private static function generateChartJsYAnnotations($yzones) 
    {
        return array_map(function($value) {
            return [
                'type' => 'line',
                'yMin' => $value,
                'yMax' => $value,
                'borderColor' => session('bg') === 'dark' ? '#404040' : '#e5e5e5',
                'borderWidth' => 1,
                'label' => [
                    'content' => $value . '°C',
                    'color' => session('bg') === 'dark' ? '#404040' : '#e5e5e5',
                    'backgroundColor' => 'transparent',
                    'position' => 'start'
                ]
            ];
        }, $yzones);
    }

    private static function generateChartJsSVAnnotations(array $sections, array $sv_temps, array $logs): array
    {
        // Validate sv_temps
        if (count($sv_temps) !== 8) {
            return []; // Return empty array if sv_temps is invalid
        }
    
        $annotations = [];
        $cursorLogsIndex = 0;
        $cursorSVTempIndex = 0;
    
        foreach ($sections as $sectionName => $values) {
            if (in_array($sectionName, ['preheat'])) {
                $cursorLogsIndex = $values ? count($values) : 0;
                continue;
            }

            if (in_array($sectionName, ['postheat'])) {
                continue;
            }
    
            if (empty($values)) {
                continue; // Skip if section is missing or empty
            }

            // Get the original indices before sorting
            $originalIndices = array_keys($values);
    
            // Find the middle index of the sorted values
            $middleIndex = (int) floor(count($values) / 2);

            $log = $logs[$cursorLogsIndex + $middleIndex];

            $annotations[] = [
                'type' => 'point',
                'xValue' => Carbon::parse($log['taken_at']),
                'yValue' => $sv_temps[$cursorSVTempIndex],
                'radius' => 4,
                'borderWidth' => 0,
                'backgroundColor' => 'red',
            ];
            
            $cursorLogsIndex = $cursorLogsIndex + count($values);
            $cursorSVTempIndex++;
            
    
            // // Get the original index of the middle value
            // $originalMiddleIndex = $originalIndices[$middleIndex];
    
            // // Use the original index to find the corresponding log entry
            // $middleValue = $values[$middleIndex] ?? null;
            
            // if ($middleValue !== null && isset($sv_temps[$sectionIndex])) {

            //     // Find the corresponding datetime from logs using the original index
            //     $logIndex = array_search($middleValue, array_column($logs, 'value'), true);
            //     dd($logIndex);
            //     $datetime = $logIndex !== false ? $logs[$logIndex]['datetime'] : null;
    
            //     if ($datetime) {
            //         $annotations[] = [
            //             'type' => 'point',
            //             'xValue' => Carbon::parse($datetime),
            //             'yValue' => $sv_temps[$sectionIndex],
            //             'label' => [
            //                 'content' => sprintf('SV%d: (%d)', $sectionIndex + 1, $sv_temps[$sectionIndex]),
            //                 'enabled' => true,
            //                 'position' => 'top',
            //             ],
            //             'backgroundColor' => 'red',
            //         ];
            //     }
            // }
    
            // $sectionIndex++;
        }
    
        return $annotations;
    }
    

    public static function getMedians(array $values): ?float
    {
        $count = count($values);
        if ($count === 0) {
            return null;
        }

        sort($values);
        $middleIndex = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middleIndex - 1] + $values[$middleIndex]) / 2;
        }

        return $values[$middleIndex];
    }

    private static function generateChartJsZoneAnnotations($zones, $yzones, $logs) 
    {
        $annotations = [];
        $temps = array_map(fn($item) => $item['temp'], $logs);
        $counts = array_map('count', self::groupValuesBySection($temps));
    
        // Calculate start positions for each section
        $cumulativeCounts = [];
        $total = $counts['preheat'];
    
        foreach (['section_1', 'section_2', 'section_3', 'section_4', 'section_5', 'section_6', 'section_7', 'section_8'] as $section) {
            $total += $counts[$section];
            $cumulativeCounts[$section] = $total;
        }

        foreach ($zones as $zoneName => $zoneSections) {
            // Get start and end indexes for the zone
            $startSection = $zoneSections[0];
            $endSection = $zoneSections[1];
            
            $startIndex = $cumulativeCounts[$startSection] - $counts[$startSection];
            $endIndex = min($cumulativeCounts[$endSection], count($logs) - 1);
            
            // Get temperature range for the zone from yzones
            $zoneIndex = array_search($zoneName, array_keys($zones));
            $yMin = $yzones[$zoneIndex];
            $yMax = $yzones[$zoneIndex + 1];
    
            $annotations[] = [
                'type' => 'box',
                'xMin' => Carbon::parse($logs[$startIndex]['taken_at']),
                'xMax' => Carbon::parse($logs[$endIndex]['taken_at']),
                'yMin' => $yMin,
                'yMax' => $yMax,
                'backgroundColor' => 'rgba(214, 69, 80, 0.10)', // #D64550 with 25% opacity
                'borderWidth' => 0
            ];
        }
    
        return $annotations;
    }

    public static function getChartOptions($logs, $width, $height)
    {

        $chartData = array_map(function ($log) {
            return [Self::parseDate($log['taken_at']), $log['temp']];
        }, $logs);

        $temps = array_map(fn($item) => $item['temp'], $logs);
        $sections = Self::groupValuesBySection($temps);
        $zones = [
            'zone_1' => ['section_1', 'section_2'],
            'zone_2' => ['section_3', 'section_4'],
            'zone_3' => ['section_5', 'section_6'],
            'zone_4' => ['section_7', 'section_8'],
        ];

        $xzones = array_map('count', $sections);
        $yzones = [ 40, 50, 60, 70, 80 ];
        $ymax = 85;
        $ymin = 35;
        $chartDataJs = json_encode($chartData);

        return [
            'chart' => [
                'redrawOnParentResize' => true,
                'background' => 'transparent',
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
            'theme' => [
                'mode' => session('bg'),
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
                'xaxis' => self::generateXAnnotations($zones, $xzones, $logs),
                'yaxis' => self::generateYAnnotations($yzones),
                'points' => self::generateZoneAnnotations($zones, $yzones, $logs),
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

    private static function generateXAnnotations($zones, $xzones, $logs)
    {
        $annotations = [];
        $previousCount = $xzones['preheat']; // Start after preheat

        $annotations[] = [
            'x' => self::parseDate($logs[$previousCount]['taken_at']),
            'borderColor' => '#bcbcbc',
            'label' => [
                'style' => [
                    'color' => 'transparent',
                    'background' => 'transparent',
                ],
                'text' => '',
            ],
        ];
    
        foreach ($zones as $zoneName => $zoneSections) {
    
            // Calculate the position of the last section's end
            $lastSectionPosition = $previousCount;
            foreach ($zoneSections as $section) {
                $lastSectionPosition += $xzones[$section];
            }
    
            // Last border: end of the last section in the zone
            if (isset($logs[$lastSectionPosition])) {
                $annotations[] = [
                    'x' => self::parseDate($logs[$lastSectionPosition]['taken_at']),
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
    
            // Update previous count for next iteration
            $previousCount = $lastSectionPosition;
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

    private static function generateZoneAnnotations($zones, $yzones, $logs)
    {
        $pointAnnotations = [];
        $temps = array_map(fn($item) => $item['temp'], $logs);
        $medians = Self::getMediansBySection($temps);
    
        $counts = array_map('count', Self::groupValuesBySection($temps));
    
        // Calculate cumulative counts to determine x-coordinates
        $cumulativeCounts = [];
        $total = $counts['preheat']; // Start with full preheat count

        foreach (['section_1', 'section_2', 'section_3', 'section_4', 'section_5', 'section_6', 'section_7', 'section_8'] as $section) {
            $total += $counts[$section];
            $cumulativeCounts[$section] = $total;
        }
    
        $i = 0;
        foreach ($zones as $zoneName => $zoneSections) {
            // Calculate index as the last log entry in the cumulative count
            $firstSection = $zoneSections[0];
            $index = $cumulativeCounts[$firstSection]; // subtract 1 to get correct zero-based index
    
            // Get the 'taken_at' timestamp for this index
            $x = self::parseDate($logs[$index]['taken_at']);
    
            // Calculate y as the middle of the y-zones
            $zoneIndex = array_search($zoneName, array_keys($zones));
            $y = $yzones[count($yzones) - $zoneIndex - 2];
    
            // Calculate zone value (average of two sections' median temperatures)
            $zoneValue = round(
                ($medians[$zoneSections[0]] + $medians[$zoneSections[1]]) / 2, 
                2
            );
    
            $pointAnnotations[] = [
                'x' => $x,
                'y' => $y,
                'marker' => [
                    'size' => 0,
                    'strokeWidth' => 0,
                ],
                'label' => [
                    'borderWidth' => 0,
                    'text' => sprintf('%s: %.2f', __('Z') . + ++$i, $zoneValue),
                    'style' => [
                        'background' => '#D64550',
                        'color' => '#ffffff',
                    ],
                ],
            ];
        }
    
        return $pointAnnotations;
    }

    private static function generateSectionAnnotations($yzones)
    {
        $pointAnnotations = [];
    
        // Directly create annotations for sections
        $sections = ['section_1', 'section_2', 'section_3', 'section_4', 'section_5', 'section_6', 'section_7', 'section_8'];
        
        foreach ($sections as $index => $section) {
            // Use the section name as x-coordinate
            $x = $section;
    
            // Calculate y as the middle of the y-zones, starting from the end
            $y = $yzones[count($yzones) - $index - 1];
    
            $pointAnnotations[] = [
                'x' => $x,
                'y' => $y,
                'marker' => [
                    'size' => 4,
                    'strokeWidth' => 0,
                    'fillColor' => '#D64550'
                ],
            ];
        }
    
        return $pointAnnotations;
    }

    public static function parseDate($dateString)
    {
        return Carbon::parse($dateString)->timestamp * 1000;
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
