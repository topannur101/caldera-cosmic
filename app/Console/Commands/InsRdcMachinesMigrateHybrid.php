<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InsRdcMachine;
use Illuminate\Support\Facades\DB;

class InsRdcMachinesMigrateHybrid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-rdc-machines-migrate-hybrid {--dry-run : Show what would be migrated without making changes} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy RDC machine configurations to hybrid format (static/dynamic)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all machines
        $machines = InsRdcMachine::all();
        
        if ($machines->isEmpty()) {
            $this->info('No machines found to migrate.');
            return 0;
        }

        // Filter machines that need migration
        $legacyMachines = $machines->filter(function ($machine) {
            return $machine->hasLegacyConfig();
        });

        if ($legacyMachines->isEmpty()) {
            $this->info('✅ All machines are already using hybrid configuration format.');
            return 0;
        }

        $this->info("Found {$legacyMachines->count()} machine(s) with legacy configuration...");
        $this->newLine();

        // Show what will be migrated
        foreach ($legacyMachines as $machine) {
            $this->showMigrationPreview($machine);
        }

        // Confirmation
        if (!$isDryRun && !$force) {
            $this->newLine();
            $this->warn('⚠️  This will update machine configurations to hybrid format!');
            
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        // Perform migration
        if (!$isDryRun) {
            $this->info('🔄 Starting migration...');
            $this->newLine();
        }

        $migratedCount = 0;
        $errorCount = 0;

        foreach ($legacyMachines as $machine) {
            try {
                $this->line("Migrating Machine #{$machine->number} - {$machine->name}");
                
                if (!$isDryRun) {
                    $success = $machine->migrateLegacyConfig();
                    
                    if ($success) {
                        $this->line("  ✅ Migrated successfully");
                        $migratedCount++;
                    } else {
                        $this->line("  ❌ Migration failed");
                        $errorCount++;
                    }
                } else {
                    $this->line("  📝 Would be migrated");
                    $migratedCount++;
                }

            } catch (\Exception $e) {
                $this->line("  ❌ Error: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();

        // Summary
        $this->info('=== MIGRATION SUMMARY ===');
        $this->line("✅ Migrated: {$migratedCount}");
        $this->line("❌ Errors: {$errorCount}");
        $this->line("⏭️  Already hybrid: " . ($machines->count() - $legacyMachines->count()));
        
        if ($isDryRun) {
            $this->newLine();
            $this->info('This was a dry run. To apply changes, run:');
            $this->line('php artisan app:ins-rdc-machines-migrate-hybrid');
        }

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Show migration preview for a machine
     */
    private function showMigrationPreview(InsRdcMachine $machine): void
    {
        $this->line("Machine #{$machine->number} - {$machine->name} ({$machine->type})");
        
        $currentConfig = $machine->cells ?? [];
        
        foreach ($currentConfig as $field) {
            if (!isset($field['field'])) {
                continue;
            }

            $fieldName = $field['field'];
            
            if (isset($field['address'])) {
                // Excel legacy format
                $this->line("  📍 {$fieldName}: address '{$field['address']}' → static '{$field['address']}'");
            } elseif (isset($field['pattern'])) {
                // TXT format (keeping as pattern type)
                $this->line("  🔍 {$fieldName}: pattern '{$field['pattern']}' → pattern '{$field['pattern']}'");
            } else {
                $this->line("  ⚠️  {$fieldName}: unknown format");
            }
        }
        
        $this->newLine();
    }
}