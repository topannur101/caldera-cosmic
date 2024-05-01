<?php

use Livewire\Volt\Component;

new class extends Component {}; ?>

<div class="w-full h-screen p-4">
    <div id="chart-left"></div>
    <div id="chart-right"></div>
</div>

@script
    <script>
        let oLeftPts = {
            chart: {
                height: 350,
                animations: {
                    enabled: false,
                    animateGradually: {
                        enabled: false,
                    },
                    dynamicAnimation: {
                        enabled: false,
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            series: [{
                name: 'Left',
                type: 'area',
                data: []
            },{
                name: 'Middle',
                type: 'line',
                data: []
            }],
            xaxis: {
                type: 'datetime',
                range: 60000
            },
            yaxis: {
                min: 0,
                max: 10

            },
            stroke: {
                curve: 'smooth'
            }
        };

        let oRightPts = {
            chart: {
                type: 'line',
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
                data: []
            }],
            xaxis: {
                type: 'datetime',
                range: 60000
            },
            yaxis: {
                min: 0,
                max: 10

            },
            stroke: {
                curve: 'smooth'
            }
        };

        let leftChart = new ApexCharts(document.querySelector("#chart-left"), oLeftPts);
        let rightChart = new ApexCharts(document.querySelector("#chart-right"), oRightPts);

        leftChart.render();
        rightChart.render();

        const leftPts   = oLeftPts.series[0].data;
        const leftMids  = oLeftPts.series[1].data;
        const rightPts  = oRightPts.series[0].data;

        setInterval(function() {
            // Generate or fetch new data point
            // axios.get('{{ route('insight.rtc.latest', ['device_id' => 1]) }}')
            //     .then(response => {
            //         let x = new Date(response.data.data.dt_client).getTime();
            //         let y = 0;

            //         // y   = parseFloat(response.data.data.act_left);
            //         leftPts.push({
            //             x,
            //             y
            //         });
            //         leftChart.updateSeries([{
            //             data: leftPts
            //         }]);

            //         y = parseFloat(response.data.data.act_right);
            //         rightPts.push({
            //             x,
            //             y
            //         });
            //         rightChart.updateSeries([{
            //             data: rightPts
            //         }]);
            //     })
            //     .catch(error => {
            //         console.error('Error fetching data:', error);
            //     });
            // Run simulation
            let x   = new Date().getTime();
            let y = 3.5;

            // y   = parseFloat(response.data.data.act_left);
            leftPts.push({x, y});
            y = 2.0;
            leftMids.push({x, y});
            leftChart.updateSeries([{
                name: 'Left',
                data: leftPts
            },{
                name: 'Middle',
                data: leftMids
            }]);

            rightPts.push({x, y});
            rightChart.updateSeries([{
                data: rightPts
            }]);

        }, 1000);
    </script>
@endscript
