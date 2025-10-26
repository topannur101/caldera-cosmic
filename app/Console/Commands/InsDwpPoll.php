<?php

namespace App\Console\Commands;

use App\Models\InsDwpDevice;
use App\Models\InsDwpCount;
use Carbon\Carbon;
use Illuminate\Console\Command;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Network\NonBlockingClient;

class InsDwpPoll extends Command
{
    // Configuration variables
    protected $pollIntervalSeconds = 1;
    protected $modbusTimeoutSeconds = 2;
    protected $modbusPort = 503;

    // Cycle detection configuration - now with independent thresholds for each sensor
    protected $cycleStartThreshold = 1; // Value to detect the start of a cycle
    protected $toeHeelEndThreshold = 2; // Value to detect end of toe/heel cycle
    protected $sideEndThreshold = 2;    // Value to detect end of side cycle
    protected $goodValueMin = 30;       // Min value for a good reading
    protected $goodValueMax = 40;       // Max value for a good reading
    protected $cycleTimeoutSeconds = 30; // Failsafe to reset a stuck cycle
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-dwp-poll {--v : Verbose output} {--d : Debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll DWP (Deep-Well Press) counter data from Modbus servers and track incremental counts';

    // In-memory buffer to track last cumulative values per line
    protected $lastCumulativeValues = [];
    
    // State machine for cycle detection - now tracks each sensor independently
    // Example entry: ['LINEA-mc1-L' => ['state' => 'capturing', 'toe_heel_values' => [], 'side_values' => [], 'toe_heel_ended' => false, 'side_ended' => false, 'start_time' => 167...]]
    protected $cycleStates = [];
    
    // Memory optimization counters
    protected $pollCycleCount = 0;
    protected $memoryCleanupInterval = 1000; // Clean memory every 1000 cycles
    
    // Statistics tracking
    protected $deviceStats = [];
    protected $totalReadings = 0;
    protected $totalErrors = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $devices = InsDwpDevice::active()->get();
        if ($devices->isEmpty()) {
            $this->error('✗ No active DWP devices found');
            return 1;
        }

        $this->info('✓ InsDwpPoll started - monitoring ' . count($devices) . ' devices');
        if ($this->option('v')) {
            $this->comment('Devices:');
            foreach ($devices as $device) {
                $lines = implode(', ', $device->getLines());
                $this->comment("  → {$device->name} ({$device->ip_address}) - Lines: {$lines}");
            }
        }
        
        $this->initializeLastValues($devices);

        while (true) {
            $cycleStartTime = microtime(true);
            $cycleReadings = 0;
            $cycleErrors = 0;
            
            foreach ($devices as $device) {
                if ($this->option('v')) {
                    $this->comment("→ Polling {$device->name} ({$device->ip_address})");
                }
                try {
                    $readings = $this->pollDevice($device);
                    $cycleReadings += $readings;
                    $this->updateDeviceStats($device->name, true);
                } catch (\Throwable $th) {
                    $this->error("✗ Error polling {$device->name} ({$device->ip_address}): " . $th->getMessage());
                    $cycleErrors++;
                    $this->updateDeviceStats($device->name, false);
                }
            }
            
            $this->totalReadings += $cycleReadings;
            $this->totalErrors += $cycleErrors;
            
            if ($this->option('v') && ($cycleReadings > 0 || $cycleErrors > 0)) {
                $cycleTime = microtime(true) - $cycleStartTime;
                $this->info("Cycle #{$this->pollCycleCount}: {$cycleReadings} new readings saved, {$cycleErrors} errors, " . 
                            number_format($cycleTime * 1000, 2) . "ms");
            }

            sleep($this->pollIntervalSeconds);
            $this->pollCycleCount++;
            
            if ($this->pollCycleCount % $this->memoryCleanupInterval === 0) {
                $this->cleanupMemory();
            }
        }
    }

    /**
     * Initialize last cumulative values from database
     */
    private function initializeLastValues($devices)
    {
        foreach ($devices as $device) {
            foreach ($device->getLines() as $line) {
                $lastCount = InsDwpCount::latestForLine($line);
                $this->lastCumulativeValues[$line] = $lastCount ? $lastCount->count : 0;
                if ($this->option('d')) {
                    $this->line("Initialized line {$line} with last cumulative: {$this->lastCumulativeValues[$line]}");
                }
            }
        }
    }

    /**
     * Poll a single device and process all its lines using the new state machine logic
     */
    private function pollDevice(InsDwpDevice $device)
    {
        $unit_id = 1;
        $savedReadingsCount = 0;

        foreach ($device->config as $lineConfig) {
            $line = strtoupper(trim($lineConfig['line']));
            
            foreach($lineConfig['list_mechine'] as $listMachine){
                try {
                    $machineName = $listMachine['name'];

                    // We can optimize by reading all 4 registers in one request
                    $request = ReadRegistersBuilder::newReadInputRegisters('tcp://' . $device->ip_address . ':' . $this->modbusPort, $unit_id)
                        ->int16($listMachine['addr_th_l'], 'toe_heel_left')
                        ->int16($listMachine['addr_th_r'], 'toe_heel_right')
                        ->int16($listMachine['addr_side_l'], 'side_left')
                        ->int16($listMachine['addr_side_r'], 'side_right')
                        ->build();
                    
                    $response = (new NonBlockingClient(['readTimeoutSec' => $this->modbusTimeoutSeconds]))
                        ->sendRequests($request)->getData();

                    // Process Left and Right positions independently
                    $savedReadingsCount += $this->processMachineCycle($line, $machineName, 'L', $response['toe_heel_left'], $response['side_left']);
                    $savedReadingsCount += $this->processMachineCycle($line, $machineName, 'R', $response['toe_heel_right'], $response['side_right']);

                } catch (\Exception $e) {
                    $this->error("    ✗ Error reading machine {$machineName} on line {$line}: " . $e->getMessage());
                    continue;
                }
            }
        }
        
        return $savedReadingsCount;
    }

    /**
     * Core state machine logic to process a single cycle for one machine position
     * Now tracks toe/heel and side sensors independently
     */
    private function processMachineCycle(string $line, string $machineName, string $position, int $toeHeelValue, int $sideValue)
    {
        $cycleKey = "{$line}-{$machineName}-{$position}";
        $currentState = $this->cycleStates[$cycleKey] ?? ['state' => 'idle'];

        // Failsafe timeout logic
        if ($currentState['state'] === 'capturing' && (time() - $currentState['start_time']) > $this->cycleTimeoutSeconds) {
            if($this->option('d')) {
                $this->warn("Cycle for {$cycleKey} timed out. Resetting.");
            }
            unset($this->cycleStates[$cycleKey]);
            $currentState['state'] = 'idle';
        }

        // STATE: IDLE -> CAPTURING
        if ($currentState['state'] === 'idle' && ($toeHeelValue >= $this->cycleStartThreshold || $sideValue >= $this->cycleStartThreshold)) {
            $this->cycleStates[$cycleKey] = [
                'state' => 'capturing',
                'toe_heel_values' => [$toeHeelValue],
                'side_values' => [$sideValue],
                'toe_heel_ended' => false,
                'side_ended' => false,
                'start_time' => time(),
            ];
            if($this->option('d')) {
                $this->line("Cycle started for {$cycleKey}. Initial values: [{$toeHeelValue}, {$sideValue}]");
            }
        } 
        // STATE: CAPTURING
        elseif ($currentState['state'] === 'capturing') {
            
            // Add current values to the collection arrays
            $this->cycleStates[$cycleKey]['toe_heel_values'][] = $toeHeelValue;
            $this->cycleStates[$cycleKey]['side_values'][] = $sideValue;

            // Check if toe/heel cycle has ended (dropped below threshold)
            if (!$this->cycleStates[$cycleKey]['toe_heel_ended'] && $toeHeelValue <= $this->toeHeelEndThreshold) {
                $this->cycleStates[$cycleKey]['toe_heel_ended'] = true;
                if($this->option('d')) {
                    $this->line("Toe/heel cycle ended for {$cycleKey}");
                }
            }

            // Check if side cycle has ended (dropped below threshold)
            if (!$this->cycleStates[$cycleKey]['side_ended'] && $sideValue <= $this->sideEndThreshold) {
                $this->cycleStates[$cycleKey]['side_ended'] = true;
                if($this->option('d')) {
                    $this->line("Side cycle ended for {$cycleKey}");
                }
            }

            // A complete cycle ends when BOTH sensors have ended their cycles
            if ($this->cycleStates[$cycleKey]['toe_heel_ended'] && $this->cycleStates[$cycleKey]['side_ended']) {
                $toeHeelValues = $this->cycleStates[$cycleKey]['toe_heel_values'];
                $sideValues = $this->cycleStates[$cycleKey]['side_values'];
                
                // Combine the collected values into one array for saving
                $collectedData = [$toeHeelValues, $sideValues];

                if ($this->option('d')) {
                    $this->line("Complete cycle ended for {$cycleKey}. Collected data: [" . 
                                implode(',', $toeHeelValues) . "] [" . 
                                implode(',', $sideValues) . "]");
                }

                // Only save if the cycle has good values (e.g., peak toe/heel is in the good range)
                $peakToeHeel = max($toeHeelValues);
                if ($peakToeHeel >= $this->goodValueMin && $peakToeHeel <= $this->goodValueMax) {
                    $this->saveSuccessfulCycle($line, $machineName, $position, $collectedData);
                    unset($this->cycleStates[$cycleKey]);
                    return 1;
                } else {
                    if ($this->option('v')) {
                        $this->warn("Peak value {$peakToeHeel} for {$cycleKey} is outside the good range ({$this->goodValueMin}-{$this->goodValueMax}). Discarding cycle.");
                    }
                }
                
                // Reset the state whether it was good or not.
                unset($this->cycleStates[$cycleKey]);
            }
        }
        
        return 0;
    }

    /**
     * Function to handle database insertion and incrementing counts
     */
    private function saveSuccessfulCycle(string $line, string $machineName, string $position, array $collectedData)
    {
        // 1. Get the current cumulative count and increment it for the new record.
        $lastCumulative = $this->lastCumulativeValues[$line] ?? 0;
        $newCumulative = $lastCumulative + 1;

        // 2. Prepare data and save to database.
        $count = new InsDwpCount([
            'mechine' => (int) trim($machineName, "mc"),
            'line' => $line,
            'count' => $newCumulative, // The new total count
            'pv' => json_encode($collectedData), // Store the collected arrays
            'position' => $position,
            'duration' => 17, // Static value as requested
            'incremental' => 1, // This represents one successful cycle
            'std_error' => json_encode([0,0]),
        ]);
        $count->save();

        // 3. Update the in-memory cumulative value for the next cycle.
        $this->lastCumulativeValues[$line] = $newCumulative;

        if ($this->option('v')) {
            $toeHeelValues = $collectedData[0];
            $peakValue = max($toeHeelValues);
            $this->line("✓ Saved good cycle for {$line}-{$machineName}-{$position}. Peak: {$peakValue}. New total count: {$newCumulative}");
        }
    }

    /**
     * Clean up memory by removing old entries and forcing garbage collection
     */
    private function cleanupMemory()
    {
        // This function can also be used to clean up stale cycleStates if needed,
        // but the current logic of unsetting keys should be efficient.
        
        $activeLines = InsDwpDevice::active()->get()->flatMap(function ($device) {
            return $device->getLines();
        })->unique()->toArray();
        
        $this->lastCumulativeValues = array_intersect_key(
            $this->lastCumulativeValues, 
            array_flip($activeLines)
        );
        
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        if ($this->option('d')) {
            $memoryUsage = memory_get_usage(true);
            $this->line("Memory cleanup performed. Current usage: " . number_format($memoryUsage / 1024 / 1024, 2) . " MB");
        }
    }

    /**
     * Update device statistics
     */
    private function updateDeviceStats(string $deviceName, bool $success)
    {
        if (!isset($this->deviceStats[$deviceName])) {
            $this->deviceStats[$deviceName] = ['success_count' => 0, 'error_count' => 0, 'last_success' => null, 'last_error' => null];
        }

        if ($success) {
            $this->deviceStats[$deviceName]['success_count']++;
            $this->deviceStats[$deviceName]['last_success'] = now();
        } else {
            $this->deviceStats[$deviceName]['error_count']++;
            $this->deviceStats[$deviceName]['last_error'] = now();
        }

        if ($this->option('v') && $this->pollCycleCount > 0 && $this->pollCycleCount % 100 === 0) {
            $stats = $this->deviceStats[$deviceName];
            $total = $stats['success_count'] + $stats['error_count'];
            $successRate = $total > 0 ? round(($stats['success_count'] / $total) * 100, 1) : 0;
            
            $this->comment("Device {$deviceName} stats: {$successRate}% success rate ({$stats['success_count']}/{$total})");
        }
    }
}