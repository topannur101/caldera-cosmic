<?php

namespace Database\Seeders;

use App\Models\InsDwpCount;
use App\Models\InsDwpDevice;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InsDwpCountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting DWP count data generation...');

        // Use default values for data generation
        $days = 30;
        $density = 'medium';
        $resetProbability = 0.02;

        // Get all lines from active devices
        $lines = $this->getAllLines();
        
        if (empty($lines)) {
            $this->command->error('❌ No active devices found. Run InsDwpDeviceSeeder first.');
            return;
        }

        $this->command->info("📊 Generating data for " . count($lines) . " lines over {$days} days");
        
        $totalRecords = 0;
        
        foreach ($lines as $line) {
            $this->command->info("  → Processing line {$line}");
            $recordCount = $this->generateDataForLine($line, $days, $density, $resetProbability);
            $totalRecords += $recordCount;
        }

        $this->command->info("✅ Generated {$totalRecords} count records across " . count($lines) . " lines");
    }

    /**
     * Get all lines from active devices
     */
    private function getAllLines(): array
    {
        return InsDwpDevice::active()
            ->get()
            ->flatMap(fn($device) => $device->getLines())
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Generate realistic data for a single line
     */
    private function generateDataForLine(string $line, int $days, string $density, float $resetProbability): int
    {
        $recordCount = 0;
        $currentCumulative = rand(1000, 10000); // Random starting cumulative
        $hasReset = false;
        $resetDay = rand(5, $days - 5); // Random day for potential reset

        // Define line characteristics
        $lineProfile = $this->getLineProfile($line);
        
        // Generate data for each day
        for ($dayOffset = $days; $dayOffset >= 0; $dayOffset--) {
            $currentDate = Carbon::now()->subDays($dayOffset);
            
            // Check if this should be a reset day
            if (!$hasReset && $dayOffset <= $resetDay && rand(1, 100) <= ($resetProbability * 100)) {
                $currentCumulative = $this->simulateCounterReset($line, $currentDate, $currentCumulative);
                $hasReset = true;
                $recordCount++;
                continue;
            }
            
            // Generate data points for the day
            $dayRecords = $this->generateDayData($line, $currentDate, $lineProfile, $density, $currentCumulative);
            $recordCount += count($dayRecords);
            
            // Update cumulative for next day
            if (!empty($dayRecords)) {
                $currentCumulative = end($dayRecords)->cumulative;
            }
        }
        
        return $recordCount;
    }

    /**
     * Get production profile for a line
     */
    private function getLineProfile(string $line): array
    {
        $profiles = [
            // High-volume lines
            'A1' => ['type' => 'high', 'base_rate' => 100, 'variation' => 50],
            'B1' => ['type' => 'high', 'base_rate' => 120, 'variation' => 60],
            
            // Medium-volume lines  
            'A2' => ['type' => 'medium', 'base_rate' => 60, 'variation' => 30],
            'G1' => ['type' => 'medium', 'base_rate' => 70, 'variation' => 35],
            
            // Variable/lower-volume lines
            'B2' => ['type' => 'variable', 'base_rate' => 40, 'variation' => 40],
            'B3' => ['type' => 'variable', 'base_rate' => 45, 'variation' => 35],
            'G2' => ['type' => 'low', 'base_rate' => 30, 'variation' => 20],
        ];

        return $profiles[$line] ?? ['type' => 'medium', 'base_rate' => 50, 'variation' => 25];
    }

    /**
     * Generate data for a single day
     */
    private function generateDayData(string $line, Carbon $date, array $profile, string $density, int &$currentCumulative): array
    {
        $records = [];
        $isWeekend = $date->isWeekend();
        
        // Determine data point frequency based on density
        $intervals = match($density) {
            'high' => 5,    // Every 5 minutes during active hours
            'medium' => 10, // Every 10 minutes
            'low' => 15,    // Every 15 minutes
            default => 10
        };

        // Work hours: 7 AM to 6 PM on weekdays, reduced hours on weekends
        $startHour = $isWeekend ? 9 : 7;
        $endHour = $isWeekend ? 16 : 18;
        
        // Generate data points throughout the day
        for ($hour = $startHour; $hour <= $endHour; $hour++) {
            $pointsInHour = 60 / $intervals;
            
            for ($point = 0; $point < $pointsInHour; $point++) {
                $minute = $point * $intervals;
                $timestamp = $date->copy()->setTime($hour, $minute, rand(0, 59));
                
                // Calculate incremental based on time and line profile
                $incremental = $this->calculateIncremental($hour, $profile, $isWeekend);
                
                if ($incremental > 0) {
                    $currentCumulative += $incremental;
                    
                    $record = InsDwpCount::create([
                        'line' => $line,
                        'cumulative' => $currentCumulative,
                        'incremental' => $incremental,
                        'created_at' => $timestamp,
                    ]);
                    
                    $records[] = $record;
                }
            }
        }

        // Add some night activity (10-20% of day activity)
        if (rand(1, 100) <= 30) { // 30% chance of night activity
            $nightIncremental = $this->calculateNightIncremental($profile);
            if ($nightIncremental > 0) {
                $currentCumulative += $nightIncremental;
                
                $nightTime = $date->copy()->setTime(rand(22, 23), rand(0, 59), rand(0, 59));
                $record = InsDwpCount::create([
                    'line' => $line,
                    'cumulative' => $currentCumulative,
                    'incremental' => $nightIncremental,
                    'created_at' => $nightTime,
                ]);
                
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Calculate incremental value based on time and profile
     */
    private function calculateIncremental(int $hour, array $profile, bool $isWeekend): int
    {
        $baseRate = $profile['base_rate'];
        $variation = $profile['variation'];
        
        // Time-based multipliers
        $timeMultiplier = match(true) {
            $hour >= 8 && $hour <= 11 => 1.2,  // Morning peak
            $hour >= 12 && $hour <= 13 => 0.7, // Lunch break
            $hour >= 14 && $hour <= 16 => 1.1, // Afternoon peak  
            $hour >= 17 && $hour <= 18 => 0.8, // End of day
            default => 1.0
        };
        
        // Weekend reduction
        if ($isWeekend) {
            $timeMultiplier *= 0.6;
        }
        
        // Add random variation
        $randomFactor = (rand(-$variation, $variation) / 100) + 1;
        
        $incremental = (int) round($baseRate * $timeMultiplier * $randomFactor);
        
        return max(0, $incremental); // Ensure non-negative
    }

    /**
     * Calculate night incremental activity
     */
    private function calculateNightIncremental(array $profile): int
    {
        $baseRate = $profile['base_rate'];
        $nightRate = (int) round($baseRate * 0.15); // 15% of day rate
        
        return rand(1, max(1, $nightRate));
    }

    /**
     * Simulate a counter reset event
     */
    private function simulateCounterReset(string $line, Carbon $date, int $previousCumulative): int
    {
        $resetTime = $date->copy()->setTime(rand(6, 8), rand(0, 59), rand(0, 59));
        $newStart = rand(0, 100); // Counter starts from 0-100 after reset
        
        InsDwpCount::create([
            'line' => $line,
            'cumulative' => $newStart,
            'incremental' => $newStart, // After reset, incremental equals cumulative
            'created_at' => $resetTime,
        ]);

        $this->command->comment("    🔄 Counter reset for line {$line} at {$resetTime->format('Y-m-d H:i')} (was {$previousCumulative}, now {$newStart})");
        
        return $newStart;
    }
}