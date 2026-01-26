<?php

namespace App\Console\Commands;

use App\Models\InsBpmDevice;
use App\Models\InsBpmCount;
use App\Models\UptimeLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\WriteMultipleRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleCoilRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleRegisterRequest;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Utils\Types;

class InsBpmPollTest extends Command
{
    // Configuration variables
    protected $pollIntervalSeconds = 1;
    protected $modbusTimeoutSeconds = 2;
    protected $modbusPort = 503;
    protected $spikeFilterThreshold = 2; // Counter increment <= this value will be treated as spike (adjust based on hardware)
    public $addressWrite  = [
        'M1_Hot'   => 10,
        'M1_Cold'  => 11,
        'M2_Hot'   => 12,
        'M2_Cold'  => 13,
        'M3_Hot'   => 14,
        'M3_Cold'  => 15,
        'M4_Hot'   => 16,
        'M4_Cold'  => 17,
    ];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-bpm-poll-test {--v : Verbose output} {--d : Debug output} {--drytest : Dry run to check conditions without executing reset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll BPM (Deep-Well Alarm Constraint Time) counter data from Modbus servers and track incremental counts';

    // In-memory buffer to track last cumulative values per line and condition
    protected  $lastCumulativeValues = []; // Format: ['M1_Hot' => 123, 'M1_Cold' => 456]
    protected  $lastReadingDates     = []; // Format: ['M1_Hot' => '2026-01-09', 'M1_Cold' => '2026-01-09']
    private    $lastDurationValues   = [];
    private    $lastSentDurationValues = []; // Track last sent duration per line
    public int $saveDuration = 0;

    // Memory optimization counters
    protected $pollCycleCount = 0;
    protected $memoryCleanupInterval = 1000; // Clean memory every 1000 cycles

    // Statistics tracking
    protected $deviceStats = [];
    protected $totalReadings = 0;
    protected $totalErrors = 0;

    // Track devices that have been reset today (to avoid multiple resets)
    protected $resetToday = [];
    
    // Track when each device came online today (for time-based spike filtering)
    protected $deviceOnlineTime = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all active devices
        $devices = InsBpmDevice::active()->get();
        if ($devices->isEmpty()) {
            $this->error('✗ No active BPM devices found');
            return 1;
        }
        $this->info('✓ InsBpmPoll started - monitoring ' . count($devices) . ' devices');
        if ($this->option('v')) {
            $this->comment('Devices:');
            foreach ($devices as $device) {
                $lines = implode(', ', $device->getLines());
                $this->comment("  → {$device->name} ({$device->ip_address}) - Lines: {$lines}");
            }
        }

        // Check for first online today and reset if needed
        foreach ($devices as $device) {
           $this->checkAndResetIfFirstOnlineToday($device);
        }

        // forach device, poll once for testing
        foreach ($devices as $device) {
            if ($this->option('v')) {
                $this->comment("→ Polling {$device->name} ({$device->ip_address})");
            }

            try {
                $readings = $this->pollDevice($device);
                if ($readings > 0) {
                    $this->info("✓ Polling {$device->name} completed - {$readings} new readings saved");
                } else {
                    $this->info("→ Polling {$device->name} completed - no new readings");
                }
            } catch (\Throwable $th) {
                $this->error("✗ Error polling {$device->name}: " . $th->getMessage());
            }
        }
    }

    /**
     * Check if device is coming online for the first time today and reset if needed
     * Only reset if it's the VERY FIRST online transition - not if device has already cycled offline/online multiple times today
     */
    private function checkAndResetIfFirstOnlineToday(InsBpmDevice $device)
    {
        $deviceKey = $device->name . '_' . $device->ip_address;
        // Skip if already reset today
        if (isset($this->resetToday[$deviceKey])) {
            if ($this->option('drytest')) {
                $this->comment("  [DRYTEST] Device {$device->name} already reset today, skipping");
            }
            return;
        }

        // Check UptimeLog for first online today
        $today = Carbon::today();
        
        // Get ALL logs today for this device (to check for multiple transitions)
        $logsToday = UptimeLog::where('ip_address', $device->ip_address)
            ->whereDate('checked_at', $today)
            ->orderBy('checked_at', 'asc')
            ->get();

        // Get yesterday's last log (to check if it was offline)
        $lastLogYesterday = UptimeLog::where('ip_address', $device->ip_address)
            ->whereDate('checked_at', '<', $today)
            ->orderBy('checked_at', 'desc')
            ->first();

        // Determine if this is first online today (with no previous cycles)
        $isFirstOnlineToday = false;
        $shouldReset = false;
        
        if ($logsToday->isEmpty()) {
            // No log today yet - this would be handled on next poll
            if ($this->option('drytest') || $this->option('d')) {
                $this->comment("  [CHECK] No logs today yet for {$device->name}, waiting for first log");
            }
            return;
        }

        // Check if there are multiple status transitions today (offline → online → offline → online)
        $statusTransitions = 0;
        $previousStatus = $lastLogYesterday ? $lastLogYesterday->status : null;
        
        foreach ($logsToday as $log) {
            if ($previousStatus && $log->status !== $previousStatus) {
                $statusTransitions++;
            }
            $previousStatus = $log->status;
        }

        if ($this->option('drytest') || $this->option('d')) {
            $this->comment("  [CHECK] Device {$device->name}: {$statusTransitions} status transition(s) today");
        }

        // Only reset if:
        // 1. There is exactly 1 transition today (offline → online, the first time)
        // 2. OR no transitions but device is online and yesterday was offline
        $firstLogToday = $logsToday->first();
        $currentStatus = $logsToday->last()->status;

        if ($statusTransitions === 1 && $currentStatus === 'online') {
            // Only one transition from offline to online - this is first online today
            $isFirstOnlineToday = true;
            $shouldReset = true;
            
            if ($this->option('drytest') || $this->option('d')) {
                $this->comment("  [CHECK] First online transition today for {$device->name} at {$firstLogToday->checked_at}");
            }
        } elseif ($statusTransitions > 1) {
            // Multiple transitions - device has been cycling offline/online
            if ($this->option('drytest') || $this->option('d')) {
                $this->comment("  [CHECK] Device {$device->name} has multiple transitions today - NOT resetting (already cycled)");
            }
            $shouldReset = false;
        } elseif ($statusTransitions === 0 && $currentStatus === 'online' && 
                  (!$lastLogYesterday || $lastLogYesterday->status === 'offline')) {
            // Device is consistently online today but was offline yesterday
            $isFirstOnlineToday = true;
            $shouldReset = true;
            
            if ($this->option('drytest') || $this->option('d')) {
                $this->comment("  [CHECK] Device {$device->name} online today, was offline yesterday - first online");
            }
        }

        if ($shouldReset) {
            if ($this->option('drytest')) {
                $this->info("  🔄 [DRYTEST] Would reset {$device->name} ({$device->ip_address}) - First online today detected");
                $this->comment("     → Command that would be executed: php artisan app:ins-bpm-reset");
            } else {
                $this->info("  🔄 First online today detected for {$device->name} - executing reset...");
                
                try {
                    // Call the reset command programmatically
                    $exitCode = \Illuminate\Support\Facades\Artisan::call('app:ins-bpm-reset', [
                        '--v' => $this->option('v'),
                        '--d' => $this->option('d'),
                    ]);
                    
                    if ($exitCode === 0) {
                        $this->info("  ✓ Reset completed successfully for {$device->name}");
                        // Mark as reset today to avoid duplicate resets
                        $this->resetToday[$deviceKey] = Carbon::now();
                        
                        // Track when device came online (for time-based spike filtering)
                        $this->deviceOnlineTime[$deviceKey] = Carbon::now();
                    } else {
                        $this->error("  ✗ Reset command failed with exit code {$exitCode}");
                    }
                } catch (\Throwable $th) {
                    $this->error("  ✗ Error executing reset: " . $th->getMessage());
                }
            }
        } else {
            // Even if not resetting, track online time if device is currently online
            if ($currentStatus === 'online' && !isset($this->deviceOnlineTime[$deviceKey])) {
                $this->deviceOnlineTime[$deviceKey] = $firstLogToday ? $firstLogToday->checked_at : Carbon::now();
            }
            
            if ($this->option('drytest') || $this->option('d')) {
                $this->comment("  [CHECK] Device {$device->name} - Not first online today or already cycled, no reset needed");
            }
        }
    }

    private function pollDevice(InsBpmDevice $device)
    {
        $unit_id = 1; // Standard Modbus unit ID
        $readingsCount = 0;
        $hmiUpdates = []; // Track HMI updates: ['M1_Hot' => 2, 'M1_Cold' => 3, ...]
        foreach ($device->config['list_mechine'] as $machineConfig) {
            $machineName = $machineConfig['name'];
            $hotAddrs    = $machineConfig['addr_hot'];
            $coldAddrs   = $machineConfig['addr_cold'];
            $line        = $machineConfig['line'] ?? $device->line;
            
            if ($this->option('d')) {
                $this->line("  Polling machine {$machineName} at addresses hot: {$hotAddrs}, cold: {$coldAddrs}");
            }

            try {
                // REQUEST DATA COUNTER HOT
                $requestConditionHot = ReadRegistersBuilder::newReadInputRegisters(
                        'tcp://' . $device->ip_address . ':' . $this->modbusPort,
                        $unit_id)
                        ->int16($hotAddrs, 'counter_hot') // Counter value at hot
                        ->build();
                // Execute Modbus request
                $responseConditionHot = (new NonBlockingClient(['readTimeoutSec' => $this->modbusTimeoutSeconds]))
                    ->sendRequests($requestConditionHot)->getData();
                $counterHot = abs($responseConditionHot['counter_hot']); // Make absolute to ensure positive values

                // REQUEST DATA COUNTER COLD
                $requestConditionCold = ReadRegistersBuilder::newReadInputRegisters(
                    'tcp://' . $device->ip_address . ':' . $this->modbusPort,
                    $unit_id)
                    ->int16($coldAddrs, 'counter_cold') // Counter value at cold
                    ->build();
                // Execute Modbus request
                $responseConditionCold = (new NonBlockingClient(['readTimeoutSec' => $this->modbusTimeoutSeconds]))
                    ->sendRequests($requestConditionCold)->getData();
                $counterCold = abs($responseConditionCold['counter_cold']); // Make absolute to ensure positive values
                
                if ($this->option('d')) {
                    $this->line("    Read values - Hot: {$counterHot}, Cold: {$counterCold}");
                }
                
                // Process HOT condition
                $hmiUpdates["M{$machineName}_Hot"] = $counterHot;
                
                // Process COLD condition
                $hmiUpdates["M{$machineName}_Cold"] = $counterCold;
            } catch (\Exception $e) {
                $this->error("    ✗ Error reading machine {$machineName}: " . $e->getMessage() . " at line " . $e->getLine());
                continue;
            }
        }

        // Cek koneksi device sebelum lanjut
        if (!$this->isDeviceConnected($device->ip_address, $this->modbusPort)) {
            $this->error("    ✗ Device {$device->ip_address} not connected. Skipping update and save.");
            return 0;
        }

        // check condition decrement if < 11 AM decrement condition its 1 and if >=11 AM decrement condition its 2
        $currentHour = Carbon::now()->hour;
        $conditionDecrement = ($currentHour < 11) ? 1 : 2;
        $values = [
                "M1_Hot"  => max(0, ($hmiUpdates['M1_Hot'] ?? 0) - $conditionDecrement),
                "M1_Cold" => max(0, ($hmiUpdates['M1_Cold'] ?? 0) - $conditionDecrement),
                "M2_Hot"  => max(0, ($hmiUpdates['M2_Hot'] ?? 0) - $conditionDecrement),
                "M2_Cold" => max(0, ($hmiUpdates['M2_Cold'] ?? 0) - $conditionDecrement),
                "M3_Hot"  => max(0, ($hmiUpdates['M3_Hot'] ?? 0) - $conditionDecrement),
                "M3_Cold" => max(0, ($hmiUpdates['M3_Cold'] ?? 0) - $conditionDecrement),
                "M4_Hot"  => $hmiUpdates['M4_Hot'] ?? 0,
                "M4_Cold" => $hmiUpdates['M4_Cold'] ?? 0,
        ];

        // Check if device recently came online (within spike-prone window)
        $deviceKey          = $device->name . '_' . $device->ip_address;
        $deviceOnlineTime   = $this->deviceOnlineTime[$deviceKey] ?? null;
        $minutesSinceOnline = null;
        $inSpikeProneWindow = false;
        $spikeProneWindowMinutes = 60; // Spike filtering more aggressive within first 60 minutes after HMI power ON
        
        if ($deviceOnlineTime) {
            $minutesSinceOnline = Carbon::now()->diffInMinutes($deviceOnlineTime);
            $inSpikeProneWindow = ($minutesSinceOnline <= $spikeProneWindowMinutes);
            
            if ($this->option('d') && $inSpikeProneWindow) {
                $this->comment("    ⏱️  Device online for {$minutesSinceOnline} minutes - in spike-prone window (first {$spikeProneWindowMinutes} min)");
            }
        }
        
        foreach (['M1_Hot', 'M1_Cold', 'M2_Hot', 'M2_Cold', 'M3_Hot', 'M3_Cold', 'M4_Hot', 'M4_Cold'] as $key) {
            $currentValue = $values[$key] ?? 0;
            $previousValue = $this->lastCumulativeValues[$key] ?? null;

            // If no previous value in memory, get from database
            if (is_null($previousValue)) {
                // Extract machine name and condition from key (e.g., "M1_Hot" -> machine=1, condition=Hot)
                preg_match('/M(\d+)_(Hot|Cold)/', $key, $matches);
                if (count($matches) === 3) {
                    $machineName = $matches[1];
                    $condition = $matches[2];
                    
                    // Get last saved value from database
                    $latestRecord = InsBpmCount::where('plant', $device->name)
                        ->where('line', strtoupper(trim($device->line)))
                        ->where('machine', strtoupper(trim($machineName)))
                        ->where('condition', $condition)
                        ->latest('created_at')
                        ->first();
                    
                    if ($latestRecord) {
                        $previousValue = $latestRecord->cumulative;
                        
                        if ($this->option('d')) {
                            $this->line("    → No memory value for {$key}, using DB value: {$previousValue}");
                        }
                    }
                }
            }

            // Only check for spike if we have a previous value to compare
            if (!is_null($previousValue)) {
                $bump = $currentValue - $previousValue;
                
                // Determine spike threshold based on time since online
                // More strict filtering when device just came online (HMI on but machine not yet running)
                $activeThreshold = $inSpikeProneWindow ? $this->spikeFilterThreshold : $this->spikeFilterThreshold;
                
                // Detect spike: positive but small increment (1 to threshold)
                if ($bump > 0 && $bump <= $activeThreshold) {
                    // Spike detected - ignore the invalid increment
                    if ($this->option('v') || $this->option('d')) {
                        $this->comment("    ⚠️  SPIKE DETECTED on {$key}: bump +{$bump} (prev: {$previousValue}, current: {$currentValue})");
                        if ($inSpikeProneWindow) {
                            $this->comment("    → Likely HMI power-on spike ({$minutesSinceOnline} min since online) - ignoring");
                        } else {
                            $this->comment("    → Ignoring spike, keeping previous value {$previousValue}");
                        }
                    }
                    
                    // Revert to previous value (spike filtering)
                    $values[$key] = $previousValue;
                }
            }
        }

        // Write all HMI updates in one call
        if (!empty($hmiUpdates)) {
            $this->pushToHmi($device, $values);
        }

        // now save readings to database
        foreach ($device->config['list_mechine'] as $machineConfig) {
            $machineName = $machineConfig['name'];
            $line        = $device->line;
            // Process HOT condition
            $newReadings = $this->processCondition($device, $line, $machineName, 'Hot', $values["M{$machineName}_Hot"] ?? 0);
            $readingsCount += $newReadings;

            // Process COLD condition
            $newReadings = $this->processCondition($device, $line, $machineName, 'Cold', $values["M{$machineName}_Cold"] ?? 0);
            $readingsCount += $newReadings;
        }

        return $readingsCount;
    }

    // Check if device is connected
    private function isDeviceConnected($ip, $port, $timeout = 1)
    {
        $connected = false;
        try {
            $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if ($fp) {
                fclose($fp);
                $connected = true;
            }
        } catch (\Exception $e) {
            $connected = false;
        }
        return $connected;
    }
    
    /**
     * Process a single condition (Hot or Cold) and save if changed
     */
    private function processCondition(InsBpmDevice $device, $line, string $machineName, string $condition, int $currentCumulative): int
    {
        // Do not force cumulative to 1 if HMI value is 0; let it reflect the real HMI value
        
        $key = $machineName . '_' . $condition;
        $today = Carbon::now()->toDateString();
        
        // Get latest record from database to compare
        $latestRecord = InsBpmCount::where('plant', $device->name)
            ->where('line', strtoupper(trim($line)))
            ->where('machine', strtoupper(trim($machineName)))
            ->where('condition', $condition)
            ->latest('created_at')
            ->first();
        
        // Check if cumulative value is same as in database - don't save duplicates
        if ($latestRecord && $latestRecord->cumulative === $currentCumulative) {
            // Update memory cache even though we're not saving
            $this->lastCumulativeValues[$key] = $currentCumulative;
            $this->lastReadingDates[$key] = $today;
            
            if ($this->option('d')) {
                $this->line("    → No change for {$key} - DB cumulative {$latestRecord->cumulative} = HMI {$currentCumulative} - skipping save");
            }
            return 0;
        }
        
        // Check if this is first reading today (not in memory or different day)
        if (!isset($this->lastCumulativeValues[$key]) || 
            !isset($this->lastReadingDates[$key]) || 
            $this->lastReadingDates[$key] !== $today) {  
            // First reading of the day: if there is a previous DB record, set incremental = currentCumulative - previous cumulative (if positive),
            // but if there is NO previous DB record, set incremental = 1 only if cumulative > 0, else 0
            $incremental = 0;
            if ($latestRecord) {
                $incremental = $currentCumulative - $latestRecord->cumulative;
                if ($incremental < 0) {
                    $incremental = 0;
                }
            } else {
                $incremental = ($currentCumulative > 0) ? 1 : 0;
            }
            InsBpmCount::create([
                'plant' => $device->name,
                'line' => $line,
                'machine' => $machineName,
                'condition' => $condition,
                'incremental' => $incremental,
                'cumulative' => $currentCumulative,
            ]);
            $this->lastCumulativeValues[$key] = $currentCumulative;
            $this->lastReadingDates[$key] = $today;
            if ($this->option('d')) {
                $this->line("    ✓ First reading today for {$key} - saved with increment {$incremental}, cumulative {$currentCumulative}");
            }
            return 1;
        }

        // Check if value is the same as last reading in memory
        if ($currentCumulative === $this->lastCumulativeValues[$key]) {
            if ($this->option('d')) {
                $this->line("    → No change for {$key} (still {$currentCumulative}) - skipping save");
            }
            return 0;
        }

        // Value is different - calculate increment
        $increment = $currentCumulative - $this->lastCumulativeValues[$key];
        
        // Only save if increment is positive (ignore decreases/resets)
        if ($increment > 0) {
            // Save to database
            InsBpmCount::create([
                'plant' => $device->name,
                'line' => $line,
                'machine' => $machineName,
                'condition' => $condition,
                'incremental' => $increment,
                'cumulative' => $currentCumulative,
            ]);
            
            // Update last cumulative value
            $this->lastCumulativeValues[$key] = $currentCumulative;
            
            if ($this->option('d')) {
                $this->line("    ✓ Saved {$condition}: increment {$increment}, cumulative {$currentCumulative}");
            }
            
            return 1;
        } else {
            if ($this->option('d')) {
                $this->line("    ⚠ Negative increment ({$increment}) for {$key} - possible counter reset, updating baseline");
            }
            // Update baseline for counter resets
            $this->lastCumulativeValues[$key] = $currentCumulative;
            return 0;
        }
    }

    /**
     * Clean up memory by removing old entries and forcing garbage collection
     */
    private function cleanupMemory()
    {
        // Limit the lastCumulativeValues array size by keeping only active lines
        $activeLines = InsDwpDevice::active()->get()->flatMap(function ($device) {
            return $device->getLines();
        })->unique()->toArray();

        // Remove entries for lines that are no longer active
        $this->lastCumulativeValues = array_intersect_key(
            $this->lastCumulativeValues,
            array_flip($activeLines)
        );

        // Force garbage collection
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
            $this->deviceStats[$deviceName] = [
                'success_count' => 0,
                'error_count' => 0,
                'last_success' => null,
                'last_error' => null,
            ];
        }

        if ($success) {
            $this->deviceStats[$deviceName]['success_count']++;
            $this->deviceStats[$deviceName]['last_success'] = now();
        } else {
            $this->deviceStats[$deviceName]['error_count']++;
            $this->deviceStats[$deviceName]['last_error'] = now();
        }

        // Display periodic stats every 100 cycles in verbose mode
        if ($this->option('v') && $this->pollCycleCount % 100 === 0) {
            $stats = $this->deviceStats[$deviceName];
            $total = $stats['success_count'] + $stats['error_count'];
            $successRate = $total > 0 ? round(($stats['success_count'] / $total) * 100, 1) : 0;

            $this->comment("Device {$deviceName} stats: {$successRate}% success rate ({$stats['success_count']}/{$total})");
        }
    }

    // push to HMI
    private function pushToHmi(InsBpmDevice $device, array $values)
    {
        if (empty($values)) {
            return false;
        }

        $unit_id = 1; // Standard Modbus unit ID
        
        try {
            $valToSend = [
                Types::toRegister($values["M1_Hot"] ?? 0),
                Types::toRegister($values["M1_Cold"] ?? 0),
                Types::toRegister($values["M2_Hot"] ?? 0),
                Types::toRegister($values["M2_Cold"] ?? 0),
                Types::toRegister($values["M3_Hot"] ?? 0),
                Types::toRegister($values["M3_Cold"] ?? 0),
                Types::toRegister($values["M4_Hot"] ?? 0),
                Types::toRegister($values["M4_Cold"] ?? 0)
            ];

            // Step 3: Update values based on what changed
            $counterss = [];
            foreach ($values as $machineKey => $counter) {
                $valueIndex = array_search($machineKey, array_keys($this->addressWrite));
                if ($valueIndex !== false) {
                    $valToSend[$valueIndex] = Types::toRegister($counter ?? 0);
                    if ($this->option('d')) {
                        $this->line("      Updated {$machineKey} = " . ($counter ?? 0));
                    }
                }
            }


            // Step 4: Write all counters to HMI in one operation
            $connection = BinaryStreamConnection::getBuilder()
                ->setHost($device->ip_address)
                ->setPort($this->modbusPort)
                ->build();
            
            $packet = new WriteMultipleRegistersRequest(
                10, // Starting address
                $valToSend, // Array of 8 values
                $unit_id
            );
            
            $connection->connect();
            $connection->send($packet);
            $connection->close();

            if ($this->option('d')) {
                $this->line("    ✓ Wrote " . count($valToSend) . " counter(s) to HMI in single operation");
            }

            return true;

        } catch (\Exception $e) {
            $this->error("    ✗ Error writing to HMI {$device->ip_address}: " . $e->getMessage());
            return false;
        }
    }
}
