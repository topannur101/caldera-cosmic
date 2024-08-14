<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use App\Models\InsRtcDevice;

new class extends Component {

    #[Url]
    public $device_id;
    public $devices = [];

    public function mount(): void
    {
        $this->devices = InsRtcDevice::all();
    }
    
}; ?>

<x-slot name="title">{{ __('RTC') . ' - ' . __('Bagan garis waktu nyata') }}</x-slot>
<div class="w-full h-screen p-4">
    <div class="h-2/6 p-4">
        <div class="flex w-full justify-between">
            <div class="truncate">
                <div>
                    <div class="text-4xl text-neutral-400 uppercase m-1"><x-link class="inline-block" href="{{ route('insight.rtc.slideshows')}}"><i class="fa fa-fw fa-chevron-left"></i></x-link>
                        {{ __('Model') }}</div>
                    <div class="text-6xl truncate font-bold py-3" id="recipe-name">???</div>
                </div>
            </div>
            <div class="px-4">
                <div>
                    <div class="text-4xl text-neutral-400 uppercase m-1">{{ __('OG/RS') }}</div>
                    <div class="text-6xl font-bold py-3" id="recipe-og-rs">000</div>
                </div>
            </div>
            <div class="px-4">
                <div>
                    <div class="text-4xl text-neutral-400 uppercase m-1">{{ __('Min') }}</div>
                    <div class="text-6xl font-bold py-3" id="recipe-std-min">0.00</div>
                </div>
            </div>
            <div class="px-4">
                <div>
                    <div class="text-4xl text-neutral-400 uppercase m-1">{{ __('Maks') }}</div>
                    <div class="text-6xl font-bold py-3" id="recipe-std-max">0.00</div>
                </div>
            </div>
            <div class="pl-4 w-40">
                <div>
                    <div class="text-4xl text-neutral-400 uppercase m-1">{{ __('Line') }}</div>
                    <x-select wire:model.live="device_id" class="text-6xl font-bold w-full">
                        <option value=""></option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}">{{ $device->line }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
        </div>
    </div>
    <div x-data="{ sensor_left: 0, sensor_right: 0 }" wire:key="chart-container" class="h-4/6 grid grid-cols-2 gap-4">
        <div
            class="w-full h-full flex flex-col px-8 py-6 bg-white text-neutral-600 shadow-md overflow-hidden sm:rounded-lg">
            <div class="flex-none w-full flex justify-between">
                <div class="text-4xl text-neutral-400 uppercase">{{ __('Kiri') }}</div>
                <div class="text-6xl font-bold" id="act-left"></div>
            </div>
            <div class="flex-1">
                <div id="chart-left"></div>
            </div>
        </div>

        <div
            class="w-full h-full flex flex-col px-8 py-6 bg-white text-neutral-600 shadow-md overflow-hidden sm:rounded-lg">
            <div class="flex-none w-full flex justify-between">
                <div class="text-4xl text-neutral-400 uppercase">{{ __('Kanan') }}</div>
                <div class="text-6xl font-bold" id="act-right"></div>
            </div>
            <div class="flex-1">
                <div id="chart-right"></div>
            </div>
        </div>
    </div>
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
            name: '{{ __("Sensor") }}',
            type: 'area',
            data: [],
            color: '#b4a5e1'
        }, {
            name: '{{ __("Minimum") }}',
            type: 'line',
            data: [],
            color: '#169292'
        }, {
            name: '{{ __("Maksimum") }}',
            type: 'line',
            data: [],
            color: '#169292'
        }, {
            name: '{{ __("Tengah") }}',
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
            name: '{{ __("Sensor") }}',
            type: 'area',
            data: [],
            color: '#b4a5e1'
        }, {
            name: '{{ __("Minimum") }}',
            type: 'line',
            data: [],
            color: '#169292'
        }, {
            name: '{{ __("Maksimum") }}',
            type: 'line',
            data: [],
            color: '#169292'
        }, {
            name: '{{ __("Tengah") }}',
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

    let leftChart = new ApexCharts(document.querySelector("#chart-left"), oLeft);
    let rightChart = new ApexCharts(document.querySelector("#chart-right"), oRight);
    let metricUrl, deviceId, recipeUrl, recipeId, recipeStdMin, recipeStdMax, recipeStdMid;

    recipeId = 0;

    const elRecipeName = document.getElementById("recipe-name");
    const elRecipeOgRs = document.getElementById("recipe-og-rs");
    const elRecipeStdMin = document.getElementById("recipe-std-min");
    const elRecipeStdMax = document.getElementById("recipe-std-max");
    const elActLeft = document.getElementById("act-left");
    const elActRight = document.getElementById("act-right");

    elActLeft.textContent = 0;
    elActRight.textContent = 0;

    leftChart.render();
    rightChart.render();

    const leftPts = oLeft.series[0].data;
    const leftMin = oLeft.series[1].data;
    const leftMax = oLeft.series[2].data;
    const leftMid = oLeft.series[3].data;
    const rightPts = oRight.series[0].data;
    const rightMin = oRight.series[1].data;
    const rightMax = oRight.series[2].data;
    const rightMid = oRight.series[3].data;

    const maxDataPoints = 100;  // Define the maximum number of data points to keep

    function updateSeriesData(seriesData, x, y) {
        if (seriesData.length >= maxDataPoints) {
            seriesData.shift();
        }
        seriesData.push({ x, y });
    }

    setInterval(function() {

        deviceId = parseInt($wire.device_id);

        if (deviceId > 0) {

            metricUrl = '{{ route('insight.rtc.metric', ['device_id' => '__deviceId__']) }}';
            metricUrl = metricUrl.replace('__deviceId__', deviceId);
            axios.get(metricUrl)
                .then(response => {

                    if (recipeId !== (parseInt(response.data.data.recipe_id))) {
                        recipeId = parseInt(response.data.data.recipe_id);
                        recipeUrl = '{{ route('insight.rtc.recipe', ['recipe_id' => '__recipeId__']) }}';
                        recipeUrl = recipeUrl.replace('__recipeId__', recipeId);

                        if (recipeId > 0) {
                            axios.get(recipeUrl)
                                .then(response => {
                                    const ogRsValue = response.data.data.og_rs.toString().padStart(3, '0');
                                    elRecipeName.textContent = response.data.data.name;
                                    elRecipeOgRs.textContent = ogRsValue;
                                    elRecipeStdMin.textContent = response.data.data.std_min;
                                    elRecipeStdMax.textContent = response.data.data.std_max;
                                    recipeStdMin = parseFloat(response.data.data.std_min);
                                    recipeStdMax = parseFloat(response.data.data.std_max);
                                    recipeStdMid = parseFloat(response.data.data.std_mid);
                                })
                                .catch(error => {
                                    console.error('Error fetching recipe:', error);
                                });
                        } else {
                            elRecipeName.textContent = '{{ __("Resep tidak diketahui") }}';
                            elRecipeOgRs.textContent = '???';
                            recipeStdMin.textContent = 0;
                            recipeStdMax.textContent = 0;
                        }
                    } 
                    elActLeft.textContent = response.data.data.sensor_left;
                    elActRight.textContent = response.data.data.sensor_right;

                    let x = new Date(response.data.data.dt_client).getTime();
                    let y = 0;

                    y = parseFloat(response.data.data.sensor_left);
                    updateSeriesData(leftPts, x, y);

                    y = recipeStdMin;
                    updateSeriesData(leftMin, x, y);

                    y = recipeStdMax;
                    updateSeriesData(leftMax, x, y);

                    y = recipeStdMid;
                    updateSeriesData(leftMid, x, y);

                    leftChart.updateSeries([{
                        name: '{{ __("Sensor") }}',
                        data: leftPts
                    }, {
                        name: '{{ __("Minimum") }}',
                        data: leftMin
                    }, {
                        name: '{{ __("Maksimum") }}',
                        data: leftMax
                    }, {
                        name: '{{ __("Tengah") }}',
                        data: leftMid
                    }]);

                    y = parseFloat(response.data.data.sensor_right);
                    updateSeriesData(rightPts, x, y);

                    y = recipeStdMin;
                    updateSeriesData(rightMin, x, y);

                    y = recipeStdMax;
                    updateSeriesData(rightMax, x, y);

                    y = recipeStdMid;
                    updateSeriesData(rightMid, x, y);

                    rightChart.updateSeries([{
                        name: '{{ __("Sensor") }}',
                        data: rightPts
                    }, {
                        name: '{{ __("Minimum") }}',
                        data: rightMin
                    }, {
                        name: '{{ __("Maksimum") }}',
                        data: rightMax
                    }, {
                        name: '{{ __("Tengah") }}',
                        data: rightMid
                    }]);
                })
                .catch(error => {
                    console.error('Error fetching metric:', error);
                });
        } else {
            recipeId = 0;
            leftChart.updateSeries([{
                name: '{{ __("Sensor") }}',
                data: []
            }, {
                name: '{{ __("Minimum") }}',
                data: []
            }, {
                name: '{{ __("Maksimum") }}',
                data: []
            }, {
                name: '{{ __("Tengah") }}',
                data: []
            }]);
            rightChart.updateSeries([{
                name: '{{ __("Sensor") }}',
                data: []
            }, {
                name: '{{ __("Minimum") }}',
                data: []
            }, {
                name: '{{ __("Maksimum") }}',
                data: []
            }, {
                name: '{{ __("Tengah") }}',
                data: []
            }]);
        }
    }, 3000);
</script>
@endscript
