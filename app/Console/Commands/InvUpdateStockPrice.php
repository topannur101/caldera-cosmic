<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvArea;
use App\Models\InvStock;

class InvUpdateStockPrice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-update-stock-price';

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
        if (!$this->confirm('Do you wish to proceed to update unit price on all USED stocks for 10% from ' . $inv_area->name. ' inventory area?')) {
            $this->info('Operation cancelled');
            return 0; // Exit successfully but without doing the operation
        }
        
        $stocks = InvStock::with(['inv_item'])
        ->whereHas('inv_item', function($query) use ($inv_area) {
            $query->where('inv_area_id', $inv_area->id);
        })
        ->get();

        foreach ($stocks as $stock) {
            
            $uom = $stock->uom;

            if (str_ends_with($uom, '-B')) {
                $stock_main = InvStock::where('inv_item_id', $stock->inv_item_id)
                ->where('uom', str_replace('-B', '', $uom))
                ->where('inv_curr_id', $stock->inv_curr_id)
                ->where('is_active', 1)
                ->first();

                if ($stock_main) {
                    $stock->update([
                        'unit_price' => $stock_main->unit_price * 0.1,
                    ]);
                    $this->info('Updated unit price for stock ID: ' . $stock->id . ' to 10% of ' . $stock_main->unit_price);
                } else {
                    $this->error('No main stock found for stock ID: ' . $stock->id);
                }

            }
 
         
        }
        
        $this->info('Operation completed. Bye!');        
    }
}
