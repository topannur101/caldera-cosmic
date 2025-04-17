<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvArea;
use App\Models\InvCirc;
use App\Models\InvStock;
use App\Models\InvItem;
use App\Models\InvItemTag;
use App\Models\ComItem;
use Carbon\Carbon;

class InvUpdateDeposit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-update-deposit';

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
        if (!$this->confirm('Do you wish to proceed to update last_withdrawal on all items from ' . $inv_area->name. ' inventory area?')) {
            $this->info('Operation cancelled');
            return 0; // Exit successfully but without doing the operation
        }

        $items = InvItem::where('inv_area_id', $inv_area->id)->update([
            'last_withdrawal' => null
        ]);
        $this->info('Last withdrawal of all items has been reset'); 
        
        $stocks = InvStock::with(['inv_item'])
        ->whereHas('inv_item', function($query) use ($inv_area) {
            $query->where('inv_area_id', $inv_area->id);
        })
        ->get();

        foreach ($stocks as $stock) {

            $cdp = '';
            $cdp = $stock->inv_item->last_deposit;

            $olc = $stock->inv_circs()
                ->where('type', 'deposit')
                ->where('eval_status', 'approved')
                ->latest('updated_at')->first();
            
            $odp = $olc ? $olc->updated_at : null;

            if (!$cdp && $odp) {
                $stock->inv_item->update([
                    'last_deposit' => $odp
                ]);
                $this->info('Last deposit of item ID: ' . $stock->inv_item->id . ' updated'); 
                continue;
            }

            if ($cdp && $odp) {
                if ($odp->gt($cdp)) {
                    $stock->inv_item->update([
                        'last_deposit' => $odp
                    ]);
                    $this->info('Last deposit of item ID: ' . $stock->inv_item->id . ' updated'); 
                    continue;
                }  
            }            
         
        }
        
        $this->info('Operation completed. Bye!');        
    }
}
