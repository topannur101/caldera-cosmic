<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsRtcClump;
use App\Models\InsRtcMetric;
use App\Models\InsRtcRecipe;
use Carbon\Carbon;

new class extends Component {
    
    public int $id = 0;

    #[On('clump-load')]
    public function clumpLoad($id)
    {
        $this->id = $id;
    }

    public function with(): array
    {
        
        $clump = InsRtcClump::find($this->id);
        $line           = '';
        $start_at       = '';
        $end_at         = '';
        $duration       = '';
        $recipe_id      = 0;
        $recipe_name    = '';
        $recipe_std_mid = 0;
        $recipe_std_min = 0;
        $recipe_std_max = 0;
        $recipe_pfc_min = 0;
        $recipe_pfc_max = 0;

        if ($clump) {
            $metrics    = InsRtcMetric::where('ins_rtc_clump_id', $this->id)->get();
            $line       = $clump->ins_rtc_device_id;
            $start_at   = $metrics->min('dt_client');
            $end_at     = $metrics->max('dt_client');
            $duration   = Carbon::createFromTimestampUTC($start_at->diffInSeconds($end_at))->format('i:s');
            $recipe     = $clump->ins_rtc_recipe;

            if($recipe) {
                $recipe_id      = $recipe->id;
                $recipe_name    = $recipe->name;
                $recipe_std_mid = $recipe->std_mid;
                $recipe_std_min = $recipe->std_min;
                $recipe_std_max = $recipe->std_max;
                $recipe_pfc_min = $recipe->pfc_min;
                $recipe_pfc_max = $recipe->pfc_max;
            }

            // Get all non-zero values from sensor_left and sensor_right
            $nonZeroValues = $metrics
                ->flatMap(function ($item) {
                    return [$item->sensor_left > 0 ? $item->sensor_left : null, $item->sensor_right > 0 ? $item->sensor_right : null];
                })
                ->filter();
                $minY = floor($nonZeroValues->min() * 2) / 2;
                $maxY = ceil($nonZeroValues->max() * 2) / 2;

            // Prepare the data for the JavaScript
            $kiriData = $metrics
                ->map(function ($metric) {
                    return [$metric->dt_client, $metric->sensor_left];
                })
                ->toArray();

            $kananData = $metrics
                ->map(function ($metric) {
                    return [$metric->dt_client, $metric->sensor_right];
                })
                ->toArray();

            $actions = [];

            foreach ($metrics as $metric) {
                // Process action_left
                if ($metric->action_left === 'thin' || $metric->action_left === 'thick') {
                    $labelText = $metric->action_left === 'thick' ? '+' : '-';
                    $actions[] = [
                        'x' => $metric->dt_client,
                        'y' => $metric->sensor_left,
                        'marker' => [
                            'size' => 8,
                        ],
                        'label' => [
                            'borderColor' => '#00BBF9',
                            'text' => $labelText,
                        ],
                    ];
                }

                // Process action_right
                if ($metric->action_right === 'thin' || $metric->action_right === 'thick') {
                    $labelText = $metric->action_right === 'thick' ? '+' : '-';
                    $actions[] = [
                        'x' => $metric->dt_client,
                        'y' => $metric->sensor_right,
                        'marker' => [
                            'size' => 8,
                        ],
                        'label' => [
                            'borderColor' => '#E27883',
                            'text' => $labelText,
                        ],
                    ];
                }
            }

            // Convert PHP arrays to JavaScript arrays
            $kiriDataJs     = json_encode($kiriData);
            $kananDataJs    = json_encode($kananData);
            $actionsJs      = json_encode($actions); 

            $this->js(
                "
            let options = {
            chart: {
                height: '100%',
                type: 'line',
                toolbar: {
                    show: false
                },
                animations: {
                    enabled: true,
                    easing: 'easeout',
                    speed: 400,
                    animateGradually: {
                        enabled: false,
                  },
                }
            },
            series: [{
                name: '" .
                    __('Kiri') .
                    "',
                data: " .
                    $kiriDataJs .
                    ",
                color: '#00BBF9'
            }, {
                name: '" .
                    __('Kanan') .
                    "',
                data: " .
                    $kananDataJs .
                    ",
                color: '#E27883'
            }],
            xaxis: {
                type: 'datetime',
                labels: {
                    datetimeUTC: false,
                }
            },
            yaxis: {
                min: " .
                    $minY .
                    ",
                max: " .
                    $maxY .
                    "

            },
            stroke: {
                curve: 'smooth',
                width: [1, 1],
                dashArray: [0, 0, 10]
            },
            annotations: {
                yaxis: [
                    {
                        y: " .
                        $recipe_std_min -
                        0.05 .
                        ",
                        y2: " .
                        $recipe_std_max +
                        0.05 .
                        ",
                        borderColor: '#654db8',
                        fillColor: '#654db8',
                        label: {
                            text: '" .
                        __('Standar') .
                        "'
                        }
                    }
                ],
                points: " . $actionsJs . ",
            }
        };

        const parent = \$wire.\$el.querySelector('#chart-container');
        parent.innerHTML = '';

        const newChartMain = document.createElement('div');
        newChartMain.id = 'chart-main';
        parent.appendChild(newChartMain);

        let mainChart = new ApexCharts(parent.querySelector('#chart-main'), options);
        mainChart.render();",
            );
        }
        return [
            'line'              => $line,
            'start_at'          => $start_at,
            'end_at'            => $end_at,
            'duration'          => $duration,
            'recipe_id'         => $recipe_id,
            'recipe_name'       => $recipe_name,
            'recipe_std_mid'    => $recipe_std_mid,
            'recipe_std_min'    => $recipe_std_min,
            'recipe_std_max'    => $recipe_std_max,
            'recipe_pfc_min'    => $recipe_pfc_min,
            'recipe_pfc_max'    => $recipe_pfc_max
    ];
    }
};
?>


<div class="p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Rincian Gilingan') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
    </div>
    <div class="h-80 bg-white dark:brightness-75 rounded overflow-hidden my-8" id="chart-container" wire:key="chart-container" wire:ignore>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4">
        <div>
            <h3 class="mb-2 font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Info gilingan') . ' [' . $id . ']' }}</h3>
            <div>{{ __('Line') . ': ' . $line }}</div>
            <div>{{ __('Awal') . ': ' . $start_at }}</div>
            <div>{{ __('Akhir') . ': ' . $end_at }}</div>
            <div>{{ __('Durasi') . ': ' . $duration }}</div>
        </div>
        <div>
            <h3 class="mb-2 font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Info resep') . ' [' . $recipe_id . ']' }}</h3>
            <div>{{ $recipe_name }}</div>
            <div>{{ __('Standar tengah') . ': ' . $recipe_std_mid }}</div>
            <div>{{ __('Standar') . ': ' . $recipe_std_min  . ' — ' . $recipe_std_max }}</div>
            <div>{{ __('PFC') . ': ' . $recipe_pfc_min . ' — ' . $recipe_pfc_max }}</div>
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
