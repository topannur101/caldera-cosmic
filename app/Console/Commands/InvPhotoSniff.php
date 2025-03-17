<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvArea;
use App\Models\InvItem;
use Carbon\Carbon;

class InvPhotoSniff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inv-photo-sniff';

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

        $from_date = Carbon::now()->subYears(2)->format('Y-m-d');
        $to_date = Carbon::now()->format('Y-m-d');

        // Initial login call
        $login_url = "http://ttconsumable.t2group.co.kr/?empcd={$user_name}";
        $login_curl = curl_init();

        curl_setopt_array($login_curl, [
            CURLOPT_URL => $login_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // Bypass SSL verification
        ]);

        $login_response = curl_exec($login_curl);
        $login_err = curl_error($login_curl);

        curl_close($login_curl);

        if ($login_err) {
            echo "Login cURL Error #:" . $login_err;
        } else {
            echo "Login successful: " . $login_response;
        }

        foreach ($items as $item) {
            $item_code = $item->code;
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://ttconsumable.t2group.co.kr/purchase_request/fetch_data/1",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "action=fetch_data&from_date={$from_date}&to_date={$to_date}&status=Complete&item_code={$item_code}",
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Referer: https://ttconsumable.t2group.co.kr/purchase_request"
                ],
                CURLOPT_SSL_VERIFYPEER => false, // Bypass SSL verification
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                echo "cURL Error #:" . $err;
            } else {
                echo $response;
            }
        }

        $this->info('Operation completed. Bye!');
    }
}
