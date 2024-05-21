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
                color: '#00F5D4'
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
                    y: " . $this->recipe->std_min . ",
                    y2: " . $this->recipe->std_max . ",
                    borderColor: '#654db8',
                    fillColor: '#9984d6',
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
            {{ __('Rincian gilingan') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
    </div>
    <div class="h-80" id="chart-container" wire:key="chart-container" wire:ignore>
    </div>
    <div class="font-bold">Batch info</div>
    <div>Batch id: {{ $id }}</div>
    <div>Batch start at: {{ $start_at }}</div>
    <div>Batch start at: {{ $end_at }}</div>
    <div>Batch duration: {{ $duration ? $duration->format('i:s') : '' }}</div>
    <div class="font-bold">Recipe info</div>
    <div>Recipe id: {{ $recipe->id ?? '' }}</div>
    <div>Recipe name: {{ $recipe->name ?? '' }}</div>
    <div>Standard mid: {{ $recipe->std_mid ?? '' }}</div>
    <div>Standard min: {{ $recipe->std_min ?? '' }}</div>
    <div>Standard max: {{ $recipe->std_max ?? '' }}</div>
    <div>PFC min: {{ $recipe->pfc_min ?? '' }}</div>
    <div>PFC max: {{ $recipe->pfc_max ?? '' }}</div>
    <div class="font-bold">Device info</div>
    <div>Device id: {{ $clump->ins_rtc_device_id ?? '' }}</div>
    <div>Device line: {{ $clump->ins_rtc_device->line ?? '' }}</div>

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
