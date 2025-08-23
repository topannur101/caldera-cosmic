<?php

namespace App\Console\Commands;

use App\Models\InsStcDSum;
use Illuminate\Console\Command;

class InsStcFixAtValues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-stc-fix-at-values {--dry-run : Preview changes without applying them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix inconsistent AT values formatting to ensure all elements are floats with 1 decimal place';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
        } else {
            $this->info('🔧 LIVE MODE - Changes will be applied to database');
        }

        $this->newLine();

        // Get all d_sums with at_values
        $dSums = InsStcDSum::whereNotNull('at_values')->get();

        $this->info("Found {$dSums->count()} records with at_values");
        $this->newLine();

        $fixedCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($dSums as $dSum) {
            try {
                $originalAtValues = json_decode($dSum->at_values, true);

                // Skip if already null or not an array
                if (! is_array($originalAtValues)) {
                    $this->warn("ID {$dSum->id}: Skipping - at_values is not a valid array");
                    $skippedCount++;

                    continue;
                }

                // Skip if doesn't have exactly 3 elements
                if (count($originalAtValues) !== 3) {
                    $this->warn("ID {$dSum->id}: Skipping - at_values doesn't have exactly 3 elements");
                    $skippedCount++;

                    continue;
                }

                // Format each element consistently
                $fixedAtValues = [
                    round((float) $originalAtValues[0], 1),
                    round((float) $originalAtValues[1], 1),
                    round((float) $originalAtValues[2], 1),
                ];

                // Check if formatting is needed
                if ($originalAtValues === $fixedAtValues) {
                    // Already correctly formatted
                    continue;
                }

                // Show the change
                $this->line("ID {$dSum->id}: ".json_encode($originalAtValues).' → '.json_encode($fixedAtValues));

                if (! $isDryRun) {
                    // Apply the fix
                    $dSum->at_values = json_encode($fixedAtValues);
                    $dSum->save();
                }

                $fixedCount++;

            } catch (\Exception $e) {
                $this->error("ID {$dSum->id}: Error - ".$e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();

        // Summary
        if ($isDryRun) {
            $this->info('📊 DRY RUN SUMMARY:');
        } else {
            $this->info('✅ OPERATION COMPLETE:');
        }

        $this->table(
            ['Status', 'Count'],
            [
                ['Fixed/Would Fix', $fixedCount],
                ['Skipped', $skippedCount],
                ['Errors', $errorCount],
                ['Total Processed', $dSums->count()],
            ]
        );

        if ($isDryRun && $fixedCount > 0) {
            $this->newLine();
            $this->comment('💡 Run without --dry-run to apply these changes:');
            $this->comment('   php artisan app:ins-stc-fix-at-values');
        }

        return Command::SUCCESS;
    }
}
