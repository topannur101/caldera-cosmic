<?php

use Livewire\Volt\Component;

new class extends Component {}; ?>

<div class="w-full h-screen p-4">
    <div id="kiri"></div>
    <div id="kanan"></div>
</div>

@script
    <script>
    let options = {
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
            max: 100

        },
        stroke: {
            curve: 'smooth'
        }
    };

    let kiri = new ApexCharts(document.querySelector("#kiri"), options);
    let kanan = new ApexCharts(document.querySelector("#kanan"), options);
    kiri.render();
    kanan.render();

    const dataPoints = options.series[0].data;

    let previousNumber = 30; 
    function getRandomNumber() {
        // Calculate the minimum and maximum numbers allowed for the next number
        let min = Math.max(30, previousNumber - 10);
        let max = Math.min(70, previousNumber + 10);

        // Generate a random number within the allowed range
        let randomNumber = Math.floor(Math.random() * (max - min + 1)) + min;

        // Update the previous number
        previousNumber = randomNumber;

        return randomNumber;
    }

    setInterval(function () {
        // Generate or fetch new data point
        let x = new Date().getTime(), // current time
            y = getRandomNumber(); // random value
        dataPoints.push({x, y});

        // Remove the oldest data point if necessary
        // if (dataPoints.length > 100) {
        //     for (let i = 0; i < 90; i++) {
        //         dataPoints.shift();
        //     }
        // }
        console.log(x)
        console.log(y)
        console.log(dataPoints);
        kiri.updateSeries([{
            data: dataPoints
        }]);
        kanan.updateSeries([{
            data: dataPoints
        }]);
    }, 1000);
    </script>
@endscript
