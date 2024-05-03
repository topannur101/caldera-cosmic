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

    function saveMetric($metric): void
    {
        $thickAct = $metric['act_left'] . $metric['act_right'];
        if (!isset($this->prevThickAct[$metric['device_id']]) || $thickAct !== $this->prevThickAct[$metric['device_id']]) {
            $x = InsRtcRecipe::find($metric['recipe_id']) ? $metric['recipe_id'] : null;

            InsRtcMetric::create([
                'ins_rtc_device_id' => $metric['device_id'],
                'ins_rtc_recipe_id' => $x,
                'act_left' => $this->convertToDecimal($metric['act_left']),
                'act_right' => $this->convertToDecimal($metric['act_right']),
                'is_correcting' => (bool) $metric['is_correcting'],
                'dt_client' => $metric['dt_client'],
            ]);
            $this->prevThickAct[$metric['device_id']] = $thickAct;
            echo 'Data is saved' . PHP_EOL;
        } else {
            echo 'Consecutive data is not saved' . PHP_EOL;
        }
    }

    public function handle()
    {
        // Nanti ganti dengan semua IP perangkat yang terdaftar di database
        $devices = InsRtcDevice::all();
        $zeroCounters = array_fill_keys($devices->pluck('id')->toArray(), 0);
        $maxZeros = 5;

        while (true) {
            $dt_client = Carbon::now()->format('Y-m-d H:i:s');

            // Tarik data MODBUS ke semua perangkat
            foreach ($devices as $device) {
                $unitID = 1;

                $fc2 = ReadCoilsBuilder::newReadInputDiscretes('tcp://' . $device->ip_address . ':503', $unitID)
                    ->coil(0, 'is_correcting')
                    // ->coil(1, 'is_holding')
                    ->build();

                $fc3 = ReadRegistersBuilder::newReadHoldingRegisters('tcp://' . $device->ip_address . ':503', $unitID)
                    ->int16(0, 'act_left')
                    ->int16(1, 'act_right')
                    // something missing here
                    ->int16(3, 'recipe_id')
                    ->int16(4, 'push_thin_left')
                    ->int16(5, 'push_thick_left')
                    ->int16(6, 'push_thin_right')
                    ->int16(7, 'push_thick_right')
                    ->build();

                try {
                    // Tarik data MODBUS
                    $responseFc2 = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc2);
                    $responseFc3 = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc3);
                    echo 'Response from: ' . $device->ip_address . ' (Line ' . $device->line . ')';
                    $dataFc2 = $responseFc2->getData();
                    $dataFc3 = $responseFc3->getData();

                    $metric = [
                        'device_id'         => $device->id,
                        'act_left'          => $dataFc3['act_left'],
                        'act_right'         => $dataFc3['act_right'],
                        'recipe_id'         => $dataFc3['recipe_id'],
                        'is_correcting'     => $dataFc2['is_correcting'],
                        'push_thin_left'    => $dataFc3['push_thin_left'],
                        'push_thick_left'   => $dataFc3['push_thick_left'],
                        'push_thin_right'   => $dataFc3['push_thin_right'],
                        'push_thick_right'  => $dataFc3['push_thick_right'],
                        'dt_client'         => $dt_client,
                    ];

                    print_r($metric);

                    if ($metric['act_left'] > 0 || $metric['act_right'] > 0) {
                        // save data
                        $this->saveMetric($metric);
                        $zeroCounters[$device->id] = 0;
                        echo 'Zero counter: 0 (Reset)' . PHP_EOL;
                    } else {
                        if ($zeroCounters[$device->id] < $maxZeros) {
                            $zeroCounters[$device->id]++;
                            echo 'Zero counter:' . $zeroCounters[$device->id] . PHP_EOL;
                            // save the data (zero value)
                            $this->saveMetric($metric);
                        } else {
                            echo 'Zero data is ignored.' . PHP_EOL;
                        }
                    }

                } catch (\Throwable $th) {
                    echo PHP_EOL . 'Exception: ' . $th->getMessage();
                }
            }
            sleep(1);
        }
    }
}
