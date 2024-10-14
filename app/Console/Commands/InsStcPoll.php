<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsStcMLog;
use App\Models\InsStcMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
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
    protected $description = 'Poll IP STC temperature and speed metrics from Modbus server installed in HMI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $machines = InsStcMachine::all();
        $port = 504;
        $unit_id = 1;
        
        while (true) {
            foreach ($machines as $machine) {
                $fc2 = $this->buildCoilRequest($machine->ip_address, $port, $unit_id);
                $fc3 = $this->buildRegisterRequest($machine->ip_address, $port, $unit_id);

                try {
                    $fc2_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc2);
                    $fc3_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc3); 
                    $this->logResponse($machine, $fc2_response, $fc3_response);

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
            ->int16(110, 'lower_pv_1')
            ->int16(111, 'lower_pv_2')
            ->int16(112, 'lower_pv_3')
            ->int16(113, 'lower_pv_4')
            ->int16(114, 'lower_pv_5')
            ->int16(115, 'lower_pv_6')
            ->int16(116, 'lower_pv_7')
            ->int16(117, 'lower_pv_8')
            ->int16(120, 'lower_sv_1')
            ->int16(121, 'lower_sv_2')
            ->int16(122, 'lower_sv_3')
            ->int16(123, 'lower_sv_4')
            ->int16(124, 'lower_sv_5')
            ->int16(125, 'lower_sv_6')
            ->int16(126, 'lower_sv_7')
            ->int16(127, 'lower_sv_8')
            ->int16(130, 'lower_speed')
            ->int16(210, 'upper_pv_1')
            ->int16(211, 'upper_pv_2')
            ->int16(212, 'upper_pv_3')
            ->int16(213, 'upper_pv_4')
            ->int16(214, 'upper_pv_5')
            ->int16(215, 'upper_pv_6')
            ->int16(216, 'upper_pv_7')
            ->int16(217, 'upper_pv_8')
            ->int16(220, 'upper_sv_1')
            ->int16(221, 'upper_sv_2')
            ->int16(222, 'upper_sv_3')
            ->int16(223, 'upper_sv_4')
            ->int16(224, 'upper_sv_5')
            ->int16(225, 'upper_sv_6')
            ->int16(226, 'upper_sv_7')
            ->int16(227, 'upper_sv_8')
            ->int16(230, 'upper_speed')
            ->build();
    }

    private function logResponse($machine, $fc2_response, $fc3_response)
    {
        $this->info("Response from: {$machine->ip_address} (Line {$machine->line})");
        
        $fc2_data = $fc2_response->getData();
        $fc3_data = $fc3_response->getData();

        $metricLower = [
            'pv_01'         => $fc3_data['lower_pv_1'] ?? null,
            'pv_2'         => $fc3_data['lower_pv_2'] ?? null,
            'pv_3'         => $fc3_data['lower_pv_3'] ?? null,
            'pv_4'         => $fc3_data['lower_pv_4'] ?? null,
            'pv_5'         => $fc3_data['lower_pv_5'] ?? null,
            'pv_6'         => $fc3_data['lower_pv_6'] ?? null,
            'pv_7'         => $fc3_data['lower_pv_7'] ?? null,
            'pv_8'         => $fc3_data['lower_pv_8'] ?? null,
            'sv_1'         => $fc3_data['lower_sv_1'] ?? null,
            'sv_2'         => $fc3_data['lower_sv_2'] ?? null,
            'sv_3'         => $fc3_data['lower_sv_3'] ?? null,
            'sv_4'         => $fc3_data['lower_sv_4'] ?? null,
            'sv_5'         => $fc3_data['lower_sv_5'] ?? null,
            'sv_6'         => $fc3_data['lower_sv_6'] ?? null,
            'sv_7'         => $fc3_data['lower_sv_7'] ?? null,
            'sv_8'         => $fc3_data['lower_sv_8'] ?? null,
            'speed'         => $fc3_data['lower_speed'] ?? null,
        ];
    
        $metricUpper = [
            'pv_1'         => $fc3_data['upper_pv_1'] ?? null,
            'pv_2'         => $fc3_data['upper_pv_2'] ?? null,
            'pv_3'         => $fc3_data['upper_pv_3'] ?? null,
            'pv_4'         => $fc3_data['upper_pv_4'] ?? null,
            'pv_5'         => $fc3_data['upper_pv_5'] ?? null,
            'pv_6'         => $fc3_data['upper_pv_6'] ?? null,
            'pv_7'         => $fc3_data['upper_pv_7'] ?? null,
            'pv_8'         => $fc3_data['upper_pv_8'] ?? null,
            'sv_1'         => $fc3_data['upper_sv_1'] ?? null,
            'sv_2'         => $fc3_data['upper_sv_2'] ?? null,
            'sv_3'         => $fc3_data['upper_sv_3'] ?? null,
            'sv_4'         => $fc3_data['upper_sv_4'] ?? null,
            'sv_5'         => $fc3_data['upper_sv_5'] ?? null,
            'sv_6'         => $fc3_data['upper_sv_6'] ?? null,
            'sv_7'         => $fc3_data['upper_sv_7'] ?? null,
            'sv_8'         => $fc3_data['upper_sv_8'] ?? null,
            'speed'         => $fc3_data['upper_speed'] ?? null,
        ];

        $this->table(['Key', 'Value'], collect($metricLower)->map(function ($value, $key) {
            return [$key, $value];
        })->toArray());

        $this->table(['Key', 'Value'], collect($metricUpper)->map(function ($value, $key) {
            return [$key, $value];
        })->toArray());

        $this->saveMetric($machine->id, $metricLower, $metricUpper);
    }

    private function saveMetric($machine_id, $metricLower, $metricUpper)
    {
        try {
            $validatedData = $this->validateMetric([
                'machine_id' => $machine_id,
                'metric_lower' => $metricLower,
                'metric_upper' => $metricUpper,
            ]);
            $logLower = InsStcMLog::create([
                'ins_stc_machine_id'    => $validatedData['machine_id'],
                'position'              => 'lower',
                'speed'                 => $validatedData['metric_lower']['speed'],
                'pv_1'                  => $validatedData['metric_lower']['pv_1'],
                'pv_2'                  => $validatedData['metric_lower']['pv_2'],
                'pv_3'                  => $validatedData['metric_lower']['pv_3'],
                'pv_4'                  => $validatedData['metric_lower']['pv_4'],
                'pv_5'                  => $validatedData['metric_lower']['pv_5'],
                'pv_6'                  => $validatedData['metric_lower']['pv_6'],
                'pv_7'                  => $validatedData['metric_lower']['pv_7'],
                'pv_8'                  => $validatedData['metric_lower']['pv_8'],
                'sv_1'                  => $validatedData['metric_lower']['sv_1'],
                'sv_2'                  => $validatedData['metric_lower']['sv_2'],
                'sv_3'                  => $validatedData['metric_lower']['sv_3'],
                'sv_4'                  => $validatedData['metric_lower']['sv_4'],
                'sv_5'                  => $validatedData['metric_lower']['sv_5'],
                'sv_6'                  => $validatedData['metric_lower']['sv_6'],
                'sv_7'                  => $validatedData['metric_lower']['sv_7'],
                'sv_8'                  => $validatedData['metric_lower']['sv_8'],
            ]);

            $logUpper = InsStcMLog::create([
                'ins_stc_machine_id'    => $validatedData['machine_id'],
                'position'              => 'upper',
                'speed'                 => $validatedData['metric_upper']['speed'],
                'pv_1'                  => $validatedData['metric_upper']['pv_1'],
                'pv_2'                  => $validatedData['metric_upper']['pv_2'],
                'pv_3'                  => $validatedData['metric_upper']['pv_3'],
                'pv_4'                  => $validatedData['metric_upper']['pv_4'],
                'pv_5'                  => $validatedData['metric_upper']['pv_5'],
                'pv_6'                  => $validatedData['metric_upper']['pv_6'],
                'pv_7'                  => $validatedData['metric_upper']['pv_7'],
                'pv_8'                  => $validatedData['metric_upper']['pv_8'],
                'sv_1'                  => $validatedData['metric_upper']['sv_1'],
                'sv_2'                  => $validatedData['metric_upper']['sv_2'],
                'sv_3'                  => $validatedData['metric_upper']['sv_3'],
                'sv_4'                  => $validatedData['metric_upper']['sv_4'],
                'sv_5'                  => $validatedData['metric_upper']['sv_5'],
                'sv_6'                  => $validatedData['metric_upper']['sv_6'],
                'sv_7'                  => $validatedData['metric_upper']['sv_7'],
                'sv_8'                  => $validatedData['metric_upper']['sv_8'],
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

            'metric_lower.speed' => 'nullable|numeric',
            'metric_lower.pv_1' => 'nullable|numeric',
            'metric_lower.pv_2' => 'nullable|numeric',
            'metric_lower.pv_3' => 'nullable|numeric',
            'metric_lower.pv_4' => 'nullable|numeric',
            'metric_lower.pv_5' => 'nullable|numeric',
            'metric_lower.pv_6' => 'nullable|numeric',
            'metric_lower.pv_7' => 'nullable|numeric',
            'metric_lower.pv_8' => 'nullable|numeric',
            'metric_lower.sv_1' => 'nullable|numeric',
            'metric_lower.sv_2' => 'nullable|numeric',
            'metric_lower.sv_3' => 'nullable|numeric',
            'metric_lower.sv_4' => 'nullable|numeric',
            'metric_lower.sv_5' => 'nullable|numeric',
            'metric_lower.sv_6' => 'nullable|numeric',
            'metric_lower.sv_7' => 'nullable|numeric',
            'metric_lower.sv_8' => 'nullable|numeric',

            'metric_upper.speed' => 'nullable|numeric',
            'metric_upper.pv_1' => 'nullable|numeric',
            'metric_upper.pv_2' => 'nullable|numeric',
            'metric_upper.pv_3' => 'nullable|numeric',
            'metric_upper.pv_4' => 'nullable|numeric',
            'metric_upper.pv_5' => 'nullable|numeric',
            'metric_upper.pv_6' => 'nullable|numeric',
            'metric_upper.pv_7' => 'nullable|numeric',
            'metric_upper.pv_8' => 'nullable|numeric',
            'metric_upper.sv_1' => 'nullable|numeric',
            'metric_upper.sv_2' => 'nullable|numeric',
            'metric_upper.sv_3' => 'nullable|numeric',
            'metric_upper.sv_4' => 'nullable|numeric',
            'metric_upper.sv_5' => 'nullable|numeric',
            'metric_upper.sv_6' => 'nullable|numeric',
            'metric_upper.sv_7' => 'nullable|numeric',
            'metric_upper.sv_8' => 'nullable|numeric',
        ];
    
        $validator = Validator::make($metric, $rules);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }
    
        return $validator->validated();
    }
}
