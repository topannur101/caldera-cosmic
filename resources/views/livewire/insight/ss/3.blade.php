<?php

use Livewire\Volt\Component;
use App\Models\InsRtcDevice;

new class extends Component {

    #[Url]
    public $sline;
    public $lines;

    public function mount(): void
    {
        $this->sline = 1;
        $this->lines = InsRtcDevice::all()->pluck('line');
    }

    public function with(): array
    {
        $device = InsRtcDevice::where('line', $this->sline)->first();
        return [
            'device_id' => $device->id ?? 1,
        ];
    }

}; ?>

<div class="w-full h-screen p-4">
    <div class="h-2/6 px-4 py-8">
        <div class="grid grid-cols-6 grid-rows-1 gap-4">
            <div class="col-span-2">
                <div>
                    <div class="text-4xl uppercase mb-3 mx-1">{{ __('Model') }}</div>
                    <div class="text-6xl" id="recipe-name"></div>
                </div>
            </div>
            <div class="col-start-3">
                <div>
                    <div class="text-4xl uppercase mb-3 mx-1">{{ __('OG/RS') }}</div>
                    <div class="text-8xl font-bold py-3" id="recipe-og-rs"></div>
                </div>
            </div>
            <div class="col-start-4">
                <div>
                    <div class="text-4xl uppercase mb-3 mx-1">{{ __('Min') }}</div>
                    <div class="text-8xl font-bold py-3" id="recipe-std-min"></div>
                </div>
            </div>
            <div class="col-start-5">
                <div>
                    <div class="text-4xl uppercase mb-3 mx-1">{{ __('Maks') }}</div>
                    <div class="text-8xl font-bold py-3" id="recipe-std-max"></div>
                </div>
            </div>
            <div class="col-start-6">
                <div>
                    <div class="text-4xl uppercase mb-3 mx-1">{{ __('Line') }}</div>
                    <x-select wire:model.live="sline" class="text-8xl font-bold">
                        <option value=""></option>
                        @foreach ($lines as $line)
                            <option value="{{ $line }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
        </div>
    </div>
    <div x-data="{ act_left: 0, act_right: 0}" wire:key="chart-container" class="h-4/6 grid grid-cols-2 gap-4">
        <div
            class="w-full h-full flex flex-col px-8 py-6 bg-white text-neutral-600 shadow-md overflow-hidden sm:rounded-lg">
            <div class="flex-none w-full flex justify-between">
                <div class="text-6xl uppercase">{{ __('Kiri') }}</div>
                <div class="text-8xl font-bold" id="act-left"></div>
            </div>
            <div class="flex-1">
                <div id="chart-left"></div>
            </div>
        </div>

        <div
            class="w-full h-full flex flex-col px-8 py-6 bg-white text-neutral-600 shadow-md overflow-hidden sm:rounded-lg">
            <div class="flex-none w-full flex justify-between">
                <div class="text-6xl uppercase">{{ __('Kanan') }}</div>
                <div class="text-8xl font-bold" id="act-right"></div>
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
                height: 350,
                animations: {
                    enabled: true,
                    easing: 'linear',
                    dynamicAnimation: {
                        speed: 1000
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            series: [{
                name: 'Left',
                type: 'area',
                data: [],
                color: '#b4a5e1'
            },{
                name: 'Middle',
                type: 'line',
                data: [],
                color: '#60a055'
            }],
            xaxis: {
                type: 'datetime',
                range: 60000
            },
            yaxis: {
                min: 1,
                max: 6

            },
            stroke: {
                curve: 'smooth',
                width: [0, 3],
                dashArray: [0, 6]
            },
        };

        let oRight = {
            chart: {
                type: 'line',
                toolbar: {
                    show: false
                },
                height: 350,
                animations: {
                    enabled: true,
                    easing: 'linear',
                    dynamicAnimation: {
                        speed: 1000
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            series: [{
                name: 'Right',
                type: 'area',
                data: [],
                color: '#b4a5e1'
            },{
                name: 'Middle',
                type: 'line',
                data: [],
                color: '#60a055'
            }],
            xaxis: {
                type: 'datetime',
                range: 60000
            },
            yaxis: {
                min: 1,
                max: 6

            },
            stroke: {
                curve: 'smooth',
                width: [0, 3],
                dashArray: [0, 6]
            },
        };

        let leftChart = new ApexCharts(document.querySelector("#chart-left"), oLeft);
        let rightChart = new ApexCharts(document.querySelector("#chart-right"), oRight);
        let recipeUrl, recipeId, recipeStdMid;

        const recipeName      = document.getElementById("recipe-name");
        const recipeOgRs      = document.getElementById("recipe-og-rs");
        const recipeStdMin    = document.getElementById("recipe-std-min");
        const recipeStdMax    = document.getElementById("recipe-std-max");

        const actLeft   = document.getElementById("act-left");
        const actRight  = document.getElementById("act-right");

        leftChart.render();
        rightChart.render();

        const leftPts   = oLeft.series[0].data;
        const leftMid   = oLeft.series[1].data;
        const rightPts  = oRight.series[0].data;
        const rightMid  = oRight.series[1].data;

        setInterval(function() {
            // Generate or fetch new data point
            axios.get('{{ route("insight.rtc.metric", ["device_id" => $device_id]) }}')
                .then(response => {

                    if (recipeId !== response.data.data.recipe_id) {

                        recipeUrl = '{{ route("insight.rtc.recipe", ["recipe_id" => "__recipeId__"]) }}'
                        recipeId = response.data.data.recipe_id
                        recipeUrl = recipeUrl.replace('__recipeId__', recipeId)

                        axios.get(recipeUrl)
                        .then(response => {
                            recipeName.textContent      = response.data.data.name
                            recipeOgRs.textContent      = response.data.data.og_rs
                            recipeStdMin.textContent    = response.data.data.std_min
                            recipeStdMax.textContent    = response.data.data.std_max
                        })
                        .catch(error => {
                            console.error('Error fetching recipe:', error);
                        });
                    }

                    actLeft.textContent         = response.data.data.act_left
                    actRight.textContent        = response.data.data.act_right

                    let x = new Date(response.data.data.dt_client).getTime();
                    let y = 0;

                    y = parseFloat(response.data.data.act_left);
                    leftPts.push({ x, y });

                    y = recipeStdMid;
                    leftMid.push({x, y});

                    leftChart.updateSeries([{
                        name: 'Left',
                        data: leftPts
                    },{
                        name: 'Middle',
                        data: leftMid
                    }]);

                    y = parseFloat(response.data.data.act_right);
                    rightPts.push({ x, y });

                    y = recipeStdMid;
                    rightMid.push({ x, y});
                    
                    rightChart.updateSeries([{
                        name: 'Right',
                        data: rightPts
                    },{
                        name: 'Middle',
                        data: rightMid
                    }]);
                })
                .catch(error => {
                    console.error('Error fetching metric:', error);
                });
            // Run simulation
            // let x   = new Date().getTime();
            // let y = 3.5;

            // // y   = parseFloat(response.data.data.act_left);
            // leftPts.push({x, y});
            // y = 2.0;
            // leftMid.push({x, y});
            // leftChart.updateSeries([{
            //     name: 'Left',
            //     data: leftPts
            // },{
            //     name: 'Middle',
            //     data: leftMid
            // }]);

            // rightPts.push({x, y});
            // rightChart.updateSeries([{
            //     data: rightPts
            // }]);

        }, 1000);
    </script>
@endscript
