<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvArea;
use App\Models\InvItem;
use App\Models\InvStock;

class InvUpdateAmount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-update-amount';

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
        if (!$this->confirm('Do you wish to proceed to update amount on all stocks from ' . $inv_area->name. ' inventory area?')) {
            $this->info('Operation cancelled');
            return 0; // Exit successfully but without doing the operation
        }
        
        $stocks = InvStock::with(['inv_item'])
        ->whereHas('inv_item', function($query) use ($inv_area) {
            $query->where('inv_area_id', $inv_area->id);
        })
        ->get();

        foreach ($stocks as $stock) {
            $amount_main = 0;
            if ($stock->qty > 0 && $stock->unit_price > 0 && $stock->inv_curr->rate > 0) {
                $amount_main = max(
                    ($stock->inv_curr_id === 1) 
                    ? $stock->qty * $stock->unit_price
                    : $stock->qty * $stock->unit_price / $stock->inv_curr->rate
                    , 0);
                $stock->update([
                    'amount_main' => $amount_main
                ]);   
                $this->info('Stock ID: ' . $stock->id . ' amount updated to ' . $amount_main);

            } else {
                $this->warn('Stock ID: ' . $stock->id . ' does not meet criteria. Amount set to 0');

            }       
 
         
        }
        
        $this->info('Operation completed. Bye!');        
    }
}
