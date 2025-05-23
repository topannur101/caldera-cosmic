<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvArea;
use App\Models\InvStock;

class InvUpdateWf extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-update-wf';

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
        // Ask for numeric input
        $inv_area_id = $this->ask('Please enter inventory area ID');

        // Validate the input is numeric
        if (!is_numeric($inv_area_id)) {
            $this->error('Invalid input. Please enter a numeric value.');
            return 1; // Exit with error code
        }
        
        // Check if the area exists
        $inv_area =InvArea::find($inv_area_id);
        if (!$inv_area) {
            $this->error('No destination inventory area with that ID.');
            return 1; // Exit with error code
        }
        
        // Confirm the operation
        if (!$this->confirm('Do you wish to proceed to update withdrawal frequency on all stocks from ' . $inv_area->name. ' inventory area?')) {
            $this->info('Operation cancelled');
            return 0; // Exit successfully but without doing the operation
        }
        
        $stocks = InvStock::with(['inv_item'])
        ->whereHas('inv_item', function($query) use ($inv_area) {
            $query->where('inv_area_id', $inv_area->id);
        })
        ->get();

        foreach ($stocks as $stock) {
            $wf = $stock->calculateWf();
            $stock->update([
                'wf' => $wf,
            ]);   
            $this->info('Stock ID: ' . $stock->id . ' wf updated to ' . round($wf, 2));
         
        }
        
        $this->info('Operation completed. Bye!');        
    }
}
