<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsStcMachine;
use Illuminate\Console\Command;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadCoilsBuilder;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;

class InsStcPoll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-stc-poll';

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
        $devices = InsStcMachine::all();
        $port = 504;
        $unit_id = 1;
        
        while (true) {
            $dt_now = Carbon::now()->format('Y-m-d H:i:s');
            foreach ($devices as $device) {
                $fc2 = $this->buildCoilRequest($device->ip_address, $port, $unit_id);
                $fc3 = $this->buildRegisterRequest($device->ip_address, $port, $unit_id);

                try {
                    $fc2_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc2);
                    $fc3_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc3); 
                    $this->logResponse($device, $fc2_response, $fc3_response, $dt_now);

                } catch (\Throwable $th) {
                    $this->error('Exception: ' . $th->getMessage());
                }
            }
            sleep(60);
        }
    }

    private function buildCoilRequest($ip, $port, $unit_id)
    {
        return ReadCoilsBuilder::newReadInputDiscretes("tcp://{$ip}:{$port}", $unit_id)
            ->coil(0, 'is_correcting')
            ->build();
    }

    private function buildRegisterRequest($ip, $port, $unit_id)
    {
        return ReadRegistersBuilder::newReadHoldingRegisters("tcp://{$ip}:{$port}", $unit_id)
            ->int16(0, 'pv_01')
            ->int16(1, 'pv_02')
            ->int16(2, 'pv_03')
            ->int16(3, 'pv_04')
            ->int16(4, 'pv_05')
            ->int16(5, 'pv_06')
            ->int16(6, 'pv_07')
            ->int16(7, 'pv_08')
            ->int16(8, 'sv_01')
            ->int16(9, 'sv_02')
            ->int16(10, 'sv_03')
            ->int16(11, 'sv_04')
            ->int16(12, 'sv_05')
            ->int16(13, 'sv_06')
            ->int16(14, 'sv_07')
            ->int16(15, 'sv_08')
            ->build();
    }

    private function logResponse($device, $fc2_response, $fc3_response, $dt_now)
    {
        $this->info("Response from: {$device->ip_address} (Line {$device->line})");
        
        $fc2_data = $fc2_response->getData();
        $fc3_data = $fc3_response->getData();

        $metric = [
            'device_id' => $device->id,
            'pv_01'     => $fc3_data['pv_01'] ?? null,
            'pv_02'     => $fc3_data['pv_02'] ?? null,
            'pv_03'     => $fc3_data['pv_03'] ?? null,
            'pv_04'     => $fc3_data['pv_04'] ?? null,
            'pv_05'     => $fc3_data['pv_05'] ?? null,
            'pv_06'     => $fc3_data['pv_06'] ?? null,
            'pv_07'     => $fc3_data['pv_07'] ?? null,
            'pv_08'     => $fc3_data['pv_08'] ?? null,
            'dt_client' => $dt_now,
        ];

        $this->table(['Key', 'Value'], collect($metric)->map(function ($value, $key) {
            return [$key, $value];
        })->toArray());

        $this->saveMetric($metric);
    }

    private function saveMetric($metric)
    {
        // Implement your logic to save the metric data
    }
}
