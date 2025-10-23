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

    // --- NEW: Cycle detection configuration ---
    protected $cycleStartThreshold = 10; // Value to detect the start of a cycle
    protected $goodValueMin = 30;        // Min value for a good reading
    protected $goodValueMax = 40;        // Max value for a good reading
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

    // --- NEW: State machine for cycle detection ---
    // This array will hold the state for each machine's L/R position
    // Example entry: ['LINEA-mc1-L' => ['state' => 'capturing', 'peak_value' => 35, 'peak_data' => [...], 'start_time' => 167...]]
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
                    $readings = $this->pollDevice($device); // This function is now heavily modified
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
     * --- MODIFIED: Poll a single device and process all its lines using the new state machine logic ---
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
                    $request = ReadRegistersBuilder::newReadInputRegisters("tcp://" . $device->ip_address . ":" . $this->modbusPort, $unit_id)
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
     * --- MODIFIED: Core state machine logic to process a single cycle for one machine position ---
     */
    private function processMachineCycle(string $line, string $machineName, string $position, int $toeHeelValue, int $sideValue)
    {
        $cycleKey = "{$line}-{$machineName}-{$position}";
        $currentState = $this->cycleStates[$cycleKey] ?? ['state' => 'idle'];

        // Failsafe to reset a cycle that gets stuck in the 'capturing' state
        if ($currentState['state'] === 'capturing' && (time() - $currentState['start_time']) > $this->cycleTimeoutSeconds) {
            if ($this->option('d')) {
                $this->warn("Cycle for {$cycleKey} timed out. Resetting.");
            }
            unset($this->cycleStates[$cycleKey]);
            $currentState['state'] = 'idle';
        }

        // STATE: IDLE -> CAPTURING
        if ($currentState['state'] === 'idle' && $toeHeelValue >= $this->cycleStartThreshold) {
            $currentTime = time();
            $this->cycleStates[$cycleKey] = [
                'state'              => 'capturing',
                'peak_toe_heel'      => $toeHeelValue,
                'peak_side'          => $sideValue,
                'start_time'         => $currentTime,
                'peak_toe_heel_time' => $currentTime,
                'peak_side_time'     => $currentTime,
            ];
            if ($this->option('d')) {
                $this->line("Cycle started for {$cycleKey}. Initial values: [{$toeHeelValue}, {$sideValue}]");
            }
        }
        // STATE: CAPTURING
        elseif ($currentState['state'] === 'capturing') {

            if ($toeHeelValue > $currentState['peak_toe_heel']) {
                $this->cycleStates[$cycleKey]['peak_toe_heel'] = $toeHeelValue;
                $this->cycleStates[$cycleKey]['peak_toe_heel_time'] = time();
            }
            if ($sideValue > $currentState['peak_side']) {
                $this->cycleStates[$cycleKey]['peak_side'] = $sideValue;
                $this->cycleStates[$cycleKey]['peak_side_time'] = time();
            }

            // Cycle ends when toeHeel drops to 0
            if ($toeHeelValue == 0) {
                $peakToeHeel = $currentState['peak_toe_heel'];
                $peakSide = $currentState['peak_side'];
                $peakToeHeelTime = $currentState['peak_toe_heel_time'];
                $peakSideTime = $currentState['peak_side_time'];
                $startTime = $currentState['start_time'];
                $endTime = time();
                $duration = $endTime - $startTime; // <-- Dynamic duration

                $timeGap = abs($peakToeHeelTime - $peakSideTime);

                if ($timeGap <= 4) {
                    if (($peakToeHeel >= $this->goodValueMin && $peakToeHeel <= $this->goodValueMax) && $peakSide > 0) {
                        $finalPeakData = [$peakToeHeel, $peakSide];
                        $this->saveSuccessfulCycle($line, $machineName, $position, $finalPeakData, $duration);
                        unset($this->cycleStates[$cycleKey]);
                        return 1;
                    } else {
                        if ($this->option('v')) {
                            $this->warn("Cycle for {$cycleKey} discarded. Peak values [{$peakToeHeel}, {$peakSide}] were not in the valid range.");
                        }
                    }
                } else {
                    if ($this->option('v')) {
                        $this->warn("Cycle for {$cycleKey} discarded. Time gap was {$timeGap}s (> 4s).");
                    }
                }

                unset($this->cycleStates[$cycleKey]);
            }
        }

        return 0;
    }

    /**
     * --- NEW: Function to handle database insertion and incrementing counts ---
     */
    private function saveSuccessfulCycle(string $line, string $machineName, string $position, array $peakData)
    {
        // 1. Get the current cumulative count and increment it for the new record.
        $lastCumulative = $this->lastCumulativeValues[$line] ?? 0;
        $newCumulative = $lastCumulative + 1;

        // 2. Prepare data and save to database.
        $count = new InsDwpCount([
            'mechine' => (int) trim($machineName, "mc"),
            'line' => $line,
            'count' => $newCumulative, // The new total count
            'pv' => json_encode($peakData),
            'position' => $position,
            'duration' => $duration, // Duration of the cycle
            'incremental' => 1, // This represents one successful cycle
            'std_error' => json_encode([0,0]),
        ]);
        $count->save();

        // 3. Update the in-memory cumulative value for the next cycle.
        $this->lastCumulativeValues[$line] = $newCumulative;

        if ($this->option('v')) {
            $this->line("✓ Saved good cycle for {$line}-{$machineName}-{$position}. Peak: {$peakData[0]}. New total count: {$newCumulative}");
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
