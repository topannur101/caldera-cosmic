<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\InsStc;
use App\Models\InsStcMLog;
use App\Models\InsStcMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadCoilsBuilder;

use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;

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
    protected $description = 'Poll IP STC temperature and speed metrics from Modbus server installed in HMI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $machines   = InsStcMachine::all();
        $port       = 503;
        $unit_id    = 1;
        $interval   = 600; // seconds
        
        while (true) {
            foreach ($machines as $machine) {

                $ip = $machine->ip_address;
                // $fc2 = $this->buildCoilRequest($machine->ip_address, $port, $unit_id);

                $lower_pv_r = InsStc::buildRegisterRequest('lower_pv_r', $ip, $port, $unit_id);
                $lower_sv_w = InsStc::buildRegisterRequest('lower_sv_w', $ip, $port, $unit_id);
                $lower_sv_r = InsStc::buildRegisterRequest('lower_sv_r', $ip, $port, $unit_id);
                $lower_sv_p = InsStc::buildRegisterRequest('lower_sv_p', $ip, $port, $unit_id);

                $upper_pv_r = InsStc::buildRegisterRequest('upper_pv_r', $ip, $port, $unit_id);
                $upper_sv_w = InsStc::buildRegisterRequest('upper_sv_w', $ip, $port, $unit_id);
                $upper_sv_r = InsStc::buildRegisterRequest('upper_sv_r', $ip, $port, $unit_id);
                $upper_sv_p = InsStc::buildRegisterRequest('upper_sv_p', $ip, $port, $unit_id);

                if (strpos($ip, '127.') !== 0) {

                    try {
                        // $fc2_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc2);
                        $lower_pv_r_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($lower_pv_r);
                        $lower_sv_w_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($lower_sv_w);
                        $lower_sv_r_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($lower_sv_r);
                        $lower_sv_p_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($lower_sv_p);
    
                        $upper_pv_r_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($upper_pv_r);
                        $upper_sv_w_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($upper_sv_w);
                        $upper_sv_r_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($upper_sv_r);
                        $upper_sv_p_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($upper_sv_p);
    
                        $lower_pv_r_data = $lower_pv_r_response->getData();
                        $lower_sv_w_data = $lower_sv_w_response->getData();
                        $lower_sv_r_data = $lower_sv_r_response->getData();
                        $lower_sv_p_data = $lower_sv_p_response->getData();
    
                        $upper_pv_r_data = $upper_pv_r_response->getData();
                        $upper_sv_w_data = $upper_sv_w_response->getData();
                        $upper_sv_r_data = $upper_sv_r_response->getData();
                        $upper_sv_p_data = $upper_sv_p_response->getData();
    
                        $data = array_merge(
                            $lower_pv_r_data,
                            $lower_sv_w_data,
                            $lower_sv_r_data,
                            $lower_sv_p_data,
        
                            $upper_pv_r_data,
                            $upper_sv_w_data,
                            $upper_sv_r_data,
                            $upper_sv_p_data
                        );
                        $this->logResponse($machine, $data);
    
                    } catch (\Throwable $th) {
                        $this->error('Exception: ' . $th->getMessage());
                    }

                } else {
                    $this->warn("The IP " . $ip . " (Line " . $machine->line .") is a loopback address. Ignored");
                }
            }
            sleep($interval);
        }
    }

    private function buildCoilRequest($ip, $port, $unit_id)
    {
        return ReadCoilsBuilder::newReadInputDiscretes("tcp://{$ip}:{$port}", $unit_id)
            ->coil(120, 'lower_sv_r_lock_1')
            ->coil(121, 'lower_sv_r_lock_2')
            ->coil(122, 'lower_sv_r_lock_3')
            ->coil(123, 'lower_sv_r_lock_4')
            ->coil(124, 'lower_sv_r_lock_5')
            ->coil(125, 'lower_sv_r_lock_6')
            ->coil(126, 'lower_sv_r_lock_7')
            ->coil(127, 'lower_sv_r_lock_8')
            ->build();
    }

    private function logResponse($machine, $data)
    {
        $this->info("Response from: {$machine->ip_address} (Line {$machine->line})");

        $metricLower = [
            'pv_r_1'         => $data['lower_pv_r_1'] ?? null,
            'pv_r_2'         => $data['lower_pv_r_2'] ?? null,
            'pv_r_3'         => $data['lower_pv_r_3'] ?? null,
            'pv_r_4'         => $data['lower_pv_r_4'] ?? null,
            'pv_r_5'         => $data['lower_pv_r_5'] ?? null,
            'pv_r_6'         => $data['lower_pv_r_6'] ?? null,
            'pv_r_7'         => $data['lower_pv_r_7'] ?? null,
            'pv_r_8'         => $data['lower_pv_r_8'] ?? null,

            'sv_w_1'         => $data['lower_sv_w_1'] ?? null,
            'sv_w_2'         => $data['lower_sv_w_2'] ?? null,
            'sv_w_3'         => $data['lower_sv_w_3'] ?? null,
            'sv_w_4'         => $data['lower_sv_w_4'] ?? null,
            'sv_w_5'         => $data['lower_sv_w_5'] ?? null,
            'sv_w_6'         => $data['lower_sv_w_6'] ?? null,
            'sv_w_7'         => $data['lower_sv_w_7'] ?? null,
            'sv_w_8'         => $data['lower_sv_w_8'] ?? null,

            'sv_r_1'         => $data['lower_sv_r_1'] ?? null,
            'sv_r_2'         => $data['lower_sv_r_2'] ?? null,
            'sv_r_3'         => $data['lower_sv_r_3'] ?? null,
            'sv_r_4'         => $data['lower_sv_r_4'] ?? null,
            'sv_r_5'         => $data['lower_sv_r_5'] ?? null,
            'sv_r_6'         => $data['lower_sv_r_6'] ?? null,
            'sv_r_7'         => $data['lower_sv_r_7'] ?? null,
            'sv_r_8'         => $data['lower_sv_r_8'] ?? null,

            'sv_p_1'         => $data['lower_sv_p_1'] ?? null,
            'sv_p_2'         => $data['lower_sv_p_2'] ?? null,
            'sv_p_3'         => $data['lower_sv_p_3'] ?? null,
            'sv_p_4'         => $data['lower_sv_p_4'] ?? null,
            'sv_p_5'         => $data['lower_sv_p_5'] ?? null,
            'sv_p_6'         => $data['lower_sv_p_6'] ?? null,
            'sv_p_7'         => $data['lower_sv_p_7'] ?? null,
            'sv_p_8'         => $data['lower_sv_p_8'] ?? null,
        ];
    
        $metricUpper = [
            'pv_r_1'         => $data['upper_pv_r_1'] ?? null,
            'pv_r_2'         => $data['upper_pv_r_2'] ?? null,
            'pv_r_3'         => $data['upper_pv_r_3'] ?? null,
            'pv_r_4'         => $data['upper_pv_r_4'] ?? null,
            'pv_r_5'         => $data['upper_pv_r_5'] ?? null,
            'pv_r_6'         => $data['upper_pv_r_6'] ?? null,
            'pv_r_7'         => $data['upper_pv_r_7'] ?? null,
            'pv_r_8'         => $data['upper_pv_r_8'] ?? null,

            'sv_w_1'         => $data['upper_sv_w_1'] ?? null,
            'sv_w_2'         => $data['upper_sv_w_2'] ?? null,
            'sv_w_3'         => $data['upper_sv_w_3'] ?? null,
            'sv_w_4'         => $data['upper_sv_w_4'] ?? null,
            'sv_w_5'         => $data['upper_sv_w_5'] ?? null,
            'sv_w_6'         => $data['upper_sv_w_6'] ?? null,
            'sv_w_7'         => $data['upper_sv_w_7'] ?? null,
            'sv_w_8'         => $data['upper_sv_w_8'] ?? null,

            'sv_r_1'         => $data['upper_sv_r_1'] ?? null,
            'sv_r_2'         => $data['upper_sv_r_2'] ?? null,
            'sv_r_3'         => $data['upper_sv_r_3'] ?? null,
            'sv_r_4'         => $data['upper_sv_r_4'] ?? null,
            'sv_r_5'         => $data['upper_sv_r_5'] ?? null,
            'sv_r_6'         => $data['upper_sv_r_6'] ?? null,
            'sv_r_7'         => $data['upper_sv_r_7'] ?? null,
            'sv_r_8'         => $data['upper_sv_r_8'] ?? null,

            'sv_p_1'         => $data['upper_sv_p_1'] ?? null,
            'sv_p_2'         => $data['upper_sv_p_2'] ?? null,
            'sv_p_3'         => $data['upper_sv_p_3'] ?? null,
            'sv_p_4'         => $data['upper_sv_p_4'] ?? null,
            'sv_p_5'         => $data['upper_sv_p_5'] ?? null,
            'sv_p_6'         => $data['upper_sv_p_6'] ?? null,
            'sv_p_7'         => $data['upper_sv_p_7'] ?? null,
            'sv_p_8'         => $data['upper_sv_p_8'] ?? null,
        ];

        $tableData = [];
        foreach ($metricLower as $key => $lowerValue) {
            $tableData[] = [
                "Lower $key",
                $lowerValue,
                "Upper $key",
                $metricUpper[$key],
            ];
        }

        $headers = ['Lower Key', 'Lower Value', 'Upper Key', 'Upper Value'];
        $this->table($headers, $tableData);

        $this->saveMetric($machine->id, $data);
    }

    private function saveMetric($machine_id, $data)
    {
        try {
            $validatedData = $this->validateMetric([
                'machine_id' => $machine_id,
                'data' => $data,
            ]);

            // print_r($validatedData);
            
            $logLower = InsStcMLog::create([
                'ins_stc_machine_id'    => $validatedData['machine_id'],
                'position'              => 'lower',
                'pv_r_1'                => $validatedData['data']['lower_pv_r_1'],
                'pv_r_2'                => $validatedData['data']['lower_pv_r_2'],
                'pv_r_3'                => $validatedData['data']['lower_pv_r_3'],
                'pv_r_4'                => $validatedData['data']['lower_pv_r_4'],
                'pv_r_5'                => $validatedData['data']['lower_pv_r_5'],
                'pv_r_6'                => $validatedData['data']['lower_pv_r_6'],
                'pv_r_7'                => $validatedData['data']['lower_pv_r_7'],
                'pv_r_8'                => $validatedData['data']['lower_pv_r_8'],

                'sv_w_1'                => $validatedData['data']['lower_sv_w_1'],
                'sv_w_2'                => $validatedData['data']['lower_sv_w_2'],
                'sv_w_3'                => $validatedData['data']['lower_sv_w_3'],
                'sv_w_4'                => $validatedData['data']['lower_sv_w_4'],
                'sv_w_5'                => $validatedData['data']['lower_sv_w_5'],
                'sv_w_6'                => $validatedData['data']['lower_sv_w_6'],
                'sv_w_7'                => $validatedData['data']['lower_sv_w_7'],
                'sv_w_8'                => $validatedData['data']['lower_sv_w_8'],

                'sv_r_1'                => $validatedData['data']['lower_sv_r_1'],
                'sv_r_2'                => $validatedData['data']['lower_sv_r_2'],
                'sv_r_3'                => $validatedData['data']['lower_sv_r_3'],
                'sv_r_4'                => $validatedData['data']['lower_sv_r_4'],
                'sv_r_5'                => $validatedData['data']['lower_sv_r_5'],
                'sv_r_6'                => $validatedData['data']['lower_sv_r_6'],
                'sv_r_7'                => $validatedData['data']['lower_sv_r_7'],
                'sv_r_8'                => $validatedData['data']['lower_sv_r_8'],

                'sv_p_1'                => $validatedData['data']['lower_sv_p_1'],
                'sv_p_2'                => $validatedData['data']['lower_sv_p_2'],
                'sv_p_3'                => $validatedData['data']['lower_sv_p_3'],
                'sv_p_4'                => $validatedData['data']['lower_sv_p_4'],
                'sv_p_5'                => $validatedData['data']['lower_sv_p_5'],
                'sv_p_6'                => $validatedData['data']['lower_sv_p_6'],
                'sv_p_7'                => $validatedData['data']['lower_sv_p_7'],
                'sv_p_8'                => $validatedData['data']['lower_sv_p_8'],
            ]);

            $logUpper = InsStcMLog::create([
                'ins_stc_machine_id'    => $validatedData['machine_id'],
                'position'              => 'upper',
                'pv_r_1'                => $validatedData['data']['upper_pv_r_1'],
                'pv_r_2'                => $validatedData['data']['upper_pv_r_2'],
                'pv_r_3'                => $validatedData['data']['upper_pv_r_3'],
                'pv_r_4'                => $validatedData['data']['upper_pv_r_4'],
                'pv_r_5'                => $validatedData['data']['upper_pv_r_5'],
                'pv_r_6'                => $validatedData['data']['upper_pv_r_6'],
                'pv_r_7'                => $validatedData['data']['upper_pv_r_7'],
                'pv_r_8'                => $validatedData['data']['upper_pv_r_8'],

                'sv_w_1'                => $validatedData['data']['upper_sv_w_1'],
                'sv_w_2'                => $validatedData['data']['upper_sv_w_2'],
                'sv_w_3'                => $validatedData['data']['upper_sv_w_3'],
                'sv_w_4'                => $validatedData['data']['upper_sv_w_4'],
                'sv_w_5'                => $validatedData['data']['upper_sv_w_5'],
                'sv_w_6'                => $validatedData['data']['upper_sv_w_6'],
                'sv_w_7'                => $validatedData['data']['upper_sv_w_7'],
                'sv_w_8'                => $validatedData['data']['upper_sv_w_8'],

                'sv_r_1'                => $validatedData['data']['upper_sv_r_1'],
                'sv_r_2'                => $validatedData['data']['upper_sv_r_2'],
                'sv_r_3'                => $validatedData['data']['upper_sv_r_3'],
                'sv_r_4'                => $validatedData['data']['upper_sv_r_4'],
                'sv_r_5'                => $validatedData['data']['upper_sv_r_5'],
                'sv_r_6'                => $validatedData['data']['upper_sv_r_6'],
                'sv_r_7'                => $validatedData['data']['upper_sv_r_7'],
                'sv_r_8'                => $validatedData['data']['upper_sv_r_8'],

                'sv_p_1'                => $validatedData['data']['upper_sv_p_1'],
                'sv_p_2'                => $validatedData['data']['upper_sv_p_2'],
                'sv_p_3'                => $validatedData['data']['upper_sv_p_3'],
                'sv_p_4'                => $validatedData['data']['upper_sv_p_4'],
                'sv_p_5'                => $validatedData['data']['upper_sv_p_5'],
                'sv_p_6'                => $validatedData['data']['upper_sv_p_6'],
                'sv_p_7'                => $validatedData['data']['upper_sv_p_7'],
                'sv_p_8'                => $validatedData['data']['upper_sv_p_8'],
            ]);
    
            $this->info("Metric saved successfully.");
            return [$logLower, $logUpper];
        } catch (\Illuminate\Database\QueryException $e) {
            $this->error("Database error while saving metric for machine ID: {$machine_id}");
            $this->error($e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->error("Unexpected error while saving metric for machine ID: {$machine_id}");
            $this->error($e->getMessage());
            return null;
        }
    }
    
    private function validateMetric($metric)
    {
        $rules = [
            'machine_id' => 'required|integer',

            'data.lower_pv_r_1' => 'nullable|numeric',
            'data.lower_pv_r_2' => 'nullable|numeric',
            'data.lower_pv_r_3' => 'nullable|numeric',
            'data.lower_pv_r_4' => 'nullable|numeric',
            'data.lower_pv_r_5' => 'nullable|numeric',
            'data.lower_pv_r_6' => 'nullable|numeric',
            'data.lower_pv_r_7' => 'nullable|numeric',
            'data.lower_pv_r_8' => 'nullable|numeric',

            'data.lower_sv_w_1' => 'nullable|numeric',
            'data.lower_sv_w_2' => 'nullable|numeric',
            'data.lower_sv_w_3' => 'nullable|numeric',
            'data.lower_sv_w_4' => 'nullable|numeric',
            'data.lower_sv_w_5' => 'nullable|numeric',
            'data.lower_sv_w_6' => 'nullable|numeric',
            'data.lower_sv_w_7' => 'nullable|numeric',
            'data.lower_sv_w_8' => 'nullable|numeric',

            'data.lower_sv_r_1' => 'nullable|numeric',
            'data.lower_sv_r_2' => 'nullable|numeric',
            'data.lower_sv_r_3' => 'nullable|numeric',
            'data.lower_sv_r_4' => 'nullable|numeric',
            'data.lower_sv_r_5' => 'nullable|numeric',
            'data.lower_sv_r_6' => 'nullable|numeric',
            'data.lower_sv_r_7' => 'nullable|numeric',
            'data.lower_sv_r_8' => 'nullable|numeric',

            'data.lower_sv_p_1' => 'nullable|numeric',
            'data.lower_sv_p_2' => 'nullable|numeric',
            'data.lower_sv_p_3' => 'nullable|numeric',
            'data.lower_sv_p_4' => 'nullable|numeric',
            'data.lower_sv_p_5' => 'nullable|numeric',
            'data.lower_sv_p_6' => 'nullable|numeric',
            'data.lower_sv_p_7' => 'nullable|numeric',
            'data.lower_sv_p_8' => 'nullable|numeric',

            'data.upper_pv_r_1' => 'nullable|numeric',
            'data.upper_pv_r_2' => 'nullable|numeric',
            'data.upper_pv_r_3' => 'nullable|numeric',
            'data.upper_pv_r_4' => 'nullable|numeric',
            'data.upper_pv_r_5' => 'nullable|numeric',
            'data.upper_pv_r_6' => 'nullable|numeric',
            'data.upper_pv_r_7' => 'nullable|numeric',
            'data.upper_pv_r_8' => 'nullable|numeric',

            'data.upper_sv_w_1' => 'nullable|numeric',
            'data.upper_sv_w_2' => 'nullable|numeric',
            'data.upper_sv_w_3' => 'nullable|numeric',
            'data.upper_sv_w_4' => 'nullable|numeric',
            'data.upper_sv_w_5' => 'nullable|numeric',
            'data.upper_sv_w_6' => 'nullable|numeric',
            'data.upper_sv_w_7' => 'nullable|numeric',
            'data.upper_sv_w_8' => 'nullable|numeric',

            'data.upper_sv_r_1' => 'nullable|numeric',
            'data.upper_sv_r_2' => 'nullable|numeric',
            'data.upper_sv_r_3' => 'nullable|numeric',
            'data.upper_sv_r_4' => 'nullable|numeric',
            'data.upper_sv_r_5' => 'nullable|numeric',
            'data.upper_sv_r_6' => 'nullable|numeric',
            'data.upper_sv_r_7' => 'nullable|numeric',
            'data.upper_sv_r_8' => 'nullable|numeric',

            'data.upper_sv_p_1' => 'nullable|numeric',
            'data.upper_sv_p_2' => 'nullable|numeric',
            'data.upper_sv_p_3' => 'nullable|numeric',
            'data.upper_sv_p_4' => 'nullable|numeric',
            'data.upper_sv_p_5' => 'nullable|numeric',
            'data.upper_sv_p_6' => 'nullable|numeric',
            'data.upper_sv_p_7' => 'nullable|numeric',
            'data.upper_sv_p_8' => 'nullable|numeric',
        ];
    
        $validator = Validator::make($metric, $rules);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }
    
        return $validator->validated();
    }
}
