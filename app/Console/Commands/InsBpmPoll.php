<?php

namespace App\Console\Commands;

use App\Models\InsBpmDevice;
use App\Models\InsBpmCount;
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

class InsBpmPoll extends Command
{
    // Configuration variables
    protected $pollIntervalSeconds = 1;
    protected $modbusTimeoutSeconds = 2;
    protected $modbusPort = 503;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-bpm-poll {--v : Verbose output} {--d : Debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll BPM (Deep-Well Alarm Constraint Time) counter data from Modbus servers and track incremental counts';

    // In-memory buffer to track last cumulative values per line and condition
    protected  $lastCumulativeValues = []; // Format: ['M1_Hot' => 123, 'M1_Cold' => 456]
    private    $lastDurationValues = [];
    private    $lastSentDurationValues = []; // Track last sent duration per line
    public int $saveDuration = 0;

    // Reset time configuration
    protected $resetHour = 7; // Reset long duration at 7:00 AM
    protected $lastResetDate = null; // Track when last reset was performed

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
        // Initialize last cumulative values from database
        $this->initializeLastValues($devices);

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
     * Initialize last cumulative values from database
     */
    private function initializeLastValues($devices)
    {
        foreach ($devices as $device) {
            if (!isset($device->config['list_machine'])) {
                continue;
            }
            
            foreach ($device->config['list_machine'] as $machineConfig) {
                $machineName = $machineConfig['name'];
                
                // Initialize for Hot condition
                $lastHot = InsBpmCount::where('machine', strtoupper($machineName))
                    ->where('condition', 'Hot')
                    ->latest('created_at')
                    ->first();
                    
                $key = $machineName . '_Hot';
                if ($lastHot) {
                    $this->lastCumulativeValues[$key] = $lastHot->cumulative;
                    if ($this->option('d')) {
                        $this->line("Initialized {$key} with last cumulative: {$lastHot->cumulative}");
                    }
                } else {
                    $this->lastCumulativeValues[$key] = null;
                    if ($this->option('d')) {
                        $this->line("{$key} has no previous data - will skip first reading");
                    }
                }
                
                // Initialize for Cold condition
                $lastCold = InsBpmCount::where('machine', strtoupper($machineName))
                    ->where('condition', 'Cold')
                    ->latest('created_at')
                    ->first();
                    
                $key = $machineName . '_Cold';
                if ($lastCold) {
                    $this->lastCumulativeValues[$key] = $lastCold->cumulative;
                    if ($this->option('d')) {
                        $this->line("Initialized {$key} with last cumulative: {$lastCold->cumulative}");
                    }
                } else {
                    $this->lastCumulativeValues[$key] = null;
                    if ($this->option('d')) {
                        $this->line("{$key} has no previous data - will skip first reading");
                    }
                }
            }
        }
    }

    private function pollDevice(InsBpmDevice $device)
    {
        $unit_id = 1; // Standard Modbus unit ID
        $readingsCount = 0;
        foreach ($device->config['list_machine'] as $machineConfig) {
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
                        ->int16($machineConfig['addr_hot'], 'counter_hot') // Counter value at hot
                        ->build();
                // Execute Modbus request
                $responseConditionHot = (new NonBlockingClient(['readTimeoutSec' => $this->modbusTimeoutSeconds]))
                    ->sendRequests($requestConditionHot)->getData();
                $counterHot = abs($responseConditionHot['counter_hot']); // Make absolute to ensure positive values

                // REQUEST DATA COUNTER COLD
                $requestConditionCold = ReadRegistersBuilder::newReadInputRegisters(
                    'tcp://' . $device->ip_address . ':' . $this->modbusPort,
                    $unit_id)
                    ->int16($machineConfig['addr_cold'], 'counter_cold') // Counter value at cold
                    ->build();
                // Execute Modbus request
                $responseConditionCold = (new NonBlockingClient(['readTimeoutSec' => $this->modbusTimeoutSeconds]))
                    ->sendRequests($requestConditionCold)->getData();
                $counterCold = abs($responseConditionCold['counter_cold']); // Make absolute to ensure positive values
                
                if ($this->option('d')) {
                    $this->line("    Read values - Hot: {$counterHot}, Cold: {$counterCold}");
                }
                
                // Process HOT condition
                $readingsCount += $this->processCondition(
                    $device,
                    $line,
                    $machineName,
                    'Hot',
                    $counterHot
                );
                
                // Process COLD condition
                $readingsCount += $this->processCondition(
                    $device,
                    $line,
                    $machineName,
                    'Cold',
                    $counterCold
                );
                
            } catch (\Exception $e) {
                $this->error("    ✗ Error reading machine {$machineName}: " . $e->getMessage() . " at line " . $e->getLine());
                continue;
            }
        }

        return $readingsCount;
    }
    
    /**
     * Process a single condition (Hot or Cold) and save if changed
     */
    private function processCondition(InsBpmDevice $device, $line, string $machineName, string $condition, int $currentCumulative): int
    {
        $key = $machineName . '_' . $condition;
        
        // Step 1: Check if this is the first reading
        if (!isset($this->lastCumulativeValues[$key]) || $this->lastCumulativeValues[$key] === null) {
            // Step 2: First reading - initialize with current value
            $this->lastCumulativeValues[$key] = $currentCumulative;
            if ($this->option('d')) {
                $this->line("    ✓ Initial reading for {$key} - initialized with {$currentCumulative}");
            }
            return 0;
        }

        // Step 3: Check if value is the same as last reading
        if ($currentCumulative === $this->lastCumulativeValues[$key]) {
            if ($this->option('d')) {
                $this->line("    → No change for {$key} (still {$currentCumulative}) - skipping save");
            }
            return 0;
        }

        // Step 4: Value is different - calculate increment
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
}
