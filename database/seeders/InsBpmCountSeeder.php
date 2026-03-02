<?php

namespace Database\Seeders;

use App\Models\InsBpmCount;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InsBpmCountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define plant-line mapping
        $plantLines = [
            'A' => ['1', '2'],
            'B' => ['1', '2', '3'],
            'C' => ['1', '2'],
            'D' => ['1', '2', '3'],
            'E' => ['1', '2'],
            'F' => ['1', '2', '3'],
            'G' => ['1', '2'],
            'H' => ['1', '2'],
            'I' => ['1', '2', '3'],
            'J' => ['1'],
        ];
        $machines = ['M1', 'M2', 'M3', 'M4'];
        $conditions = ['hot', 'cold'];

        // Clear existing data
        InsBpmCount::truncate();

        // Generate data for the last 30 days
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $this->command->info('Generating BPM Count data...');

        $days = 30;
        $progressBar = $this->command->getOutput()->createProgressBar($days);

        // Track cumulative values per plant-line-machine-condition combination
        $cumulatives = [];

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            // Generate 10-25 total emergencies per day (across all plants/lines/machines)
            $entriesPerDay = rand(10, 25);

            for ($i = 0; $i < $entriesPerDay; $i++) {
                // Random plant
                $plant = array_rand($plantLines);
                $lines = $plantLines[$plant];
                $line = $lines[array_rand($lines)];
                $machine = $machines[array_rand($machines)];
                $condition = $conditions[array_rand($conditions)];

                $key = "{$plant}_{$line}_{$machine}_{$condition}";

                // Initialize cumulative if not exists
                if (! isset($cumulatives[$key])) {
                    $cumulatives[$key] = rand(50, 200);
                }

                // Random time during work hours (7:00 - 17:00)
                $hour = rand(7, 16);
                $minute = rand(0, 59);
                $second = rand(0, 59);

                $timestamp = $date->copy()
                    ->setHour($hour)
                    ->setMinute($minute)
                    ->setSecond($second);

                // Skip if timestamp is in the future
                if ($timestamp > Carbon::now()) {
                    continue;
                }

                // Random incremental value (emergency button presses)
                $incremental = rand(1, 3);

                // Update cumulative
                $cumulatives[$key] += $incremental;

                InsBpmCount::create([
                    'plant' => $plant,
                    'line' => $line,
                    'machine' => $machine,
                    'condition' => $condition,
                    'incremental' => $incremental,
                    'cumulative' => $cumulatives[$key],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info('BPM Count data generated successfully!');

        // Display summary by plant
        $totalRecords = InsBpmCount::count();
        $this->command->info("Total records created: {$totalRecords}");

        $this->command->newLine();
        $this->command->info('Cumulative by Plant:');
        $this->command->table(
            ['Plant', 'Max Cumulative'],
            InsBpmCount::selectRaw('plant, MAX(cumulative) as max_cumulative')
                ->groupBy('plant')
                ->orderBy('plant')
                ->get()
                ->map(fn ($row) => [$row->plant, number_format($row->max_cumulative)])
                ->toArray()
        );
    }
}
