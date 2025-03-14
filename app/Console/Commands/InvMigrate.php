<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InvCurr;
use App\Models\InvStock;
use App\Models\InvItem;
use App\Models\InvArea;
use App\Models\InvLoc;
use App\Models\InvCirc;
use App\Models\User;


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

        $inv_area =InvArea::find($inv_area_id);
        if (!$inv_area) {
            $this->error('No destination inventory area with that ID.');
            return 1; // Exit with error code
        }
        
        // Confirm the operation
        if (!$this->confirm('Do you wish to proceed to migrate items from ' . $legacy_area->name . ' (legacy) to ' . $inv_area->name. ' (destination)?')) {
            $this->info('Operation cancelled');
            return 0; // Exit successfully but without doing the operation
        }

        $igoods = DB::connection('caldera_legacy')->table('igoods')
        ->where('area_id', $legacy_area->id)
        ->whereNotNull('ttcurr_id')
        ->whereNotNull('iuom_id')
        ->get();

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

            $item = new InvItem([
                'inv_area_id'   => $inv_area->id,
                'name'          => $igood->name,
                'desc'          => $igood->spec,
                'photo'         => $igood->image ? 'legacy/' . $igood->image : null,
                'is_active'     => true,
                'legacy_id'     => $igood->id
            ]);

            // check whether the item code duplicate
            if ($igood->ttcode) {
                $item_exists = InvItem::where('inv_area_id', $inv_area->id)->where('code', $igood->ttcode)->first();

                if ($item_exists) {
                    $item->desc = $igood->spec . ' [duplicate_code:' . $igood->ttcode . ' legacy_area:'. $legacy_area->name  . ']';

                } else {
                    $item->code = $igood->ttcode;

                }
            }

            $loc = $igood->iloc_id
                ? DB::connection('caldera_legacy')->table('ilocs')->find($igood->iloc_id)?->name
                : null;

            $parent = '';
            $bin = '';

            if ($loc) {
                $parts  = explode('-', $loc, 2);
                $parent = $parts[0];
                $bin    = isset($parts[1]) ? $parts[1] : '';
            }

            if ($parent && $bin) {
                $inv_loc = InvLoc::firstOrCreate([
                    'parent' => $parent,
                    'bin'   => $bin,
                ]);
                $item->inv_loc_id = $inv_loc->id;
            }        

            $item->save();
            if ($item->id && $inv_curr && $uom) {
                $stock = InvStock::updateOrCreate([
                    'inv_item_id' => $item->id,
                    'inv_curr_id' => $inv_curr->id,
                    'uom'         => $uom,
                ],[
                    'qty'         => max($igood->qty, 0),
                    'unit_price'  => $igood->ttprice,
                    'is_active'   => true
                ]);
                $this->info('Stock for legacy item ID: ' . $igood->id . ' created. New ID: ' . $item->id);

                $ilogs = DB::connection('caldera_legacy')->table('ilogs')
                    ->where('igood_id', $item->legacy_id)
                    ->where('status', 1)
                    ->whereNotNull('approver_id')
                    ->whereNotNull('user_id')
                    ->get();

                foreach ($ilogs as $ilog) {

                    $user_legacy = DB::connection('caldera_legacy')->table('users')->find($ilog->user_id);
                    $user = User::firstOrCreate([
                        'emp_id' => 'TT' . $user_legacy->tt
                    ], [
                        'name' => $user_legacy->name . ' ' . $user_legacy->lastname,
                        'photo' => $user_legacy->image,
                        'password' => '$2y$12$0KKCawG6HLkTJP3BPUJ5xupcpSGiYdL2CV13Eku8eID48YFN2L.aC'
                    ]);

                    $approver_user = DB::connection('caldera_legacy')->table('users')->find($ilog->approver_id);
                    $eval_user = User::firstOrCreate([
                        'emp_id' => 'TT' . $approver_user->tt
                    ], [
                        'name' => $approver_user->name . ' ' . $approver_user->lastname,
                        'photo' => $approver_user->image,
                        'password' => '$2y$12$0KKCawG6HLkTJP3BPUJ5xupcpSGiYdL2CV13Eku8eID48YFN2L.aC'
                    ]);

                    InvCirc::create([
                        'user_id'       => $user->id,
                        'type'          => $ilog->iact_id == 3 ? 'withdrawal' : 'deposit',
                        'eval_status'   => 'approved',
                        'eval_user_id'  => $eval_user->id,
                        'inv_stock_id'  => $stock->id,
                        'qty_relative'  => abs($ilog->static_qty),
                        'amount'        => $ilog->static_ttprice_usd * $ilog->static_qty,
                        'unit_price'    => $ilog->static_ttprice_usd,
                        'remarks'       => $ilog->ket ?? 'No remarks',
                        'is_delegated'  => false,
                        'created_at'    => $ilog->created_at,
                        'updated_at'    => $ilog->updated_at
                    ]);                    
                }

            } else {
                $item->delete();
                $this->warn('Stock for legacy item ID: ' . $igood->id . ' was not created. Item destroyed.');
            } 
            
        }
        
        return 0; // Exit successfully        
    }
}
