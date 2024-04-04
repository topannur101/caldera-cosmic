<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsRtcDevice;
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

    function convertToDecimal($value) {
        $value = (int) $value; // Cast to integer to remove any leading zeros
        $length = strlen((string) $value);
    
        if ($length == 3) {
            $decimal = substr((string) $value, 0, -2) . '.' . substr((string) $value, -2);
        } elseif ($length == 2) {
            $decimal = '0.' . (string) $value;
        } elseif ($length == 1) {
            $decimal = '0.0' . (string) $value;
        } else {
            $decimal = '0.00';
        }
    
        return $decimal;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
         // Nanti ganti dengan semua IP perangkat yang terdaftar di database
         $devices = InsRtcDevice::all();

         while (true) {

            $dt_client = Carbon::now()->format('Y-m-d H:i:s');
            echo $dt_client . PHP_EOL;

            // Tarik data MODBUS ke semua perangkat
            foreach ($devices as $device) {
             
                $unitID = 1;
                $data = [];

                $fc3 = ReadRegistersBuilder::newReadHoldingRegisters('tcp://'.$device->ip_address.':502', $unitID)
                    ->int16(10, 'thick_act_left')
                    ->int16(20, 'thick_act_right')
                    ->build();
    
                    try {
                        // Tarik data MODBUS
                        $responseContainer = (new NonBlockingClient(['readTimeoutSec' => 1]))->sendRequests($fc3);
                        echo 'Response from: ' . $device->ip_address . ' (Line ' . $device->line . ')' . PHP_EOL;
                        $data = $responseContainer->getData();
                        print_r($data);   
                    } catch (\Throwable $th) {
                        echo 'Failed to reach ' . $device->ip_address . ' (Line ' . $device->line . ')' . PHP_EOL;
                    }

                    if ($data) {
                        InsRtcMetric::create([
                            'ins_rtc_device_id' => 1,
                            'thick_act_left'    => $this->convertToDecimal($data['thick_act_left']),
                            'thick_act_right'   => $this->convertToDecimal($data['thick_act_right']),
                            'dt_client'         => $dt_client,
                        ]);
                    }
            }
            sleep(1);
         }
         
    }
}
