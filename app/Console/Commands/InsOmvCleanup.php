<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InsOmvCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-omv-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete InsOmvCaptures entries and files older than one month';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $oneMonthAgo = Carbon::now()->subMonth();
        
        // Get records older than one month
        $oldRecords = DB::table('ins_omv_captures')
            ->where('created_at', '<', $oneMonthAgo)
            ->get();

        $deletedCount = 0;
        $errorCount = 0;

        foreach ($oldRecords as $record) {
            try {
                // Delete the associated file
                $filePath = 'public/omv-captures/' . $record->file_name;
                if (Storage::exists($filePath)) {
                    Storage::delete($filePath);
                }

                // Delete the database record
                DB::table('ins_omv_captures')
                    ->where('id', $record->id)
                    ->delete();

                $deletedCount++;

                // Logging
                /*
                Log::info('Deleted OmvMetrics record and file', [
                    'id' => $record->id,
                    'filename' => $record->filename,
                    'created_at' => $record->created_at
                ]);
                */
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $errorCount++;
                // Logging (commented out for now)
                /*
                Log::error('Failed to delete OmvMetrics record', [
                    'id' => $record->id,
                    'filename' => $record->filename,
                    'error' => $e->getMessage()
                ]);
                */
            }
        }

        $this->info("Cleanup completed. Deleted {$deletedCount} records. Errors: {$errorCount}");
    }
}
