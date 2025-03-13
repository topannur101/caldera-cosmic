<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InvCurr;
use App\Models\InvStock;
use App\Models\InvItem;

class InvMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-migrate';

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
        $legacy_area_id = $this->ask('Please enter legacy inventory area ID');
        $inv_area_id = $this->ask('Please enter destination inventory area ID');

        // Validate the input is numeric
        if (!is_numeric($legacy_area_id)) {
            $this->error('Invalid input. Please enter a numeric value.');
            return 1; // Exit with error code
        }
        if (!is_numeric($inv_area_id)) {
            $this->error('Invalid input. Please enter a numeric value.');
            return 1; // Exit with error code
        }
        
        // Check if the area exists
        $legacy_area = DB::connection('caldera_legacy')->table('areas')->find($legacy_area_id);
        if (!$legacy_area) {
            $this->error('No legacy inventory area with that ID.');
            return 1; // Exit with error code
        }

        $inv_area = DB::connection('caldera_legacy')->table('areas')->find($inv_area_id);
        if (!$inv_area) {
            $this->error('No destination inventory area with that ID.');
            return 1; // Exit with error code
        }
        
        // Confirm the operation
        if (!$this->confirm('Do you wish to proceed to migrate items from ' . $legacy_area->name . ' (legacy) to ' . $inv_area->name. ' (destination)?')) {
            $this->info('Operation cancelled');
            return 0; // Exit successfully but without doing the operation
        }

        $igoods = DB::connection('caldera_legacy')->table('igoods')->where('area_id', $legacy_area->id)->limit(100)->get();

        foreach ($igoods as $igood) {

            $ttcurr = $igood->ttcurr_id 
            ? DB::connection('caldera_legacy')->table('ttcurrs')->find($igood->ttcurr_id) 
            : null;
            
            $inv_curr = $ttcurr
            ? InvCurr::where('name', $ttcurr->name)->first()
            : null;

            $uom = $igood->iuom_id 
            ? DB::connection('caldera_legacy')->table('iuoms')->find($igood->iuom_id)?->name
            : null;

            $item = InvItem::updateOrCreate([
                'inv_area_id' => $inv_area->id,
                'code' => $igood->ttcode,
            ],[
                'name'  => $igood->name,
                'desc'  => $igood->spec,
                'photo' => $igood->image ? 'legacy/' . $igood->image : null,
                'is_active' => true,
            ]);

            // location please

            if ($item->id && $inv_curr && $uom) {
                InvStock::updateOrCreate([
                    'inv_item_id' => $item->id,
                    'inv_curr_id' => $inv_curr->id,
                    'uom'         => $uom,
                ],[
                    'qty'         => $igood->qty,
                    'unit_price'  => $igood->ttprice,
                    'is_active'   => true
                ]);
                $this->warn('Stock for item ID: ' . $item->id . ' created.');
            } else {
                $this->warn('Stock for item ID: ' . $item->id . ' was not created.');
            } 


            
        }
        
        return 0; // Exit successfully
        
    }
}
