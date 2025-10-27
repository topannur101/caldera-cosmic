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
    
    // State machine for cycle detection - tracks both positions together for each machine
    // Example entry: ['LINEA-mc1' => ['state' => 'capturing', 'L' => [...], 'R' => [...], 'start_time' => 167...]]
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

                    // Process Left and Right positions together as one machine cycle
                    $savedReadingsCount += $this->processMachineCycle(
                        $line, 
                        $machineName,
                        [
                            'L' => ['toe_heel' => $response['toe_heel_left'], 'side' => $response['side_left']],
                            'R' => ['toe_heel' => $response['toe_heel_right'], 'side' => $response['side_right']]
                        ]
                    );

                } catch (\Exception $e) {
                    $this->error("    ✗ Error reading machine {$machineName} on line {$line}: " . $e->getMessage());
                    continue;
                }
            }
        }
        
        return $savedReadingsCount;
    }

    /**
     * Core state machine logic to process a complete cycle for both positions of a machine
     * Now waits for both L and R to complete before saving any data
     */
    private function processMachineCycle(string $line, string $machineName, array $positionsData)
    {
        $cycleKey = "{$line}-{$machineName}";
        $currentState = $this->cycleStates[$cycleKey] ?? ['state' => 'idle', 'L' => [], 'R' => []];

        // Failsafe timeout logic
        if ($currentState['state'] === 'capturing' && (time() - $currentState['start_time']) > $this->cycleTimeoutSeconds) {
            if($this->option('d')) {
                $this->warn("Cycle for {$cycleKey} timed out. Resetting.");
            }
            unset($this->cycleStates[$cycleKey]);
            $currentState = ['state' => 'idle', 'L' => [], 'R' => []];
        }

        $toeHeelL = $positionsData['L']['toe_heel'];
        $sideL = $positionsData['L']['side'];
        $toeHeelR = $positionsData['R']['toe_heel'];
        $sideR = $positionsData['R']['side'];

        // STATE: IDLE -> CAPTURING
        // Start capturing when at least one position starts a cycle
        if ($currentState['state'] === 'idle' && 
            (($toeHeelL >= $this->cycleStartThreshold || $sideL >= $this->cycleStartThreshold) || 
             ($toeHeelR >= $this->cycleStartThreshold || $sideR >= $this->cycleStartThreshold))) {
            
            $this->cycleStates[$cycleKey] = [
                'state' => 'capturing',
                'L' => [
                    'toe_heel_values' => [$toeHeelL],
                    'side_values' => [$sideL],
                    'toe_heel_ended' => false,
                    'side_ended' => false,
                    'active' => false,
                ],
                'R' => [
                    'toe_heel_values' => [$toeHeelR],
                    'side_values' => [$sideR],
                    'toe_heel_ended' => false,
                    'side_ended' => false,
                    'active' => false,
                ],
                'start_time' => time(),
            ];
            
            if($this->option('d')) {
                $this->line("Cycle started for {$cycleKey}. Initial values L: [{$toeHeelL}, {$sideL}] R: [{$toeHeelR}, {$sideR}]");
            }
            return 0;
        }
        
        // STATE: CAPTURING
        elseif ($currentState['state'] === 'capturing') {
            
            // Update Left position data
            $this->cycleStates[$cycleKey]['L']['toe_heel_values'][] = $toeHeelL;
            $this->cycleStates[$cycleKey]['L']['side_values'][] = $sideL;
            
            // Check if L position has started
            if (!$this->cycleStates[$cycleKey]['L']['active'] && 
                ($toeHeelL >= $this->cycleStartThreshold || $sideL >= $this->cycleStartThreshold)) {
                $this->cycleStates[$cycleKey]['L']['active'] = true;
                if($this->option('d')) {
                    $this->line("Left position active for {$cycleKey}");
                }
            }
            
            // Check if L toe/heel cycle has ended
            if (!$this->cycleStates[$cycleKey]['L']['toe_heel_ended'] && $toeHeelL <= $this->toeHeelEndThreshold) {
                $this->cycleStates[$cycleKey]['L']['toe_heel_ended'] = true;
                if($this->option('d')) {
                    $this->line("Toe/heel cycle ended for L-{$cycleKey}");
                }
            }
            
            // Check if L side cycle has ended
            if (!$this->cycleStates[$cycleKey]['L']['side_ended'] && $sideL <= $this->sideEndThreshold) {
                $this->cycleStates[$cycleKey]['L']['side_ended'] = true;
                if($this->option('d')) {
                    $this->line("Side cycle ended for L-{$cycleKey}");
                }
            }
            
            // Update Right position data
            $this->cycleStates[$cycleKey]['R']['toe_heel_values'][] = $toeHeelR;
            $this->cycleStates[$cycleKey]['R']['side_values'][] = $sideR;
            
            // Check if R position has started
            if (!$this->cycleStates[$cycleKey]['R']['active'] && 
                ($toeHeelR >= $this->cycleStartThreshold || $sideR >= $this->cycleStartThreshold)) {
                $this->cycleStates[$cycleKey]['R']['active'] = true;
                if($this->option('d')) {
                    $this->line("Right position active for {$cycleKey}");
                }
            }
            
            // Check if R toe/heel cycle has ended
            if (!$this->cycleStates[$cycleKey]['R']['toe_heel_ended'] && $toeHeelR <= $this->toeHeelEndThreshold) {
                $this->cycleStates[$cycleKey]['R']['toe_heel_ended'] = true;
                if($this->option('d')) {
                    $this->line("Toe/heel cycle ended for R-{$cycleKey}");
                }
            }
            
            // Check if R side cycle has ended
            if (!$this->cycleStates[$cycleKey]['R']['side_ended'] && $sideR <= $this->sideEndThreshold) {
                $this->cycleStates[$cycleKey]['R']['side_ended'] = true;
                if($this->option('d')) {
                    $this->line("Side cycle ended for R-{$cycleKey}");
                }
            }

            // A complete machine cycle ends when BOTH positions have ended their cycles
            $lComplete = $this->cycleStates[$cycleKey]['L']['toe_heel_ended'] && $this->cycleStates[$cycleKey]['L']['side_ended'];
            $rComplete = $this->cycleStates[$cycleKey]['R']['toe_heel_ended'] && $this->cycleStates[$cycleKey]['R']['side_ended'];
            
            if ($lComplete && $rComplete) {
                $lToeHeelValues = $this->cycleStates[$cycleKey]['L']['toe_heel_values'];
                $lSideValues = $this->cycleStates[$cycleKey]['L']['side_values'];
                $rToeHeelValues = $this->cycleStates[$cycleKey]['R']['toe_heel_values'];
                $rSideValues = $this->cycleStates[$cycleKey]['R']['side_values'];
                
                // Calculate duration dynamically from the cycle start time
                $cycleDuration = time() - $this->cycleStates[$cycleKey]['start_time'];
                
                if ($this->option('d')) {
                    $this->line("Complete cycle ended for {$cycleKey}. Duration: {$cycleDuration}s. L: [" . 
                                implode(',', $lToeHeelValues) . "] [" . 
                                implode(',', $lSideValues) . "] R: [" .
                                implode(',', $rToeHeelValues) . "] [" .
                                implode(',', $rSideValues) . "]");
                }

                // Validate both positions have good values
                $peakToeHeelL = max($lToeHeelValues);
                $peakToeHeelR = max($rToeHeelValues);
                
                $bothGood = ($peakToeHeelL >= $this->goodValueMin && $peakToeHeelL <= $this->goodValueMax) &&
                            ($peakToeHeelR >= $this->goodValueMin && $peakToeHeelR <= $this->goodValueMax);

                if ($bothGood) {
                    // Save both positions with calculated duration
                    $savedCount = 0;
                    $savedCount += $this->saveSuccessfulCycle($line, $machineName, 'L', [$lToeHeelValues, $lSideValues], $cycleDuration);
                    $savedCount += $this->saveSuccessfulCycle($line, $machineName, 'R', [$rToeHeelValues, $rSideValues], $cycleDuration);
                    unset($this->cycleStates[$cycleKey]);
                    return $savedCount;
                } else {
                    if ($this->option('v')) {
                        $this->warn("Peak values for {$cycleKey} are outside the good range ({$this->goodValueMin}-{$this->goodValueMax}). L: {$peakToeHeelL}, R: {$peakToeHeelR}. Discarding cycle.");
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
     * Returns 1 if saved successfully, 0 otherwise
     */
    private function saveSuccessfulCycle(string $line, string $machineName, string $position, array $collectedData, int $duration)
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
            'duration' => $duration, // Dynamic duration from cycle
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
        
        return 1;
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