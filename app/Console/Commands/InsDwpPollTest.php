<?php

namespace App\Console\Commands;

use App\Models\InsDwpDevice;
use App\Models\InsDwpCount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Network\NonBlockingClient;

class InsDwpPollTest extends Command
{
    // Polling configuration
    protected $pollIntervalSeconds = 1;
    protected $modbusTimeoutSeconds = 2;
    protected $modbusPort = 503;
    protected $modbusUnitId = 1;

    // Cycle detection configuration
    protected $cycleStartThreshold = 5;
    protected $cycleEndThreshold = 5;
    protected $consecutiveZerosNeeded = 5;
    protected $minCycleDurationMs = 200;
    protected $minSamples = 3;
    protected $maxBufferLength = 100;
    protected $cycleTimeoutSeconds = 100;
    protected $trailingZerosToKeep = 3;

    // Quality thresholds
    protected $goodValueMin = 30;
    protected $goodValueMax = 45;
    protected $goodExtendedMin = 25;
    protected $goodExtendedMax = 55;
    protected $marginalMin = 15;
    protected $marginalMax = 70;
    protected $sensorLowThreshold = 10;
    protected $pressureHighThreshold = 80;

    // Memory optimization
    protected $memoryCleanupInterval = 1000;

    protected $signature = 'app:ins-dwp-poll-test {--v : Verbose output} {--d : Debug output}';
    protected $description = 'Poll DWP (Deep-Well Press) counter data from Modbus servers and track incremental counts';

    // State tracking
    protected $lastCumulativeValues = [];
    protected $cycleStates = [];
    protected $modbusClients = [];
    protected $deviceStats = [];
    protected $pollCycleCount = 0;
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
        $this->logDebug('Configuration: Poll interval=' . $this->pollIntervalSeconds . 's, Timeout=' . $this->modbusTimeoutSeconds . 's');

        // Initialize Modbus clients for each device
        $this->initializeModbusClients($devices);

        while (true) {
            $this->executePollCycle($devices);
            sleep($this->pollIntervalSeconds);
        }
    }

    /**
     * Initialize reusable Modbus clients for all devices
     */
    private function initializeModbusClients($devices): void
    {
        foreach ($devices as $device) {
            $this->modbusClients[$device->id] = new NonBlockingClient([
                'readTimeoutSec' => $this->modbusTimeoutSeconds
            ]);
        }
    }

    /**
     * Execute a single poll cycle across all devices
     */
    private function executePollCycle($devices): void
    {
        $cycleStartTime = microtime(true);
        $cycleReadings = 0;
        $cycleErrors = 0;

        foreach ($devices as $device) {
            $this->logVerbose("→ Polling {$device->name} ({$device->ip_address})");

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
        $this->pollCycleCount++;

        if ($this->option('v') && ($cycleReadings > 0 || $cycleErrors > 0)) {
            $cycleTime = microtime(true) - $cycleStartTime;
            $this->info(sprintf(
                "Cycle #%d: %d readings saved, %d errors, %.2fms",
                $this->pollCycleCount,
                $cycleReadings,
                $cycleErrors,
                $cycleTime * 1000
            ));
        }

        if ($this->pollCycleCount % $this->memoryCleanupInterval === 0) {
            $this->cleanupMemory();
        }
    }

    /**
     * Poll a single device and process all its machines
     */
    private function pollDevice(InsDwpDevice $device): int
    {
        $savedReadingsCount = 0;
        $client = $this->modbusClients[$device->id] ?? null;

        if (!$client) {
            throw new \RuntimeException("No Modbus client initialized for device {$device->name}");
        }

        if (!is_array($device->config)) {
            $this->logDebug("Device {$device->name} has invalid config");
            return 0;
        }

        foreach ($device->config as $lineConfig) {
            if (!isset($lineConfig['line']) || !isset($lineConfig['list_mechine'])) {
                $this->logDebug("Invalid line config structure in device {$device->name}");
                continue;
            }

            $line = strtoupper(trim($lineConfig['line']));

            foreach ($lineConfig['list_mechine'] as $machineConfig) {
                if (!$this->validateMachineConfig($machineConfig)) {
                    $this->logDebug("Invalid machine config in line {$line}");
                    continue;
                }

                $savedReadingsCount += $this->pollMachine(
                    $device,
                    $client,
                    $line,
                    $machineConfig
                );
            }
        }

        return $savedReadingsCount;
    }

    /**
     * Validate machine configuration structure
     */
    private function validateMachineConfig(array $config): bool
    {
        $requiredKeys = ['name', 'addr_th_l', 'addr_th_r', 'addr_side_l', 'addr_side_r'];

        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Poll a single machine and process its data
     */
    private function pollMachine(InsDwpDevice $device, NonBlockingClient $client, string $line, array $machineConfig): int
    {
        $machineName = $machineConfig['name'];
        $this->logDebug("Processing machine: {$machineName} on line {$line}");

        try {
            $data = $this->readMachineRegisters($device, $client, $machineConfig);

            return $this->processMachineCycle($line, $machineName, [
                'L' => [
                    'toe_heel' => $data['toe_heel_left'],
                    'side' => $data['side_left']
                ],
                'R' => [
                    'toe_heel' => $data['toe_heel_right'],
                    'side' => $data['side_right']
                ]
            ]);
        } catch (\Exception $e) {
            $this->error("     ✗ Error reading machine {$machineName} on line {$line}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Read all registers for a machine using batched requests
     */
    private function readMachineRegisters(InsDwpDevice $device, NonBlockingClient $client, array $config): array
    {
        $ip = $device->ip_address;
        $port = $this->modbusPort;
        $unitId = $this->modbusUnitId;
        $tcpAddress = "tcp://{$ip}:{$port}";

        // Build individual requests
        $requests = [
            ReadRegistersBuilder::newReadInputRegisters($tcpAddress, $unitId)
                ->int16($config['addr_th_l'], 'toe_heel_left')
                ->build(),
            ReadRegistersBuilder::newReadInputRegisters($tcpAddress, $unitId)
                ->int16($config['addr_th_r'], 'toe_heel_right')
                ->build(),
            ReadRegistersBuilder::newReadInputRegisters($tcpAddress, $unitId)
                ->int16($config['addr_side_l'], 'side_left')
                ->build(),
            ReadRegistersBuilder::newReadInputRegisters($tcpAddress, $unitId)
                ->int16($config['addr_side_r'], 'side_right')
                ->build(),
        ];

        // Send requests and merge results
        $mergedData = [];
        foreach ($requests as $request) {
            $response = $client->sendRequests($request)->getData();
            $mergedData = array_merge($mergedData, $response);
        }

        return $mergedData;
    }

    /**
     * Process both L and R positions for a machine
     */
    private function processMachineCycle(string $line, string $machineName, array $positionsData): int
    {
        $savedCount = 0;

        // Process Left position
        $savedCount += $this->processPositionCycle(
            $line,
            $machineName,
            'L',
            $positionsData['L']
        );

        // Process Right position
        $savedCount += $this->processPositionCycle(
            $line,
            $machineName,
            'R',
            $positionsData['R']
        );

        return $savedCount;
    }

    /**
     * Process a single position (L/R) with robust cycle detection
     */
    private function processPositionCycle(string $line, string $machineName, string $position, array $data): int
    {
        $toeHeelValue = (int) $data['toe_heel'];
        $sideValue = (int) $data['side'];
        $cycleKey = "{$line}-{$machineName}-{$position}";

        // Initialize state if needed
        if (!isset($this->cycleStates[$cycleKey])) {
            $this->cycleStates[$cycleKey] = ['state' => 'idle'];
        }

        $state = &$this->cycleStates[$cycleKey];

        // Failsafe timeout check
        if ($this->isCycleTimedOut($state, $cycleKey)) {
            $state = ['state' => 'idle'];
            return 0;
        }

        // State machine
        if ($state['state'] === 'idle') {
            return $this->handleIdleState($state, $toeHeelValue, $sideValue, $cycleKey);
        }

        if ($state['state'] === 'active') {
            return $this->handleActiveState($state, $toeHeelValue, $sideValue, $line, $machineName, $position, $cycleKey);
        }

        return 0;
    }

    /**
     * Check if cycle has timed out
     */
    private function isCycleTimedOut(array $state, string $cycleKey): bool
    {
        if ($state['state'] !== 'idle' &&
            (time() - ($state['start_time'] ?? 0)) > $this->cycleTimeoutSeconds) {
            $this->logDebug("⚠️ Cycle {$cycleKey} timed out after {$this->cycleTimeoutSeconds}s. Resetting.");
            return true;
        }
        return false;
    }

    /**
     * Handle idle state - waiting for cycle to start
     */
    private function handleIdleState(array &$state, int $toeHeelValue, int $sideValue, string $cycleKey): int
    {
        if ($toeHeelValue >= $this->cycleStartThreshold || $sideValue >= $this->cycleStartThreshold) {
            $state = [
                'state' => 'active',
                'start_time' => microtime(true),
                'th_buffer' => [$toeHeelValue],
                'side_buffer' => [$sideValue],
                'consecutive_zeros' => 0,
            ];
            $this->logDebug("🟢 Cycle STARTED {$cycleKey}: TH={$toeHeelValue}, Side={$sideValue}");
        }
        return 0;
    }

    /**
     * Handle active state - collecting data and detecting end
     */
    private function handleActiveState(
        array &$state,
        int $toeHeelValue,
        int $sideValue,
        string $line,
        string $machineName,
        string $position,
        string $cycleKey
    ): int {
        $elapsedMs = (microtime(true) - $state['start_time']) * 1000;

        // Check for cycle end
        if ($toeHeelValue <= $this->cycleEndThreshold && $sideValue <= $this->cycleEndThreshold) {
            $state['consecutive_zeros']++;

            if ($state['consecutive_zeros'] >= $this->consecutiveZerosNeeded &&
                $elapsedMs >= $this->minCycleDurationMs) {
                return $this->completeCycle($state, $line, $machineName, $position, $cycleKey);
            }
        } else {
            // Active readings - reset zero counter and add to buffer
            $state['consecutive_zeros'] = 0;
            $state['th_buffer'][] = $toeHeelValue;
            $state['side_buffer'][] = $sideValue;

            if ($this->option('d') && count($state['th_buffer']) % 5 === 0) {
                $this->line("📈 {$cycleKey} buffer growth: " . count($state['th_buffer']) . " samples");
            }
        }

        // Buffer overflow protection
        if (count($state['th_buffer']) > $this->maxBufferLength) {
            $this->logDebug("⚠️ Buffer overflow for {$cycleKey}. Saving as OVERFLOW.");
            $saved = $this->saveCycle($line, $machineName, $position, $state, 'OVERFLOW', 0);
            $state = ['state' => 'idle'];
            return $saved;
        }

        return 0;
    }

    /**
     * Complete a cycle and save it
     */
    private function completeCycle(array &$state, string $line, string $machineName, string $position, string $cycleKey): int
    {
        $sampleCount = count($state['th_buffer']);

        // Add trailing zeros for smooth waveform ending
        for ($i = 0; $i < $this->trailingZerosToKeep; $i++) {
            $state['th_buffer'][] = 0;
            $state['side_buffer'][] = 0;
        }

        // Trim excessive trailing zeros
        $this->trimTrailingZeros($state);

        $cycleType = $sampleCount < $this->minSamples ? 'SHORT_CYCLE' : 'COMPLETE';
        $durationSec = round((microtime(true) - $state['start_time']), 2);

        $this->logCycleCompletion($cycleType, $cycleKey, $sampleCount, $durationSec);

        $saved = $this->saveCycle($line, $machineName, $position, $state, $cycleType, $durationSec);
        $state = ['state' => 'idle'];

        return $saved;
    }

    /**
     * Trim excessive trailing zeros from buffers
     */
    private function trimTrailingZeros(array &$state): void
    {
        while (count($state['th_buffer']) > max($this->minSamples, 5) &&
               end($state['th_buffer']) === 0 &&
               end($state['side_buffer']) === 0 &&
               count($state['th_buffer']) > $this->trailingZerosToKeep) {
            array_pop($state['th_buffer']);
            array_pop($state['side_buffer']);
        }
    }

    /**
     * Log cycle completion
     */
    private function logCycleCompletion(string $cycleType, string $cycleKey, int $sampleCount, float $durationSec): void
    {
        if (!$this->option('d')) {
            return;
        }

        $msg = $cycleType === 'SHORT_CYCLE'
            ? "🟡 SHORT cycle {$cycleKey} ({$sampleCount} samples). Saving anyway."
            : "✅ COMPLETE cycle {$cycleKey} ({$sampleCount} samples, {$durationSec}s).";

        $this->line($msg);
    }

    /**
     * Save cycle data to database with transaction protection
     */
    private function saveCycle(
        string $line,
        string $machineName,
        string $position,
        array $state,
        string $cycleType,
        float $duration
    ): int {
        $thBuffer = $state['th_buffer'] ?? [];
        $sideBuffer = $state['side_buffer'] ?? [];

        $maxTh = !empty($thBuffer) ? max($thBuffer) : 0;
        $maxSide = !empty($sideBuffer) ? max($sideBuffer) : 0;

        $qualityGrade = $this->determineQualityGrade($maxTh, $maxSide, $cycleType);
        $thQuality = $this->isQualityGood($maxTh) ? 1 : 0;
        $sideQuality = $this->isQualityGood($maxSide) ? 1 : 0;

        $enhancedPvData = [
            'waveforms' => [$thBuffer, $sideBuffer],
            'quality' => [
                'grade' => $qualityGrade,
                'peaks' => ['th' => $maxTh, 'side' => $maxSide],
                'cycle_type' => $cycleType,
                'sample_count' => count($thBuffer),
            ]
        ];

        $stdErrorBooleanArray = [[$thQuality], [$sideQuality]];

        try {
            DB::beginTransaction();

            $lastCumulative = $this->lastCumulativeValues[$line] ?? 0;
            $newCumulative = $lastCumulative + 1;

            $count = new InsDwpCount([
                'mechine' => (int) trim($machineName, "mc"),
                'line' => $line,
                'count' => $newCumulative,
                'pv' => json_encode($enhancedPvData),
                'position' => $position,
                'duration' => $duration ?: 1,
                'incremental' => 1,
                'std_error' => json_encode($stdErrorBooleanArray),
            ]);

            $count->save();
            $this->lastCumulativeValues[$line] = $newCumulative;

            DB::commit();

            $this->logCycleSave($thQuality, $sideQuality, $qualityGrade, $line, $machineName, $position, $maxTh, $maxSide, $newCumulative);

            return 1;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to save cycle for {$line}-{$machineName}-{$position}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a value is within good quality range
     */
    private function isQualityGood(int $value): bool
    {
        return $value >= $this->goodValueMin && $value <= $this->goodValueMax;
    }

    /**
     * Log cycle save information
     */
    private function logCycleSave(
        int $thQuality,
        int $sideQuality,
        string $qualityGrade,
        string $line,
        string $machineName,
        string $position,
        int $maxTh,
        int $maxSide,
        int $newCumulative
    ): void {
        if (!$this->option('v')) {
            return;
        }

        $statusIcon = ($thQuality && $sideQuality) ? '✅' : ($qualityGrade === 'DEFECTIVE' ? '❌' : '⚠️');

        $this->line(sprintf(
            "%s Saved %s cycle for %s-%s-%s. Peaks: TH=%d(%d), Side=%d(%d). Total: %d",
            $statusIcon,
            $qualityGrade,
            $line,
            $machineName,
            $position,
            $maxTh,
            $thQuality,
            $maxSide,
            $sideQuality,
            $newCumulative
        ));
    }

    /**
     * Determine quality grade based on peak values and cycle type
     */
    private function determineQualityGrade(int $maxTh, int $maxSide, string $cycleType): string
    {
        // Handle special cycle types
        if (in_array($cycleType, ['SHORT_CYCLE', 'OVERFLOW', 'TIMEOUT'])) {
            return $cycleType;
        }

        // Excellent - both in perfect range
        if ($this->isQualityGood($maxTh) && $this->isQualityGood($maxSide)) {
            return 'EXCELLENT';
        }

        // Good - both in extended range
        if ($this->isInRange($maxTh, $this->goodExtendedMin, $this->goodExtendedMax) &&
            $this->isInRange($maxSide, $this->goodExtendedMin, $this->goodExtendedMax)) {
            return 'GOOD';
        }

        // Marginal - one good, one acceptable
        $thGood = $this->isQualityGood($maxTh);
        $sideGood = $this->isQualityGood($maxSide);
        $thMarginal = $this->isInRange($maxTh, $this->marginalMin, $this->marginalMax);
        $sideMarginal = $this->isInRange($maxSide, $this->marginalMin, $this->marginalMax);

        if (($thGood && $sideMarginal) || ($sideGood && $thMarginal)) {
            return 'MARGINAL';
        }

        // Sensor issues
        if ($maxTh < $this->sensorLowThreshold && $maxSide < $this->sensorLowThreshold) {
            return 'SENSOR_LOW';
        }

        if ($maxTh > $this->pressureHighThreshold || $maxSide > $this->pressureHighThreshold) {
            return 'PRESSURE_HIGH';
        }

        return 'DEFECTIVE';
    }

    /**
     * Check if value is within range (inclusive)
     */
    private function isInRange(int $value, int $min, int $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * Clean up memory and stale state data
     */
    private function cleanupMemory(): void
    {
        static $activeDevicesCache = null;
        static $cacheExpiry = 0;

        // Refresh cache every 10 cleanup cycles (10,000 polls)
        if ($activeDevicesCache === null || time() > $cacheExpiry) {
            $activeDevicesCache = InsDwpDevice::active()->get();
            $cacheExpiry = time() + ($this->memoryCleanupInterval * 10 * $this->pollIntervalSeconds);
        }

        // Clean cumulative values
        $activeLines = $activeDevicesCache->flatMap(function ($device) {
            return $device->getLines();
        })->unique()->toArray();

        $this->lastCumulativeValues = array_intersect_key(
            $this->lastCumulativeValues,
            array_flip($activeLines)
        );

        // Clean cycle states
        $activeCycleKeys = $activeDevicesCache->flatMap(function ($device) {
            $keys = [];
            if (!is_array($device->config)) {
                return $keys;
            }

            foreach ($device->config as $lineConfig) {
                if (!isset($lineConfig['line']) || !isset($lineConfig['list_mechine'])) {
                    continue;
                }

                $line = strtoupper(trim($lineConfig['line']));
                foreach ($lineConfig['list_mechine'] as $listMachine) {
                    if (!isset($listMachine['name'])) {
                        continue;
                    }
                    $machineName = $listMachine['name'];
                    $keys[] = "{$line}-{$machineName}-L";
                    $keys[] = "{$line}-{$machineName}-R";
                }
            }
            return $keys;
        })->unique()->toArray();

        $this->cycleStates = array_intersect_key(
            $this->cycleStates,
            array_flip($activeCycleKeys)
        );

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        if ($this->option('d')) {
            $memoryUsage = memory_get_usage(true);
            $this->line(sprintf(
                "Memory cleanup performed. Current usage: %.2f MB, Cycle states: %d, Cumulative values: %d",
                $memoryUsage / 1024 / 1024,
                count($this->cycleStates),
                count($this->lastCumulativeValues)
            ));
        }
    }

    /**
     * Update device statistics for monitoring
     */
    private function updateDeviceStats(string $deviceName, bool $success): void
    {
        if (!isset($this->deviceStats[$deviceName])) {
            $this->deviceStats[$deviceName] = [
                'success_count' => 0,
                'error_count' => 0,
                'last_success' => null,
                'last_error' => null
            ];
        }

        if ($success) {
            $this->deviceStats[$deviceName]['success_count']++;
            $this->deviceStats[$deviceName]['last_success'] = now();
        } else {
            $this->deviceStats[$deviceName]['error_count']++;
            $this->deviceStats[$deviceName]['last_error'] = now();
        }

        // Log stats every 100 cycles
        if ($this->option('v') && $this->pollCycleCount > 0 && $this->pollCycleCount % 100 === 0) {
            $stats = $this->deviceStats[$deviceName];
            $total = $stats['success_count'] + $stats['error_count'];
            $successRate = $total > 0 ? round(($stats['success_count'] / $total) * 100, 1) : 0;

            $this->comment(sprintf(
                "Device %s stats: %.1f%% success rate (%d/%d)",
                $deviceName,
                $successRate,
                $stats['success_count'],
                $total
            ));
        }
    }

    /**
     * Helper method for verbose logging
     */
    private function logVerbose(string $message): void
    {
        if ($this->option('v')) {
            $this->comment($message);
        }
    }

    /**
     * Helper method for debug logging
     */
    private function logDebug(string $message): void
    {
        if ($this->option('d')) {
            $this->line($message);
        }
    }
}
