<?php

namespace App;

class InsRdc
{
    public static function getChartOptions($tests, $height)
    {
        $chartData = $tests->map(function ($test) {
            return [
                'queued_at' => $test->queued_at,
                'tc10' => $test->tc10,
                'tc90' => $test->tc90,
            ];
        })->toArray();

        return [
            'chart' => [
                'redrawOnParentResize' => true,
                'height' => $height.'%',
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
                    'name' => __('TC10'),
                    'data' => array_map(function ($item) {
                        return ['x' => $item['queued_at'], 'y' => $item['tc10']];
                    }, $chartData),
                    'color' => '#D64550',
                ],
                [
                    'name' => __('TC90'),
                    'data' => array_map(function ($item) {
                        return ['x' => $item['queued_at'], 'y' => $item['tc90']];
                    }, $chartData),
                    'color' => '#4CAF50',
                ],
            ],
            'yaxis' => [
                'title' => [
                    'text' => __('Angka TC'),
                ],
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 1,
            ],
        ];
    }
}
