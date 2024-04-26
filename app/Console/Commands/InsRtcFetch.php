<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsRtcDevice;
use App\Models\InsRtcMetric;
use Illuminate\Console\Command;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;

class InsRtcFetch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-rtc-fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch rubber thickness data from Modbus server installed in HMI';



    function convertToDecimal($value) 
    {
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

    protected $prevThickAct = [];

    function saveMetric($device_id, $thick_act_left, $thick_act_right, $dt_client): void
    {
        $thickAct = $thick_act_left . $thick_act_right;
        if (!isset($this->prevThickAct[$device_id]) || $thickAct !== $this->prevThickAct[$device_id]) {
            InsRtcMetric::create([
                'ins_rtc_device_id' => $device_id,
                'thick_act_left'    => $this->convertToDecimal($thick_act_left),
                'thick_act_right'   => $this->convertToDecimal($thick_act_right),
                'dt_client'         => $dt_client,
            ]);
            $this->prevThickAct[$device_id] = $thickAct;
            echo 'Data is saved' . PHP_EOL;
        } else {
            echo 'Consecutive data is not saved' . PHP_EOL;
        }
    }

    public function handle()
    {
         // Nanti ganti dengan semua IP perangkat yang terdaftar di database
         $devices       = InsRtcDevice::all();
         $zeroCounters  = array_fill_keys($devices->pluck('id')->toArray(), 0);
         $maxZeros      = 5;

         while (true) {
            $dt_client = Carbon::now()->format('Y-m-d H:i:s');
            echo PHP_EOL . $dt_client . PHP_EOL;

            // Tarik data MODBUS ke semua perangkat
            foreach ($devices as $device) {
                $unitID = 1;
                $data = [];
                $fc3 = ReadRegistersBuilder::newReadHoldingRegisters('tcp://'.$device->ip_address.':503', $unitID)
                    ->int16(0, 'thick_act_left')
                    ->int16(1, 'thick_act_right')
                    ->int16(2, 'standard_middle')
                    ->int16(3, 'recipe_id')
                    // ->int16(4, 'is_correcting')

                    ->build();
    
                try {
                    // Tarik data MODBUS
                    $responseContainer = (new NonBlockingClient(['readTimeoutSec' => 1]))->sendRequests($fc3);
                    echo 'Response from: ' . $device->ip_address . ' (Line ' . $device->line . ')';
                    $data = $responseContainer->getData();
                    print_r($data);
                    echo ' --- Left: ' . $data['thick_act_left'] . ' Right: ' . $data['thick_act_right'] . PHP_EOL;   
                } catch (\Throwable $th) {
                    echo PHP_EOL . 'Exception: ' . $th->getMessage();
                }

                if (isset($data['thick_act_left']) && isset($data['thick_act_right'])) {
                    if ( $data['thick_act_left'] > 0 || $data['thick_act_right'] > 0 ) {
                        // save data
                        $this->saveMetric($device->id, $data['thick_act_left'], $data['thick_act_right'], $dt_client);
                        $zeroCounters[$device->id] = 0;
                        echo 'Zero counter: 0 (Reset)' . PHP_EOL;
                    } else {
                        if($zeroCounters[$device->id] < $maxZeros) {
                            $zeroCounters[$device->id]++;
                            echo 'Zero counter:' . $zeroCounters[$device->id] . PHP_EOL;    
                            // save the data (zero value)
                            $this->saveMetric($device->id, $data['thick_act_left'], $data['thick_act_right'], $dt_client);
                        } else {
                            echo 'Zero data is ignored.' . PHP_EOL;
                        }
                    }
                }
            }
            sleep(1);
         }         
    }
}
