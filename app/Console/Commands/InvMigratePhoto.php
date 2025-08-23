<?php

namespace App\Console\Commands;

use App\Models\InvArea;
use App\Models\InvItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InvMigratePhoto extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-migrate-photo';

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
        if (! $this->confirm('Do you wish to proceed to update photos for '.$inv_area->name.' inventory area?')) {
            $this->info('Operation cancelled');

            return 0; // Exit successfully but without doing the operation
        }

        $items = InvItem::where('inv_area_id', $inv_area->id)->whereNotNull('code')->get();
        foreach ($items as $item) {
            $legacy_item = DB::connection('caldera_legacy')->table('igoods')->whereNotNull('image')->where('ttcode', $item->code)->first();
            if ($legacy_item && ! $item->photo) {
                $item->photo = 'legacy/'.$legacy_item->image;
                $item->save();
                $this->info('Photo item with id: '.$item->id.' updated');

            } else {
                $this->warn('Photo item with id: '.$item->id.' not found');

            }

        }
        $this->info('Operation completed. Bye!');

    }
}
