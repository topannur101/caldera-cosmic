<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsRtcClump;
use App\Models\InsRtcDevice;
use App\Models\InsRtcMetric;
use App\Models\InsRtcRecipe;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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

    function action($pushThin, $pushThick)
    {
        $action = null;
        if ($pushThin > 0 && $pushThick == 0) {
            $action = 'thin';
        } elseif ($pushThin == 0 && $pushThick > 0) {
            $action = 'thick';
        }
        return $action;
    }

    protected $sensor_prev      = [];
    protected $zero_metrics     = [];
    protected $clump_id_prev    = [];
    protected $clump_timeout    = 120; // original 60
    // Latest dt_client
    protected $dt_prev          = [];
    // System time (HMI) previous triggered correction
    protected $st_cl_prev       = [];
    protected $st_cr_prev       = [];

    function saveMetric($metric): void
    {
        $sensor = $metric['sensor_left'] . $metric['st_correct_left'] . $metric['sensor_right'] . $metric['st_correct_right'];
        // Save to database if new value deteced
        if ($sensor !== $this->sensor_prev[$metric['device_id']]) {

            $collection = collect($this->zero_metrics[$metric['device_id']]);

            $maxDtClient = $collection->max(function ($item) {
                return $item['dt_client'];
            });
            $minDtClient = $collection->min(function ($item) {
                return $item['dt_client'];
            });

            // Convert to Carbon instances if not already
            $maxDtClient = Carbon::parse($maxDtClient);
            $minDtClient = Carbon::parse($minDtClient);

            // Calculate the difference in seconds
            $differenceInSeconds = $minDtClient->diffInSeconds($maxDtClient);

            if ($differenceInSeconds > $this->clump_timeout || !$this->clump_id_prev[$metric['device_id']]) {
                $clump = InsRtcClump::create([
                    'ins_rtc_recipe_id' => $metric['recipe_id'],
                    'ins_rtc_device_id' => $metric['device_id']
                ]);
                $this->clump_id_prev[$metric['device_id']] = $clump->id;
            }

            $action_left = null;
            $action_right = null;

            $st_cl = $metric['st_correct_left'];
            $st_cr = $metric['st_correct_right'];

            // Save 'thin' or 'thick' action if there's new correction system time
            if ($st_cl !== $this->st_cl_prev[$metric['device_id']]) {
                $action_left = $this->action($metric['push_thin_left'], $metric['push_thick_left']);
                $this->st_cl_prev[$metric['device_id']] = $st_cl;
            }

            if ($st_cr !== $this->st_cr_prev[$metric['device_id']]) {
                $action_right = $this->action($metric['push_thin_right'], $metric['push_thick_right']);
                $this->st_cr_prev[$metric['device_id']] = $st_cr;
            }

            InsRtcMetric::create([
                'ins_rtc_clump_id'      => $this->clump_id_prev[$metric['device_id']],
                'sensor_left'           => $this->convertToDecimal($metric['sensor_left']),
                'sensor_right'          => $this->convertToDecimal($metric['sensor_right']),
                'action_left'           => $action_left,
                'action_right'          => $action_right,
                'is_correcting'         => (bool) $metric['is_correcting'],
                'clump_id'              => $this->clump_id_prev[$metric['device_id']],
                'dt_client'             => $metric['dt_client'],
            ]);
            $this->sensor_prev[$metric['device_id']]    = $sensor;
            $this->zero_metrics[$metric['device_id']]   = [];
            echo 'Data is saved' . PHP_EOL;
        } else {
            if (!$metric['sensor_left'] && !$metric['sensor_right']) {
                
                $this->zero_metrics[$metric['device_id']][] = $metric;
                echo 'Consecutive data (zero) is not saved' . PHP_EOL;

            } else {
                echo 'Consecutive data is not saved' . PHP_EOL;
            }
            
        }
    }

    public function handle()
    {
        // Nanti ganti dengan semua IP perangkat yang terdaftar di database
        $devices = InsRtcDevice::all();

        // Initialize variables
        foreach($devices as $device) {
            $this->sensor_prev[$device->id]     = null;
            $this->zero_metrics[$device->id]    = null;
            $this->clump_id_prev[$device->id]   = null;
            $this->st_cl_prev[$device->id]      = null;
            $this->st_cr_prev[$device->id]      = null;
        }

        while (true) {
            $dt_now = Carbon::now()->format('Y-m-d H:i:s');

            // Tarik data MODBUS ke semua perangkat
            foreach ($devices as $device) {
                $unit_id = 1;

                $fc2 = ReadCoilsBuilder::newReadInputDiscretes('tcp://' . $device->ip_address . ':503', $unit_id)
                    ->coil(0, 'is_correcting')
                    // ->coil(1, 'is_holding')
                    ->build();

                $fc3 = ReadRegistersBuilder::newReadHoldingRegisters('tcp://' . $device->ip_address . ':503', $unit_id)
                    ->int16(0, 'sensor_left')
                    ->int16(1, 'sensor_right')
                    // something missing here
                    ->int16(3, 'recipe_id')
                    ->int16(4, 'push_thin_left')
                    ->int16(5, 'push_thick_left')
                    ->int16(6, 'push_thin_right')
                    ->int16(7, 'push_thick_right')
                    ->int16(8, 'st_correct_left')
                    ->int16(9, 'st_correct_right')
                    ->build();

                try {
                    // Tarik data MODBUS
                    $fc2_resposne = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc2);
                    $fc3_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc3);
                    echo 'Response from: ' . $device->ip_address . ' (Line ' . $device->line . ')';
                    $fc2_data = $fc2_resposne->getData();
                    $fc3_data = $fc3_response->getData();
                    // print_r($fc3_data);

                    $metric = [
                        'device_id'         => $device->id,
                        'sensor_left'       => $fc3_data['sensor_left'],
                        'sensor_right'      => $fc3_data['sensor_right'],
                        'recipe_id'         => $fc3_data['recipe_id'],
                        'is_correcting'     => $fc2_data['is_correcting'],
                        'push_thin_left'    => $fc3_data['push_thin_left'],
                        'push_thick_left'   => $fc3_data['push_thick_left'],
                        'push_thin_right'   => $fc3_data['push_thin_right'],
                        'push_thick_right'  => $fc3_data['push_thick_right'],
                        'st_correct_left'   => $fc3_data['st_correct_left'],
                        'st_correct_right'  => $fc3_data['st_correct_right'],
                        'dt_client'         => $dt_now,
                    ];

                    print_r($metric);
                    $this->saveMetric($metric);

                } catch (\Throwable $th) {
                    echo PHP_EOL . 'Exception: ' . $th->getMessage();
                }
            }
            sleep(1);
        }
    }
}
