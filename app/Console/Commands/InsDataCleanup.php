<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InsDataCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:data-cleanup {--dry-run : Preview what would be deleted without actually deleting} {--test : Test mode with limited records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old data across all modules with configurable retention periods';

    /**
     * Data retention configuration in months (0 = never cleanup)
     */
    private array $retentionConfig = [
        'omv' => 6,    // OMV captures and metrics
        'ctc' => 6,    // CTC metrics
        'rdc' => 6,    // RDC test results
        'stc' => 6,    // STC device logs and summaries
        'clm' => 12,   // CLM environmental records
        'ldc' => 24,   // LDC hide records
        'rubber_batches' => 12,   // Rubber batch records
        'inv' => 60,   // Inventory circulation records
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isTestMode = $this->option('test');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No data will be deleted');
            $this->line('');
        }
        
        if ($isTestMode) {
            $this->info('🧪 TEST MODE - Limited to checking first few records per table');
            $this->line('');
        }

        $totalDeleted = 0;
        $totalErrors = 0;

        foreach ($this->retentionConfig as $module => $retentionMonths) {
            if ($retentionMonths === 0) {
                $this->line("⏭️  Skipping {$module} module (retention set to 0 - never cleanup)");
                continue;
            }

            $this->info("🧹 Processing {$module} module (retention: {$retentionMonths} month(s))");
            
            $result = $this->cleanupModule($module, $retentionMonths, $isDryRun, $isTestMode);
            $totalDeleted += $result['deleted'];
            $totalErrors += $result['errors'];
            
            $this->line('');
        }

        $action = $isDryRun ? 'Would delete' : 'Deleted';
        $this->info("✅ Cleanup completed. {$action} {$totalDeleted} records total. Errors: {$totalErrors}");
    }

    /**
     * Clean up data for a specific module
     */
    private function cleanupModule(string $module, int $retentionMonths, bool $isDryRun, bool $isTestMode = false): array
    {
        $cutoffDate = Carbon::now()->subMonths($retentionMonths);
        $deletedCount = 0;
        $errorCount = 0;

        switch ($module) {
            case 'omv':
                $result = $this->cleanupOmv($cutoffDate, $isDryRun, $isTestMode);
                break;
            case 'ctc':
                $result = $this->cleanupCtc($cutoffDate, $isDryRun, $isTestMode);
                break;
            case 'rdc':
                $result = $this->cleanupRdc($cutoffDate, $isDryRun, $isTestMode);
                break;
            case 'stc':
                $result = $this->cleanupStc($cutoffDate, $isDryRun, $isTestMode);
                break;
            case 'ldc':
                $result = $this->cleanupLdc($cutoffDate, $isDryRun, $isTestMode);
                break;
            case 'clm':
                $result = $this->cleanupClm($cutoffDate, $isDryRun, $isTestMode);
                break;
            case 'inv':
                $result = $this->cleanupInv($cutoffDate, $isDryRun, $isTestMode);
                break;
            case 'rubber_batches':
                $result = $this->cleanupRubberBatches($cutoffDate, $isDryRun, $isTestMode);
                break;
            default:
                $result = ['deleted' => 0, 'errors' => 0];
        }

        $action = $isDryRun ? 'Would delete' : 'Deleted';
        $this->line("   {$action} {$result['deleted']} records. Errors: {$result['errors']}");
        
        return $result;
    }

    /**
     * Clean up OMV data (captures and metrics)
     */
    private function cleanupOmv(Carbon $cutoffDate, bool $isDryRun, bool $isTestMode = false): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        // Clean up captures (with files)
        $captureQuery = DB::table('ins_omv_captures')
            ->where('created_at', '<', $cutoffDate);
        
        if ($isTestMode) {
            $captureQuery->limit(5);
        }
        
        $captureRecords = $captureQuery->get();

        foreach ($captureRecords as $record) {
            try {
                if (!$isDryRun) {
                    $filePath = 'public/omv-captures/'.$record->file_name;
                    if (Storage::exists($filePath)) {
                        Storage::delete($filePath);
                    }
                    DB::table('ins_omv_captures')->where('id', $record->id)->delete();
                }
                $deletedCount++;
            } catch (\Exception $e) {
                $this->error("   Error deleting OMV capture {$record->id}: " . $e->getMessage());
                $errorCount++;
            }
        }

        // Clean up metrics
        $metricsQuery = DB::table('ins_omv_metrics')
            ->where('created_at', '<', $cutoffDate);
        
        if ($isTestMode) {
            $metricsQuery->limit(5);
        }
        
        $metricsCount = $isTestMode ? $metricsQuery->count() : DB::table('ins_omv_metrics')
            ->where('created_at', '<', $cutoffDate)
            ->count();

        if (!$isDryRun && $metricsCount > 0) {
            try {
                DB::table('ins_omv_metrics')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
                $deletedCount += $metricsCount;
            } catch (\Exception $e) {
                $this->error("   Error deleting OMV metrics: " . $e->getMessage());
                $errorCount++;
            }
        } else {
            $deletedCount += $metricsCount;
        }

        return ['deleted' => $deletedCount, 'errors' => $errorCount];
    }

    /**
     * Clean up CTC data (metrics)
     */
    private function cleanupCtc(Carbon $cutoffDate, bool $isDryRun, bool $isTestMode = false): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        try {
            $query = DB::table('ins_ctc_metrics')
                ->where('created_at', '<', $cutoffDate);
            
            if ($isTestMode) {
                $query->limit(5);
            }
            
            $count = $query->count();

            if (!$isDryRun && $count > 0) {
                DB::table('ins_ctc_metrics')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
            }
            $deletedCount = $count;
        } catch (\Exception $e) {
            $this->error("   Error deleting CTC metrics: " . $e->getMessage());
            $errorCount++;
        }

        return ['deleted' => $deletedCount, 'errors' => $errorCount];
    }

    /**
     * Clean up RDC data (test results)
     */
    private function cleanupRdc(Carbon $cutoffDate, bool $isDryRun, bool $isTestMode = false): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        try {
            $query = DB::table('ins_rdc_tests')
                ->where('created_at', '<', $cutoffDate);
            
            if ($isTestMode) {
                $query->limit(5);
            }
            
            $count = $query->count();

            if (!$isDryRun && $count > 0) {
                DB::table('ins_rdc_tests')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
            }
            $deletedCount = $count;
        } catch (\Exception $e) {
            $this->error("   Error deleting RDC tests: " . $e->getMessage());
            $errorCount++;
        }

        return ['deleted' => $deletedCount, 'errors' => $errorCount];
    }

    /**
     * Clean up STC data (device logs first, then summaries)
     */
    private function cleanupStc(Carbon $cutoffDate, bool $isDryRun, bool $isTestMode = false): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        try {
            // First get summary IDs that are old
            $summaryQuery = DB::table('ins_stc_d_sums')
                ->where('created_at', '<', $cutoffDate);
            
            if ($isTestMode) {
                $summaryQuery->limit(5);
            }
            
            $oldSummaryIds = $summaryQuery->pluck('id');

            if ($oldSummaryIds->isNotEmpty()) {
                // Delete logs first (child records)
                $logsCount = DB::table('ins_stc_d_logs')
                    ->whereIn('ins_stc_d_sum_id', $oldSummaryIds)
                    ->count();

                if (!$isDryRun && $logsCount > 0) {
                    DB::table('ins_stc_d_logs')
                        ->whereIn('ins_stc_d_sum_id', $oldSummaryIds)
                        ->delete();
                }
                $deletedCount += $logsCount;

                // Then delete summaries (parent records)
                if (!$isDryRun) {
                    DB::table('ins_stc_d_sums')
                        ->whereIn('id', $oldSummaryIds)
                        ->delete();
                }
                $deletedCount += $oldSummaryIds->count();
            }

            // Clean up machine logs
            $machineLogsQuery = DB::table('ins_stc_m_logs')
                ->where('created_at', '<', $cutoffDate);
            
            if ($isTestMode) {
                $machineLogsQuery->limit(5);
            }
            
            $machineLogsCount = $machineLogsQuery->count();

            if (!$isDryRun && $machineLogsCount > 0) {
                DB::table('ins_stc_m_logs')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
            }
            $deletedCount += $machineLogsCount;

        } catch (\Exception $e) {
            $this->error("   Error deleting STC data: " . $e->getMessage());
            $errorCount++;
        }

        return ['deleted' => $deletedCount, 'errors' => $errorCount];
    }

    /**
     * Clean up LDC data (hide records)
     */
    private function cleanupLdc(Carbon $cutoffDate, bool $isDryRun, bool $isTestMode = false): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        try {
            $query = DB::table('ins_ldc_hides')
                ->where('created_at', '<', $cutoffDate);
            
            if ($isTestMode) {
                $query->limit(5);
            }
            
            $count = $query->count();

            if (!$isDryRun && $count > 0) {
                DB::table('ins_ldc_hides')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
            }
            $deletedCount = $count;
        } catch (\Exception $e) {
            $this->error("   Error deleting LDC hides: " . $e->getMessage());
            $errorCount++;
        }

        return ['deleted' => $deletedCount, 'errors' => $errorCount];
    }

    /**
     * Clean up CLM data (environmental records)
     */
    private function cleanupClm(Carbon $cutoffDate, bool $isDryRun, bool $isTestMode = false): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        try {
            $query = DB::table('ins_clm_records')
                ->where('created_at', '<', $cutoffDate);
            
            if ($isTestMode) {
                $query->limit(2);
            }
            
            $count = $query->count();

            if (!$isDryRun && $count > 0) {
                $deleteQuery = DB::table('ins_clm_records')
                    ->where('created_at', '<', $cutoffDate);
                    
                if ($isTestMode) {
                    $deleteQuery->limit(2);
                }
                
                $deleteQuery->delete();
            }
            $deletedCount = $count;
        } catch (\Exception $e) {
            $this->error("   Error deleting CLM records: " . $e->getMessage());
            $errorCount++;
        }

        return ['deleted' => $deletedCount, 'errors' => $errorCount];
    }

    /**
     * Clean up inventory data (circulation records only)
     */
    private function cleanupInv(Carbon $cutoffDate, bool $isDryRun, bool $isTestMode = false): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        try {
            $query = DB::table('inv_circs')
                ->where('created_at', '<', $cutoffDate);
            
            if ($isTestMode) {
                $query->limit(5);
            }
            
            $count = $query->count();

            if (!$isDryRun && $count > 0) {
                DB::table('inv_circs')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
            }
            $deletedCount = $count;
        } catch (\Exception $e) {
            $this->error("   Error deleting inventory circulation records: " . $e->getMessage());
            $errorCount++;
        }

        return ['deleted' => $deletedCount, 'errors' => $errorCount];
    }

    /**
     * Clean up rubber batch data
     * NOTE: This should be run AFTER child modules (ctc, rdc, omv) to respect foreign key constraints
     */
    private function cleanupRubberBatches(Carbon $cutoffDate, bool $isDryRun, bool $isTestMode = false): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        try {
            $query = DB::table('ins_rubber_batches')
                ->where('created_at', '<', $cutoffDate);
            
            if ($isTestMode) {
                $query->limit(5);
            }
            
            $count = $query->count();

            if (!$isDryRun && $count > 0) {
                $deleteQuery = DB::table('ins_rubber_batches')
                    ->where('created_at', '<', $cutoffDate);
                    
                if ($isTestMode) {
                    $deleteQuery->limit(5);
                }
                
                $deleteQuery->delete();
            }
            $deletedCount = $count;
        } catch (\Exception $e) {
            $this->error("   Error deleting rubber batch records: " . $e->getMessage());
            $errorCount++;
        }

        return ['deleted' => $deletedCount, 'errors' => $errorCount];
    }
}