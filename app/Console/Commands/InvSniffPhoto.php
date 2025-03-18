<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvArea;
use App\Models\InvItem;
use App\Inv;

class InvSniffPhoto extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-sniff-photo';

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
        $inv_area = InvArea::find($inv_area_id);
        if (!$inv_area) {
            $this->error('No destination inventory area with that ID.');
            return 1; // Exit with error code
        }

        $user_name = $this->ask('Please enter groupware account');

        if (!$user_name) {
            $this->error('Invalid input. Please enter a username.');
            return 1; // Exit with error code
        }
        
        // Confirm the operation
        if (!$this->confirm('Do you wish to proceed to SNIFF photos for ' . $inv_area->name. ' inventory area using account: ' . $user_name .'?')) {
            $this->info('Operation cancelled');
            return 0; // Exit successfully but without doing the operation
        }

        $items = InvItem::where('inv_area_id', $inv_area->id)
            ->whereNotNull('code')
            ->where(function($query) {
                $query->whereNull('photo')
                    ->orWhere('photo', '');
            })
            ->get();

        $ci_session = Inv::getCiSession($user_name);
        
        if ($ci_session) {
            foreach ($items as $item) {
                $result = Inv::photoSniff($item->code, $ci_session);
                if ($result['success']) {
                    $item->photo = $result['photo'];
                    $item->save();
                    $this->info("{$item->code}: {$result['message']}");

                } else {
                    $this->warn("{$item->code}: {$result['message']}");
                }               
    
            }
        }

        $this->info('Operation completed. Bye!');
    }
}
