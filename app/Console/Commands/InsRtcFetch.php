<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsRtcDevice;
use App\Models\InsRtcMetric;
use App\Models\InsRtcRecipe;
use Illuminate\Console\Command;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadCoilsBuilder;
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

    function saveMetric($device_id, $act_left, $act_right, $recipe_id, $is_correcting, $dt_client): void
    {
        $thickAct = $act_left . $act_right;
        if (!isset($this->prevThickAct[$device_id]) || $thickAct !== $this->prevThickAct[$device_id]) {
            $x = InsRtcRecipe::find($recipe_id) ? $recipe_id : null;

            InsRtcMetric::create([
                'ins_rtc_device_id' => $device_id,
                'ins_rtc_recipe_id' => $x,
                'act_left'    => $this->convertToDecimal($act_left),
                'act_right'   => $this->convertToDecimal($act_right),
                'is_correcting'     => (bool) $is_correcting,
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
                $data_fc3 = [];

                $fc2 = ReadCoilsBuilder::newReadInputDiscretes('tcp://'.$device->ip_address.':503', $unitID)
                    ->coil(0, 'is_correcting')
                    ->coil(1, 'is_holding')
                    ->build();
                
                $fc3 = ReadRegistersBuilder::newReadHoldingRegisters('tcp://'.$device->ip_address.':503', $unitID)
                    ->int16(0, 'act_left')
                    ->int16(1, 'act_right')
                    ->int16(2, 'std_mid')
                    ->int16(3, 'recipe_id')
                    ->build();
    
                try {
                    // Tarik data MODBUS
                    $response_fc2 = (new NonBlockingClient(['readTimeoutSec' => 1]))->sendRequests($fc2);
                    $response_fc3 = (new NonBlockingClient(['readTimeoutSec' => 1]))->sendRequests($fc3);
                    echo 'Response from: ' . $device->ip_address . ' (Line ' . $device->line . ')';
                    $data_fc2 = $response_fc2->getData();
                    $data_fc3 = $response_fc3->getData();
                    // print_r($data_fc2);
                    // print_r($data_fc3);
                    echo ' --- is_correcting: ' . $data_fc2['is_correcting'] . ' act_left: ' . $data_fc3['act_left'] . ' act_right: ' . $data_fc3['act_right'] . ' std_mid: ' . $data_fc3['std_mid'] . ' recipe_id: ' . $data_fc3['recipe_id'] . PHP_EOL;   
                } catch (\Throwable $th) {
                    echo PHP_EOL . 'Exception: ' . $th->getMessage();
                }

                if (isset($data_fc3['act_left']) && isset($data_fc3['act_right'])) {
                    if ( $data_fc3['act_left'] > 0 || $data_fc3['act_right'] > 0 ) {
                        // save data
                        $this->saveMetric($device->id, $data_fc3['act_left'], $data_fc3['act_right'], $data_fc3['recipe_id'], $data_fc2['is_correcting'], $dt_client);
                        $zeroCounters[$device->id] = 0;
                        echo 'Zero counter: 0 (Reset)' . PHP_EOL;
                    } else {
                        if($zeroCounters[$device->id] < $maxZeros) {
                            $zeroCounters[$device->id]++;
                            echo 'Zero counter:' . $zeroCounters[$device->id] . PHP_EOL;    
                            // save the data (zero value)
                            $this->saveMetric($device->id, $data_fc3['act_left'], $data_fc3['act_right'], $data_fc3['recipe_id'], $data_fc2['is_correcting'],  $dt_client);
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
