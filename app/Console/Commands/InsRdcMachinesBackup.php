<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InsRdcMachine;
use Illuminate\Support\Facades\Storage;

class InsRdcMachinesBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-rdc-machines-backup {--filename= : Custom filename for backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup of current RDC machine configurations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->option('filename') ?: 'rdc_machines_backup_' . now()->format('Y_m_d_H_i_s') . '.json';
        
        $this->info('Creating backup of RDC machines...');
        
        // Get all machines
        $machines = InsRdcMachine::all()->toArray();
        
        if (empty($machines)) {
            $this->warn('No machines found to backup.');
            return 0;
        }
        
        // Create backup data
        $backupData = [
            'created_at' => now()->toISOString(),
            'total_machines' => count($machines),
            'machines' => $machines
        ];
        
        // Save to storage
        $path = "backups/rdc_machines/{$filename}";
        Storage::disk('local')->put($path, json_encode($backupData, JSON_PRETTY_PRINT));
        
        $this->info("✅ Backup created successfully!");
        $this->line("📁 Location: storage/app/{$path}");
        $this->line("📊 Machines backed up: " . count($machines));
        
        // Show summary
        $this->newLine();
        $this->info('Backup contains:');
        foreach ($machines as $machine) {
            $configCount = count(json_decode($machine['cells'] ?? '[]', true));
            $type = $machine['type'] ?? 'unknown';
            $this->line("  - #{$machine['number']} {$machine['name']} ({$type}, {$configCount} fields)");
        }
        
        return 0;
    }
}