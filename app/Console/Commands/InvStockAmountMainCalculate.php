<?php

namespace App\Console\Commands;

use App\Models\InvStock;
use Illuminate\Console\Command;

class InvStockAmountMainCalculate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-stock-amount-main-calculate';

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
        $inv_stocks = InvStock::all();
        foreach ($inv_stocks as $inv_stock) {

            $amount_main = 0;
            if ($inv_stock->qty > 0 && $inv_stock->unit_price > 0 && $inv_stock->inv_curr->rate > 0) {
                $amount_main = abs(
                    ($inv_stock->inv_curr_id === 1)
                    ? $inv_stock->qty * $inv_stock->unit_price
                    : $inv_stock->qty * $inv_stock->unit_price / $inv_stock->inv_curr->rate
                );
            }

            $inv_stock->amount_main = $amount_main;
            $inv_stock->save();
            $this->info('InvStock '.$inv_stock->id.' updated');
        }
    }
}
