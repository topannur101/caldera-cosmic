<?php

namespace App;

use Illuminate\Support\Carbon;

class InsOmv
{
    public static function getChartOptions(array $amps, Carbon $start_at, int $height)
    {
        $chartData = array_map(function ($amp) {
            return [
                'taken_at' => $amp['taken_at'],
                'value' => $amp['value'],
            ];
        }, $amps);

        return [
            'chart' => [
                'redrawOnParentResize' => true,
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
                    'name' => __('Current'),
                    'data' => array_map(function($item) {
                        return ['x' => $item['taken_at'], 'y' => $item['value']];
                    }, $chartData),
                    'color' => '#D64550',
                ],
            ],
            'yaxis' => [
                'title' => [
                    'text' => __('Angka TC'),
                ]
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 1,
            ],
        ];
    }  
    
}
