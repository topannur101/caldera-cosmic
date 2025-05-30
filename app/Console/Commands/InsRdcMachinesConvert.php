<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InsRdcMachine;
use Illuminate\Support\Facades\DB;

class InsRdcMachinesConvert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-rdc-machines-convert {--dry-run : Show what would be converted without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert legacy RDC machine configurations to new format';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all machines that need conversion
        $machines = InsRdcMachine::all();
        
        if ($machines->isEmpty()) {
            $this->info('No machines found to convert.');
            return 0;
        }

        $this->info("Found {$machines->count()} machine(s) to process...");
        $this->newLine();

        $convertedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($machines as $machine) {
            $this->line("Processing Machine #{$machine->number} - {$machine->name}");
            
            try {
                // Check if machine already has type set (already converted)
                if (!empty($machine->type) && $machine->type !== 'excel') {
                    $this->line("  ⏭️  Already converted (type: {$machine->type})");
                    $skippedCount++;
                    continue;
                }

                // Parse existing cells configuration
                $legacyCells = $machine->cells;
                
                if (empty($legacyCells)) {
                    $this->line("  ⚠️  No configuration found, setting as Excel with empty config");
                    
                    if (!$isDryRun) {
                        $machine->update([
                            'type' => 'excel',
                            'cells' => json_encode([])
                        ]);
                    }
                    
                    $convertedCount++;
                    continue;
                }

                // Determine machine type based on configuration
                $machineType = $this->determineMachineType($legacyCells);
                $newConfig = $this->convertConfiguration($legacyCells, $machineType);
                
                $this->line("  📝 Type: {$machineType}");
                $this->line("  📊 Fields: " . count($newConfig));
                
                if ($this->output->isVerbose()) {
                    foreach ($newConfig as $field) {
                        if ($machineType === 'excel') {
                            $this->line("    - {$field['field']}: {$field['address']}");
                        } else {
                            $this->line("    - {$field['field']}: {$field['pattern']}");
                        }
                    }
                }

                if (!$isDryRun) {
                    $machine->update([
                        'type' => $machineType,
                        'cells' => json_encode($newConfig)
                    ]);
                }

                $this->line("  ✅ Converted successfully");
                $convertedCount++;

            } catch (\Exception $e) {
                $this->line("  ❌ Error: " . $e->getMessage());
                $errorCount++;
            }
            
            $this->newLine();
        }

        // Summary
        $this->info('=== CONVERSION SUMMARY ===');
        $this->line("✅ Converted: {$convertedCount}");
        $this->line("⏭️  Skipped: {$skippedCount}");
        $this->line("❌ Errors: {$errorCount}");
        
        if ($isDryRun) {
            $this->newLine();
            $this->info('This was a dry run. To apply changes, run:');
            $this->line('php artisan app:ins-rdc-machines-convert');
        }

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Determine machine type based on legacy configuration
     */
    private function determineMachineType(array $legacyCells): string
    {
        // Check if any field has an "address" key (Excel format)
        foreach ($legacyCells as $cell) {
            if (isset($cell['address'])) {
                return 'excel';
            }
            if (isset($cell['pattern'])) {
                return 'txt';
            }
        }
        
        // Default to excel if we can't determine
        return 'excel';
    }

    /**
     * Convert legacy configuration to new format
     */
    private function convertConfiguration(array $legacyCells, string $machineType): array
    {
        $newConfig = [];

        foreach ($legacyCells as $cell) {
            if (!isset($cell['field'])) {
                continue;
            }

            $field = $cell['field'];

            if ($machineType === 'excel') {
                // Excel format: keep address if exists
                if (isset($cell['address'])) {
                    $newConfig[] = [
                        'field' => $field,
                        'address' => strtoupper(trim($cell['address']))
                    ];
                }
            } else {
                // TXT format: convert or keep pattern if exists
                if (isset($cell['pattern'])) {
                    $newConfig[] = [
                        'field' => $field,
                        'pattern' => $cell['pattern']
                    ];
                } else {
                    // Convert from address to default pattern if possible
                    $defaultPattern = $this->getDefaultPatternForField($field);
                    if ($defaultPattern) {
                        $newConfig[] = [
                            'field' => $field,
                            'pattern' => $defaultPattern
                        ];
                    }
                }
            }
        }

        return $newConfig;
    }

    /**
     * Get default TXT pattern for a field
     */
    private function getDefaultPatternForField(string $field): ?string
    {
        $defaultPatterns = [
            's_min' => '^ML\s+(\d+\.\d+)',
            's_max' => '^MH\s+(\d+\.\d+)',
            'tc10' => '^t10\s+(\d+\.\d+)',
            'tc50' => '^t50\s+(\d+\.\d+)',
            'tc90' => '^t90\s+(\d+\.\d+)',
            'code_alt' => 'Orderno\.:?\s*(\d+)',
            'mcs' => 'OG\/RS\s+(\d{3})',
            'color' => 'Description:\s*([^$]+)',
            'eval' => 'Status:\s*(Pass|Fail)',
        ];

        return $defaultPatterns[$field] ?? null;
    }
}