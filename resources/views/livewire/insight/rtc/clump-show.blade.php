<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsRtcClump;
use App\Models\InsRtcMetric;
use App\Models\InsRtcRecipe;
use Carbon\Carbon;

new class extends Component {


    public $id;
    public InsRtcClump $clump;
    public InsRtcRecipe $recipe;
    public $metrics;
    public Carbon $start_at;
    public Carbon $end_at;
    public Carbon $duration;

    public function placeholder()
    {
        return view('livewire.layout.modal-placeholder');
    }

     #[On('clump-show')] 
    public function clumpShow($id)
    {
        $this->id = (int) $id;
        if($this->id) {
            $this->clump    = InsRtcClump::find($this->id);
            $this->metrics  = InsRtcMetric::where('ins_rtc_clump_id', $this->id)->get();
            $this->start_at = $this->metrics->min('dt_client');
            $this->end_at   = $this->metrics->max('dt_client');
            $this->duration = Carbon::createFromTimestampUTC($this->start_at->diffInSeconds($this->end_at));
            $this->recipe   = $this->clump->ins_rtc_recipe;

            // Prepare the data for the JavaScript
            $kiriData = $this->metrics->map(function($metric) {
                return [$metric->dt_client, $metric->sensor_left];
            })->toArray();

            $kananData = $this->metrics->map(function($metric) {
                return [$metric->dt_client, $metric->sensor_right];
            })->toArray();

            // Convert PHP arrays to JavaScript arrays
            $kiriDataJs = json_encode($kiriData);
            $kananDataJs = json_encode($kananData);

            $this->js("
            let options = {
            chart: {
                height: '100%',
                type: 'line',
                toolbar: {
                    show: false
                },
                animations: {
                    enabled: true,
                }
            },
            series: [{
                name: '" . __('Kiri') . "',
                data: " . $kiriDataJs . ",
                color: '#00BBF9'
            }, {
                name: '" . __('Kanan') . "',
                data: " . $kananDataJs . ",
                color: '#E27883'
            }],
            xaxis: {
                type: 'datetime',
                labels: {
                    datetimeUTC: false,
                }
            },
            yaxis: {
                min: 1,
                max: 6

            },
            stroke: {
                curve: 'smooth',
                width: [1, 1],
                dashArray: [0, 0, 10]
            },
            annotations: {
                yaxis: [
                    {
                    y: " . $this->recipe->std_min - 0.05 . ",
                    y2: " . $this->recipe->std_max + 0.05 . ",
                    borderColor: '#654db8',
                    fillColor: '#654db8',
                    label: {
                        text: '" . __('Standar') . "'
                    }
                    }
                ]
                }
        };

        const parent = \$wire.\$el.querySelector('#chart-container');
        parent.innerHTML = '';

        const newChartMain = document.createElement('div');
        newChartMain.id = 'chart-main';
        parent.appendChild(newChartMain);

        let mainChart = new ApexCharts(parent.querySelector('#chart-main'), options);
        mainChart.render();");

        }
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
    <div class="h-80 bg-white rounded overflow-hidden my-8" id="chart-container" wire:key="chart-container" wire:ignore>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4">
      <div>
         <div class="font-bold">{{ __('Info gilingan') . ' [' . $id . ']' }}</div>
         <div>{{ __('Awal') . ': ' . $start_at }}</div>
         <div>{{ __('Akhir') . ': ' . $end_at }}</div>
         <div>{{ __('Durasi') . ': ' . ($duration ? $duration->format('i:s') : '') }}</div>
         <div>{{ __('Line') . ': ' . ($clump->ins_rtc_device->line ?? '') }}</div>
      </div>
      <div>
         <div class="font-bold">{{ __('Info resep') . ' [' . ($recipe->id ?? '') . ']' }}</div>
         <div>{{ $recipe->name ?? '' }}</div>
         <div>{{ __('Standar tengah') . ': ' . ($recipe->std_mid ?? '') }}</div>
         <div>{{ __('Standar') . ': ' . ($recipe->std_min ?? '') . ' — ' . ($recipe->std_max ?? '') }}</div>
         <div>{{ __('PFC') . ': ' . ($recipe->pfc_min ?? '') . ' — ' . ($recipe->pfc_max ?? '') }}</div>
      </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
