<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;

new class extends Component {
    
    public array $logs = [];
    public array $xzones = [];
    public array $yzones = [];
    public float $z_1_temp = 0;
    public float $z_2_temp = 0;
    public float $z_3_temp = 0;
    public float $z_4_temp = 0;
    public int $ymax = 10;
    public int $ymin = 0;

    #[On('d-logs-review')]
    public function dLogsLoad($logs, $xzones, $yzones, $z_1_temp, $z_2_temp, $z_3_temp, $z_4_temp )
    {
        $this->logs = json_decode($logs, true);
        $this->xzones = json_decode($xzones, true);
        $this->yzones = json_decode($yzones, true);
        $this->z_1_temp = $z_1_temp;
        $this->z_2_temp = $z_2_temp;
        $this->z_3_temp = $z_3_temp;
        $this->z_4_temp = $z_4_temp;
        $this->ymax = $this->yzones ? max($this->yzones) + 0 : $this->ymax;
        $this->ymin = $this->yzones ? min($this->yzones) - 0 : $this->ymin;
        $this->generateChart();
    }

    private function generateChart()
    {
        $chartData = array_map(function ($log) {
            return [$this->parseDate($log['taken_at']), $log['temp']];
        }, $this->logs);

        $chartDataJs = json_encode($chartData);

        $this->js("
            let options = " . json_encode($this->getChartOptions($chartDataJs)) . ";

            const parent = \$wire.\$el.querySelector('#chart-container');
            parent.innerHTML = '';

            const newChartMain = document.createElement('div');
            newChartMain.id = 'chart-main';
            parent.appendChild(newChartMain);

            let mainChart = new ApexCharts(parent.querySelector('#chart-main'), options);
            mainChart.render();
        ");
    }

    private function getChartOptions($chartDataJs)
    {
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
                    ]
                ],
                'animations' => [
                    'enabled' => true,
                    'easing' => 'easeout',
                    'speed' => 400,
                    'animateGradually' => [
                        'enabled' => false,
                    ],
                ]
            ],
            'series' => [[
                'name' => __('Suhu'),
                'data' => json_decode($chartDataJs, true),
                'color' => '#00BBF9'
            ]],
            'xaxis' => [
                'type' => 'datetime',
                'labels' => [
                    'datetimeUTC' => false,
               ]
            ],
            'yaxis' => [
                'title' => [
                    'text' => '°C'
               ],
               'max' => $this->ymax,
               'min' => $this->ymin,

            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 1,
            ],
            'tooltip' => [
                'x' => [
                    'format' => 'dd MMM yyyy HH:mm',
                ]              
            ],
            'annotations' => [
                'xaxis' => $this->generateXAnnotations(),
                'yaxis' => $this->generateYAnnotations(),
                'points' => $this->generatePointAnnotations(),
            ],
            'grid' => [
               'yaxis' => [
                  'lines' => [
                     'show' => false
                  ]
               ]
            ]
        ];
    }

    private function generateXAnnotations()
    {
        $annotations = [];
        $previousCount = 0;

        foreach ($this->xzones as $zone => $count) {
            if (strpos($zone, 'count') !== false && $count > 0) {
                $zoneName = str_replace('_count', '', $zone);
                $position = $previousCount + $count;

                if (isset($this->logs[$position])) {
                    $annotations[] = [
                        'x' => $this->parseDate($this->logs[$position]['taken_at']),
                        'borderColor' => '#775DD0',
                        'label' => [
                            'style' => [
                                'color' => 'transparent',
                                'background' => 'transparent'
                            ],
                            'text' => ''
                        ]
                    ];
                }

                $previousCount += $count;
            }
        }

        return $annotations;
    }

    private function generateYAnnotations()
   {
      $annotations = [];
      foreach ($this->yzones as $index => $value) {
         $annotations[] = [
               'y' => $value,
               'borderColor' => '#bcbcbc',
               'label' => [
                  'borderColor' => 'transparent',
                  'style' => [
                     'color' => '#bcbcbc',
                     'background' => 'transparent'
                  ],
                  'text' => $value . "°C"
               ]
         ];
      }
      return $annotations;
   }

   private function generatePointAnnotations()
    {
        $pointAnnotations = [];
        $preheatCount = $this->xzones['preheat_count'];
        $currentIndex = $preheatCount;
        $zoneNames = ['zone_1_count', 'zone_2_count', 'zone_3_count', 'zone_4_count'];
        $yzonesReversed = array_reverse($this->yzones);

        foreach ($zoneNames as $index => $zoneName) {
            $zoneCount = $this->xzones[$zoneName];
            $midpointIndex = $currentIndex + floor($zoneCount / 2);
            
            if (isset($this->logs[$midpointIndex])) {
                $yValue = ($yzonesReversed[$index] + $yzonesReversed[$index + 1]) / 2;
                
                $pointAnnotations[] = [
                    'x' => $this->parseDate($this->logs[$midpointIndex]['taken_at']),
                    'y' => $yValue,
                    'marker' => [
                            'size'          => 0,
                            'strokeWidth'   => 0
                    ],
                    'label' => [
                        'borderWidth' => 0,
                        'text' => 'Z' . ($index + 1). ': ' $this->z_1_temp,
                        'style' => [
                            'background' => '#00BBF9',
                            'color' => '#ffffff'
                        ]
                    ]
                ];
            }
            
            $currentIndex += $zoneCount;
        }

        return $pointAnnotations;
    }

    private function parseDate($dateString)
    {
        return Carbon::parse($dateString)->timestamp * 1000;
    }

    public function with(): array
    {
        return [
            'logs' => $this->logs,
        ];
    }
};
?>

<div class="p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Tinjau data') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
    </div>
    <div class="h-80 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden my-8" id="chart-container" wire:key="chart-container" wire:ignore>
    </div>
    <div class="grid grid-cols-2 gap-x-3">
      <div>
         <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Pembagian zona') }}
        </h2>
        <div class="mt-3">
         {{ __('Preheat diambil berdasarkan 5 data pertama. Selanjutnya, setiap zona diwakili oleh 12 data berturut-turut.') }}
        </div>
      </div>
      <div class="max-h-48 overflow-y-auto relative">
          <table class="table table-xs text-sm overflow-hidden">
              <thead class="sticky top-0 z-10">
                  <tr>
                      <th>{{ __('No.') }}</th>
                      <th>{{ __('Diambil pada') }}</th>
                      <th>{{ __('Suhu') }}</th>
                  </tr>
              </thead>
              <tbody>
                  @foreach($logs as $index => $log)
                      <tr>
                          <td>{{ $index + 1 }}</td>
                          <td>{{ $log['taken_at'] }}</td>
                          <td>{{ $log['temp'] }}</td>
                      </tr>
                  @endforeach
              </tbody>
          </table>
      </div>
  </div>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>