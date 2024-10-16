<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsStcMLog;
use App\Models\InsStcMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadCoilsBuilder;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
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
        $interval   = 60; // seconds
        
        while (true) {
            foreach ($machines as $machine) {
                // $fc2 = $this->buildCoilRequest($machine->ip_address, $port, $unit_id);
                $inputRegisterRequestL      = $this->buildRegisterRequest('inputRegisterLower', $machine->ip_address, $port, $unit_id);
                $inputRegisterRequestU      = $this->buildRegisterRequest('inputRegisterUpper', $machine->ip_address, $port, $unit_id);
                $holdingRegisterRequestL    = $this->buildRegisterRequest('holdingRegisterLower', $machine->ip_address, $port, $unit_id);
                $holdingRegisterRequestU    = $this->buildRegisterRequest('holdingRegisterUpper', $machine->ip_address, $port, $unit_id);

                try {
                    // $fc2_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc2);
                    $inputRegisterResponseL = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($inputRegisterRequestL);
                    $inputRegisterResponseU = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($inputRegisterRequestU);
                    $holdingRegisterResponseL = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($holdingRegisterRequestL);
                    $holdingRegisterResponseU = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($holdingRegisterRequestU);

                    $inputRegisterDataL = $inputRegisterResponseL->getData();
                    $inputRegisterDataU = $inputRegisterResponseU->getData();
                    $holdingRegisterDataL = $holdingRegisterResponseL->getData();
                    $holdingRegisterDataU = $holdingRegisterResponseU->getData();
                    $data = array_merge($inputRegisterDataL, $inputRegisterDataU, $holdingRegisterDataL, $holdingRegisterDataU);
                    $this->logResponse($machine, $data);

                } catch (\Throwable $th) {
                    $this->error('Exception: ' . $th->getMessage());
                }
            }
            sleep($interval);
        }
    }

    private function buildCoilRequest($ip, $port, $unit_id)
    {
        return ReadCoilsBuilder::newReadInputDiscretes("tcp://{$ip}:{$port}", $unit_id)
            ->coil(0, 'is_correcting')
            ->build();
    }

    private function buildRegisterRequest($type, $ip, $port, $unit_id)
    {
        switch ($type) {
            case 'inputRegisterLower':
                return ReadRegistersBuilder::newReadInputRegisters("tcp://{$ip}:{$port}", $unit_id)
                    ->int16(120, 'lower_pv_1')
                    ->int16(121, 'lower_pv_2')
                    ->int16(122, 'lower_pv_3')
                    ->int16(123, 'lower_pv_4')
                    ->int16(124, 'lower_pv_5')
                    ->int16(125, 'lower_pv_6')
                    ->int16(126, 'lower_pv_7')
                    ->int16(127, 'lower_pv_8')
                    ->build();
                break;
            case 'holdingRegisterLower':
                return ReadRegistersBuilder::newReadHoldingRegisters("tcp://{$ip}:{$port}", $unit_id)
                    ->int16(140, 'lower_sv_1')
                    ->int16(141, 'lower_sv_2')
                    ->int16(142, 'lower_sv_3')
                    ->int16(143, 'lower_sv_4')
                    ->int16(144, 'lower_sv_5')
                    ->int16(145, 'lower_sv_6')
                    ->int16(146, 'lower_sv_7')
                    ->int16(147, 'lower_sv_8')
                    ->build();
                break;
            case 'inputRegisterUpper':
                return ReadRegistersBuilder::newReadInputRegisters("tcp://{$ip}:{$port}", $unit_id)                   
                    ->int16(220, 'upper_pv_1')
                    ->int16(221, 'upper_pv_2')
                    ->int16(222, 'upper_pv_3')
                    ->int16(223, 'upper_pv_4')
                    ->int16(224, 'upper_pv_5')
                    ->int16(225, 'upper_pv_6')
                    ->int16(226, 'upper_pv_7')
                    ->int16(227, 'upper_pv_8')
                    ->build();
                break;
            case 'holdingRegisterUpper':
                return ReadRegistersBuilder::newReadHoldingRegisters("tcp://{$ip}:{$port}", $unit_id)                   
                    ->int16(240, 'upper_sv_1')
                    ->int16(241, 'upper_sv_2')
                    ->int16(242, 'upper_sv_3')
                    ->int16(243, 'upper_sv_4')
                    ->int16(244, 'upper_sv_5')
                    ->int16(245, 'upper_sv_6')
                    ->int16(246, 'upper_sv_7')
                    ->int16(247, 'upper_sv_8')
                    ->build();
                break;
        }
    }

    private function logResponse($machine, $data)
    {
        $this->info("Response from: {$machine->ip_address} (Line {$machine->line})");

        $metricLower = [
            'pv_1'         => $data['lower_pv_1'] ?? null,
            'pv_2'         => $data['lower_pv_2'] ?? null,
            'pv_3'         => $data['lower_pv_3'] ?? null,
            'pv_4'         => $data['lower_pv_4'] ?? null,
            'pv_5'         => $data['lower_pv_5'] ?? null,
            'pv_6'         => $data['lower_pv_6'] ?? null,
            'pv_7'         => $data['lower_pv_7'] ?? null,
            'pv_8'         => $data['lower_pv_8'] ?? null,
            'sv_1'         => $data['lower_sv_1'] ?? null,
            'sv_2'         => $data['lower_sv_2'] ?? null,
            'sv_3'         => $data['lower_sv_3'] ?? null,
            'sv_4'         => $data['lower_sv_4'] ?? null,
            'sv_5'         => $data['lower_sv_5'] ?? null,
            'sv_6'         => $data['lower_sv_6'] ?? null,
            'sv_7'         => $data['lower_sv_7'] ?? null,
            'sv_8'         => $data['lower_sv_8'] ?? null,
        ];
    
        $metricUpper = [
            'pv_1'         => $data['upper_pv_1'] ?? null,
            'pv_2'         => $data['upper_pv_2'] ?? null,
            'pv_3'         => $data['upper_pv_3'] ?? null,
            'pv_4'         => $data['upper_pv_4'] ?? null,
            'pv_5'         => $data['upper_pv_5'] ?? null,
            'pv_6'         => $data['upper_pv_6'] ?? null,
            'pv_7'         => $data['upper_pv_7'] ?? null,
            'pv_8'         => $data['upper_pv_8'] ?? null,
            'sv_1'         => $data['upper_sv_1'] ?? null,
            'sv_2'         => $data['upper_sv_2'] ?? null,
            'sv_3'         => $data['upper_sv_3'] ?? null,
            'sv_4'         => $data['upper_sv_4'] ?? null,
            'sv_5'         => $data['upper_sv_5'] ?? null,
            'sv_6'         => $data['upper_sv_6'] ?? null,
            'sv_7'         => $data['upper_sv_7'] ?? null,
            'sv_8'         => $data['upper_sv_8'] ?? null,
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
            $logLower = InsStcMLog::create([
                'ins_stc_machine_id'    => $validatedData['machine_id'],
                'position'              => 'lower',
                'pv_1'                  => $validatedData['data']['lower_pv_1'],
                'pv_2'                  => $validatedData['data']['lower_pv_2'],
                'pv_3'                  => $validatedData['data']['lower_pv_3'],
                'pv_4'                  => $validatedData['data']['lower_pv_4'],
                'pv_5'                  => $validatedData['data']['lower_pv_5'],
                'pv_6'                  => $validatedData['data']['lower_pv_6'],
                'pv_7'                  => $validatedData['data']['lower_pv_7'],
                'pv_8'                  => $validatedData['data']['lower_pv_8'],
                'sv_1'                  => $validatedData['data']['lower_sv_1'],
                'sv_2'                  => $validatedData['data']['lower_sv_2'],
                'sv_3'                  => $validatedData['data']['lower_sv_3'],
                'sv_4'                  => $validatedData['data']['lower_sv_4'],
                'sv_5'                  => $validatedData['data']['lower_sv_5'],
                'sv_6'                  => $validatedData['data']['lower_sv_6'],
                'sv_7'                  => $validatedData['data']['lower_sv_7'],
                'sv_8'                  => $validatedData['data']['lower_sv_8'],
                'speed'                 => 0
            ]);

            $logUpper = InsStcMLog::create([
                'ins_stc_machine_id'    => $validatedData['machine_id'],
                'position'              => 'upper',
                'pv_1'                  => $validatedData['data']['upper_pv_1'],
                'pv_2'                  => $validatedData['data']['upper_pv_2'],
                'pv_3'                  => $validatedData['data']['upper_pv_3'],
                'pv_4'                  => $validatedData['data']['upper_pv_4'],
                'pv_5'                  => $validatedData['data']['upper_pv_5'],
                'pv_6'                  => $validatedData['data']['upper_pv_6'],
                'pv_7'                  => $validatedData['data']['upper_pv_7'],
                'pv_8'                  => $validatedData['data']['upper_pv_8'],
                'sv_1'                  => $validatedData['data']['upper_sv_1'],
                'sv_2'                  => $validatedData['data']['upper_sv_2'],
                'sv_3'                  => $validatedData['data']['upper_sv_3'],
                'sv_4'                  => $validatedData['data']['upper_sv_4'],
                'sv_5'                  => $validatedData['data']['upper_sv_5'],
                'sv_6'                  => $validatedData['data']['upper_sv_6'],
                'sv_7'                  => $validatedData['data']['upper_sv_7'],
                'sv_8'                  => $validatedData['data']['upper_sv_8'],
                'speed'                 => 0
            ]);
    
            $this->info("Metric saved successfully for machine ID: {$machine_id}");
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

            'data.lower_pv_1' => 'nullable|numeric',
            'data.lower_pv_2' => 'nullable|numeric',
            'data.lower_pv_3' => 'nullable|numeric',
            'data.lower_pv_4' => 'nullable|numeric',
            'data.lower_pv_5' => 'nullable|numeric',
            'data.lower_pv_6' => 'nullable|numeric',
            'data.lower_pv_7' => 'nullable|numeric',
            'data.lower_pv_8' => 'nullable|numeric',
            'data.lower_sv_1' => 'nullable|numeric',
            'data.lower_sv_2' => 'nullable|numeric',
            'data.lower_sv_3' => 'nullable|numeric',
            'data.lower_sv_4' => 'nullable|numeric',
            'data.lower_sv_5' => 'nullable|numeric',
            'data.lower_sv_6' => 'nullable|numeric',
            'data.lower_sv_7' => 'nullable|numeric',
            'data.lower_sv_8' => 'nullable|numeric',

            'data.upper_pv_1' => 'nullable|numeric',
            'data.upper_pv_2' => 'nullable|numeric',
            'data.upper_pv_3' => 'nullable|numeric',
            'data.upper_pv_4' => 'nullable|numeric',
            'data.upper_pv_5' => 'nullable|numeric',
            'data.upper_pv_6' => 'nullable|numeric',
            'data.upper_pv_7' => 'nullable|numeric',
            'data.upper_pv_8' => 'nullable|numeric',
            'data.upper_sv_1' => 'nullable|numeric',
            'data.upper_sv_2' => 'nullable|numeric',
            'data.upper_sv_3' => 'nullable|numeric',
            'data.upper_sv_4' => 'nullable|numeric',
            'data.upper_sv_5' => 'nullable|numeric',
            'data.upper_sv_6' => 'nullable|numeric',
            'data.upper_sv_7' => 'nullable|numeric',
            'data.upper_sv_8' => 'nullable|numeric',
        ];
    
        $validator = Validator::make($metric, $rules);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }
    
        return $validator->validated();
    }
}
