<?php

namespace App\Console\Commands;

use App\Models\InvArea;
use App\Models\InvStock;
use Illuminate\Console\Command;

class InvCalibrateUsed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-calibrate-used';

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
        if (! is_numeric($inv_area_id)) {
            $this->error('Invalid input. Please enter a numeric value.');

            return 1; // Exit with error code
        }

        // Check if the area exists
        $inv_area = InvArea::find($inv_area_id);
        if (! $inv_area) {
            $this->error('No destination inventory area with that ID.');

            return 1; // Exit with error code
        }

        // Confirm the operation
        if (! $this->confirm('Do you wish to proceed to calibrate qty on all items from '.$inv_area->name.' inventory area?')) {
            $this->info('Operation cancelled');

            return 0; // Exit successfully but without doing the operation
        }

        $stocks = InvStock::with(['inv_item'])
            ->whereHas('inv_item', function ($query) use ($inv_area) {
                $query->where('inv_area_id', $inv_area->id);
            })
            ->where('uom', 'LIKE', '%-B')
            ->get();

        foreach ($stocks as $stock) {
            $uom_e = substr($stock->uom, 0, -2);
            $stock_e = InvStock::where('inv_item_id', $stock->inv_item_id)->where('uom', $uom_e)->where('inv_curr_id', $stock->inv_curr_id)->first();

            if ($stock_e) {
                $qty = $stock_e->qty - $stock->qty;

                if ($qty >= 0) {
                    $stock_e->update([
                        'qty' => $qty,
                    ]);
                    $this->info('Qty calibrated for stock ID: '.$stock->id.' (Item ID: '.$stock_e->inv_item->id.')');

                } else {
                    $this->info('[!] Qty negative for stock ID: '.$stock->id.' (Item ID: '.$stock_e->inv_item->id.')');
                }

            } else {
                $this->info('No equivalent unit stock found for stock ID: '.$stock->id);
            }

        }

    }
}
