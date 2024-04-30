<?php

use Livewire\Volt\Component;

new class extends Component {}; ?>

<div class="w-full h-screen p-4">
    <div id="chart-left"></div>
    <div id="chart-right"></div>
</div>

@script
    <script>
    let optionsLeft = {
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

    let optionsRight = {
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

    let leftChart = new ApexCharts(document.querySelector("#chart-left"), optionsLeft);
    let rightChart = new ApexCharts(document.querySelector("#chart-right"), optionsRight);

    leftChart.render();
    rightChart.render();

    const leftPoints    = optionsLeft.series[0].data;
    const rightPoints   = optionsRight.series[0].data;

    setInterval(function () {
        // Generate or fetch new data point
        axios.get('{{ route('insight.rtc.latest', ['device_id' => 1]) }}')
        .then(response => {
            let x   = new Date(response.data.data.dt_client).getTime();
            let y = 0;

            y   = parseFloat(response.data.data.act_left);
            leftPoints.push({x, y});
            leftChart.updateSeries([{
                data: leftPoints
            }]);

            y = parseFloat(response.data.data.act_right);
            rightPoints.push({x, y});
            rightChart.updateSeries([{
                data: rightPoints
            }]);
        })
        .catch(error => {
            console.error('Error fetching data:', error);
        });

    }, 1000);
    </script>
@endscript
