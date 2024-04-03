<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsRtcMetric;
use Illuminate\Console\Command;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;

class InsRtcRead extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-rtc-read';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read rubber thickness data from Modbus server installed in HMI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         // Nanti ganti dengan semua IP perangkat yang terdaftar di database
         $addresses = ['172.70.86.12'];

         while (true) {

            $dt_client = Carbon::now()->format('Y-m-d H:i:s');
            echo $dt_client;

            // Tarik data MODBUS ke semua perangkat
            foreach ($addresses as $address) {
             
                $unitID = 1;
                $fc3 = ReadRegistersBuilder::newReadHoldingRegisters('tcp://'.$address.':502', $unitID)
                    ->int16(10, 'thick_act_left')
                    ->int16(20, 'thick_act_right')
                    ->build();
    
                    try {
                        // Tarik data MODBUS
                        $responseContainer = (new NonBlockingClient(['readTimeoutSec' => 1]))->sendRequests($fc3);
                        echo 'Response from: ' . $address . PHP_EOL;
                        $data = $responseContainer->getData();
                        print_r($data);   
                    } catch (\Throwable $th) {
                        echo 'Failed to reach ' . $address . PHP_EOL;
                    }

                    InsRtcMetric::create([
                        'thick_act_left'    => $data['thick_act_left'],
                        'thick_act_right'   => $data['thick_act_right'],
                        'dt_client'         => $dt_client,
                    ]);
            }
            sleep(1);
         }
         
    }
}
