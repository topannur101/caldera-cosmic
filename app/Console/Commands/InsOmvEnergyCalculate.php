<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InsOmvMetric;

class InsOmvEnergyCalculate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-omv-energy-calculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $metrics = InsOmvMetric::all();

        foreach ($metrics as $metric) {
            $data = json_decode($metric->data, true);
            $amps = $data['amps'] ?? [];

            $voltage = 220; // Voltage in Volts
            $kwhUsage = 0; // Initialize total energy

            for ($i = 1; $i < count($amps); $i++) {
                // Use average current of the interval
                $avgCurrent = ($amps[$i]['value'] + $amps[$i - 1]['value']) / 2;
                $timeInterval = ($amps[$i]['taken_at'] - $amps[$i - 1]['taken_at']) / 3600; // Convert time interval to hours
                
                // Calculate energy for the interval (including power factor)
                $energy = (sqrt(3) * $avgCurrent * $voltage * $timeInterval) / 1000;
                $kwhUsage += $energy; // Sum up the energy
            }

            $metric->kwh_usage = $kwhUsage;
            $metric->save();
            $this->info("Updated kwh_usage for metric {$metric->id}");
        }
    }
}
