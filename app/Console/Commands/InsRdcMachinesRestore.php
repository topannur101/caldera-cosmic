<?php

namespace App\Console\Commands;

use App\Models\InsRdcMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InsRdcMachinesRestore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-rdc-machines-restore {filename} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore RDC machine configurations from backup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('filename');
        $path = "backups/rdc_machines/{$filename}";

        if (! Storage::disk('local')->exists($path)) {
            $this->error("❌ Backup file not found: {$path}");

            return 1;
        }

        $this->info("📁 Found backup file: {$filename}");

        // Read backup data
        try {
            $backupContent = Storage::disk('local')->get($path);
            $backupData = json_decode($backupContent, true);

            if (! $backupData || ! isset($backupData['machines'])) {
                $this->error('❌ Invalid backup file format');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error reading backup file: '.$e->getMessage());

            return 1;
        }

        $machines = $backupData['machines'];
        $this->line("📊 Backup contains {$backupData['total_machines']} machine(s)");
        $this->line("📅 Created: {$backupData['created_at']}");

        // Show what will be restored
        $this->newLine();
        $this->info('Machines to restore:');
        foreach ($machines as $machine) {
            $configCount = count(json_decode($machine['cells'] ?? '[]', true));
            $type = $machine['type'] ?? 'unknown';
            $this->line("  - #{$machine['number']} {$machine['name']} ({$type}, {$configCount} fields)");
        }

        // Confirmation
        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('⚠️  This will REPLACE all existing machine configurations!');

            if (! $this->confirm('Are you sure you want to continue?')) {
                $this->info('Restore cancelled.');

                return 0;
            }
        }

        // Perform restore
        $this->info('🔄 Starting restore...');

        try {
            DB::transaction(function () use ($machines) {
                // Clear existing machines
                InsRdcMachine::truncate();

                // Restore machines
                foreach ($machines as $machineData) {
                    InsRdcMachine::create([
                        'id' => $machineData['id'],
                        'number' => $machineData['number'],
                        'name' => $machineData['name'],
                        'type' => $machineData['type'] ?? 'excel',
                        'cells' => $machineData['cells'],
                        'created_at' => $machineData['created_at'],
                        'updated_at' => $machineData['updated_at'],
                    ]);
                }
            });

            $this->info('✅ Restore completed successfully!');
            $this->line("📊 Restored {$backupData['total_machines']} machine(s)");

        } catch (\Exception $e) {
            $this->error('❌ Restore failed: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
