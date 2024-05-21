<?php

use Livewire\Volt\Component;
use App\Models\InsRtcClump;
use App\Models\InsRtcMetric;
use Carbon\Carbon;

new class extends Component {
    public int $id;
    public InsRtcClump $clump;
    public $metrics;
    public Carbon $start_at;
    public Carbon $end_at;
    public int $duration;

    public function placeholder()
    {
        return view('livewire.layout.modal-placeholder');
    }

    public function mount()
    {
        $this->clump = InsRtcClump::find($this->id);
        $this->metrics = InsRtcMetric::where('ins_rtc_clump_id', $this->id)->get();
        $this->start_at = $this->metrics->min('dt_client');
        $this->end_at = $this->metrics->max('dt_client');
        $this->duration = $this->start_at->diffInSeconds($this->end_at);
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
    <div>Batch info</div>
    <div>Batch id: {{ $id }}</div>
    <div>Batch start at: {{ $start_at }}</div>
    <div>Batch start at: {{ $end_at }}</div>
    <div>Batch duration: {{ Carbon::createFromTimestampUTC($duration)->format('i:s') }}</div>
    <div>Recipe info</div>
    <div>Recipe id: {{ $clump->ins_rtc_recipe_id }}</div>
    <div>Recipe name: {{ $clump->ins_rtc_recipe->name }}</div>
    <div>Standard mid: {{ $clump->ins_rtc_recipe->std_mid }}</div>
    <div>Standard min: {{ $clump->ins_rtc_recipe->std_min }}</div>
    <div>Standard max: {{ $clump->ins_rtc_recipe->std_max }}</div>
    <div>PFC min: {{ $clump->ins_rtc_recipe->pfc_min }}</div>
    <div>PFC max: {{ $clump->ins_rtc_recipe->pfc_max }}</div>
    <div>Device info</div>
    <div>Device id: {{ $clump->ins_rtc_device_id }}</div>
    <div>Device line: {{ $clump->ins_rtc_device->line }}</div>
    <div class="chart-left"></div>
    <div class="chart-right"></div>
</div>

@script
    <script>
        let oLeft = {
            chart: {
                type: 'line',
                toolbar: {
                    show: false
                },
                animations: {
                    enabled: false,
                }
            },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [0],
                style: {
                    fontSize: '20px',
                    colors: ['#7F63CC'],
                },
                background: {
                    enabled: false
                }
            },
            series: [{
                name: '{{ __('Sensor') }}',
                type: 'area',
                data: [
                    [1, 1],
                    [2, 2]
                ],
                color: '#b4a5e1'
            }, {
                name: '{{ __('Minimum') }}',
                type: 'line',
                data: [
                    [1, 1],
                    [2, 2]
                ],
                color: '#169292'
            }, {
                name: '{{ __('Maksimum') }}',
                type: 'line',
                data: [
                    [1, 1],
                    [2, 2]
                ],
                color: '#169292'
            }, {
                name: '{{ __('Tengah') }}',
                type: 'line',
                data: [
                    [1, 1],
                    [2, 2]
                ],
                color: '#169292'
            }],
            xaxis: {
                type: 'datetime',
                range: 60000,
                labels: {
                    show: false,
                    datetimeUTC: false,
                }
            },
            yaxis: {
                min: 1,
                max: 6

            },
            stroke: {
                curve: 'smooth',
                width: [0, 1, 1, 1],
                dashArray: [0, 0, 0, 10]
            },
        };

        let oRight = {
            chart: {
                type: 'line',
                toolbar: {
                    show: false
                },
                animations: {
                    enabled: false,
                }
            },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [0],
                style: {
                    fontSize: '20px',
                    colors: ['#7F63CC'],
                },
                background: {
                    enabled: false
                }
            },
            series: [{
                name: '{{ __('Sensor') }}',
                type: 'area',
                data: [],
                color: '#b4a5e1'
            }, {
                name: '{{ __('Minimum') }}',
                type: 'line',
                data: [],
                color: '#169292'
            }, {
                name: '{{ __('Maksimum') }}',
                type: 'line',
                data: [],
                color: '#169292'
            }, {
                name: '{{ __('Tengah') }}',
                type: 'line',
                data: [],
                color: '#169292'
            }],
            xaxis: {
                type: 'datetime',
                range: 60000,
                labels: {
                    show: false,
                    datetimeUTC: false,
                }
            },
            yaxis: {
                min: 1,
                max: 6

            },
            stroke: {
                curve: 'smooth',
                width: [0, 1, 1, 1],
                dashArray: [0, 0, 0, 10]
            },
        };

        Livewire.hook('element.init', ({ component, el }) => {
         console.log(el);
         })



        const el = $wire.$el;
        

        let leftChart = new ApexCharts(el.querySelector('.chart-left'), oLeft);
        let rightChart = new ApexCharts(el.querySelector('.chart-right'), oRight);

        leftChart.render();
        rightChart.render();


    </script>
@endscript
