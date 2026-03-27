<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\WriteMultipleRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleCoilRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleRegisterRequest;
use ModbusTcpClient\Packet\ResponseFactory;
use App\Models\InsIbmsDevice;
use App\Models\InsIbmsCount;
use ModbusTcpClient\Utils\Types;

class InsIbmsPoll extends Command
{
    // Configuration variables
    protected $pollIntervalSeconds = 1;
    protected $modbusTimeoutSeconds = 2;
    protected $modbusPort = 503;
    public $addressWrite  = [
        // Read Input Register (04)
        'duration_m_1_2'       => 6,
        'duration_m_3_4'       => 7,
        'duration_m_5_6'       => 8,
        'total_batch'          => 210,
        'standard_batch'       => 211,
        'not_standard_batch'   => 212,
        'average_batch'        => 213,
        'std_timer_all'        => 121,
    ];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-ibms-poll {--v : Verbose output} {--d : Debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll IBMS log data from Modbus servers and track incremental counts';

    // In-memory buffer to track last cumulative values per line and condition
    protected  $lastCumulativeValues = []; 
    protected  $lastReadingDates     = []; 
    private    $lastDurationValues   = [];
    private    $lastSentDurationValues = []; // Track last sent duration per line
    private    $durationWasCounting  = [];
    public int $saveDuration = 0;

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
        $devices = InsIbmsDevice::active()->get();
        if ($devices->isEmpty()) {
            $this->error('✗ No active IBMS devices found');
            return 1;
        }
        
        $this->info('✓ InsIbmsPoll started - monitoring ' . count($devices) . ' devices');
        if ($this->option('v')) {
            $this->comment('Devices:');
            foreach ($devices as $device) {
                $this->comment("  → {$device->name} ({$device->ip_address})");
            }
        }

        // Continuous polling loop for real-time monitoring
        while (true) {
            $cycleStartTime = microtime(true);
            $cycleReadings = 0;
            $cycleErrors = 0;

            foreach ($devices as $device) {
                $config = $device->config ?? [];
                // add ip addres to config
                $config['ip_address'] = $device->ip_address;
                if ($this->option('v')) {
                    $this->comment("→ Polling {$device->name} ({$device->ip_address})");
                }

                try {
                    $readings = $this->pollDevice($config);
                    $cycleReadings += $readings;
                    
                    if ($readings > 0) {
                        $this->info("✓ Polling {$device->name} completed - {$readings} new readings saved");
                    } else {
                        if ($this->option('d')) {
                            $this->info("→ Polling {$device->name} completed - no new readings");
                        }
                    }
                    
                    $this->updateDeviceStats($device->name, true);
                } catch (\Throwable $th) {
                    $this->error("✗ Error polling {$device->name}: " . $th->getMessage());
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

            // Wait before next poll cycle
            sleep($this->pollIntervalSeconds);
            $this->pollCycleCount++;

            // Periodic memory cleanup
            if ($this->pollCycleCount % $this->memoryCleanupInterval === 0) {
                $this->cleanupMemory();
            }
        }
    }

    private function pollDevice(array $config)
    {
        $readingsCount = 0;

        if (!isset($config['list_machine']) || !is_array($config['list_machine'])) {
            if ($this->option('d')) {
                $this->error('✗ Invalid IBMS device config: list_machine is missing');
            }

            return 0;
        }

        $deviceKey = $config['ip_address'] ?? ($config['name'] ?? 'unknown_device');
        
        try {
            $batchCount = $this->readBatchCount($config);

            foreach ($config['list_machine'] as $machine) {
                $machineName = $machine['name'] ?? 'unknown_machine';
                $machineKey = $deviceKey . '|' . $machineName;

                if (!isset($machine['addr_timer'])) {
                    if ($this->option('d')) {
                        $this->error("✗ Missing addr_timer for machine {$machineName}");
                    }
                    continue;
                }

                $duration = (int) $this->readInputFunction($config, $machine["addr_timer"], "duration");

                if (isset($machine['addr_std_timer']) || isset($config['addr_std_timer'])) {
                    $stdAddr = $machine['addr_std_timer'] ?? $config['addr_std_timer'];
                    $stdDurationRaw = $this->readHoldingFunction($config, $stdAddr, 'standard_duration');
                } else {
                    $stdDurationRaw = 100;
                }

                $stdDuration = $this->hmiToTotalMinutes($stdDurationRaw) / 60;
                $previousDuration = $this->lastDurationValues[$machineKey] ?? null;

                $isDurationCounting = $previousDuration !== null && $duration > $previousDuration;
                $isDurationStopped = $previousDuration !== null && $duration === $previousDuration;
                $wasCountingBefore = $this->durationWasCounting[$machineKey] ?? false;

                if ($isDurationCounting) {
                    $this->durationWasCounting[$machineKey] = true;
                } elseif ($previousDuration !== null && $duration < $previousDuration) {
                    $this->durationWasCounting[$machineKey] = false;
                }

                if ($isDurationStopped && $wasCountingBefore) {
                    $this->saveBatchCount($config, $machine, $duration, $batchCount, $stdDuration);
                    $readingsCount++;
                    $this->durationWasCounting[$machineKey] = false;

                    if ($this->option('d')) {
                        $this->info("✓ Saved batch for {$machineName} (batch: {$batchCount}, duration: {$duration})");
                    }
                }

                $this->lastDurationValues[$machineKey] = $duration;
            }
        } catch (\Throwable $th) {
            $this->error("✗ Error during polling: " . $th->getMessage());
            return 0;
        }

        return $readingsCount;
    }

    private function saveBatchCount(array $config, array $machine, int $durationRaw, ?int $batchCount, float $stdDuration): void
    {
        $durationSeconds = $this->normalizeDurationToSeconds($durationRaw, $machine, $config);
        $durationTime = $this->formatSecondsToTime($durationSeconds);
        $status = $this->resolveBatchStatus($durationSeconds, $machine, $config, $stdDuration);
        $shift = $machine['shift'] ?? ($config['shift'] ?? data_get($config, 'plant'));

        $data = [
            'name' => (string) ($machine['name'] ?? 'unknown_machine'),
            'status' => $status,
            'duration_raw' => $durationRaw,
            'duration_seconds' => $durationSeconds,
            'ip_address' => $config['ip_address'] ?? null,
        ];

        if ($batchCount !== null) {
            $data['batch_count'] = $batchCount;
        }

        InsIbmsCount::create([
            'shift' => $shift ? (string) $shift : "A",
            'duration' => $durationTime,
            'data' => $data,
        ]);
    }

    private function readBatchCount(array $config): ?int
    {
        if (!isset($config['addr_total_batch'])) {
            return null;
        }

        try {
            return (int) $this->readHoldingFunction($config, $config['addr_total_batch'], 'batch_count');
        } catch (\Throwable $th) {
            if ($this->option('d')) {
                $this->warn('⚠ Unable to read batch count: ' . $th->getMessage());
            }

            return null;
        }
    }

    private function normalizeDurationToSeconds(int $durationRaw, array $machine, array $config): int
    {
        $durationUnit = strtolower((string) ($machine['duration_unit'] ?? $config['duration_unit'] ?? 'seconds'));

        return match ($durationUnit) {
            'minute', 'minutes', 'min' => $durationRaw * 60,
            'hour', 'hours', 'hr' => $durationRaw * 3600,
            default => $durationRaw,
        };
    }

    private function hmiToTotalMinutes($rawData) {
        // Get the hours (everything before the last two digits)
        $hours = floor($rawData / 100);
        
        // Get the minutes (the last two digits)
        $minutes = $rawData % 100;
        
        // Return the total sum in minutes
        return ($hours * 60) + $minutes;
    }

    private function formatSecondsToTime(int $durationSeconds): string
    {
        $safeSeconds = max(0, $durationSeconds);
        $hours = intdiv($safeSeconds, 3600);
        $minutes = intdiv($safeSeconds % 3600, 60);
        $seconds = $safeSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    private function resolveBatchStatus(int $durationSeconds, array $machine, array $config, float $stdDuration): string
    {
        $standardMinutes = $stdDuration > 0 ? $stdDuration : 15.0;
        $durationMinutes = $durationSeconds / 60;

        if ($durationMinutes < $standardMinutes) {
            return 'too_early';
        }

        if (abs($durationMinutes - $standardMinutes) < (1 / 60)) {
            return 'on_time';
        }

        return 'too_late';
    }
    
    // READ INPUT FUNCTION
    private function readInputFunction(array $config, $address, $name)
    {
        $unit_id = 1;
        $request = ReadRegistersBuilder::newReadInputRegisters('tcp://'.$config['ip_address'].':503', $unit_id)
            ->int16($address, $name)
            ->build();
        $response = (new NonBlockingClient(['readTimeoutSec' => $this->modbusTimeoutSeconds]))
            ->sendRequests($request)->getData();
        return $response[$name];
    }

    private function readHoldingFunction(array $config, $address, $name)
    {
        $unit_id = 1;
        $request = ReadRegistersBuilder::newReadHoldingRegisters('tcp://'.$config['ip_address'].':503', $unit_id)
            ->int16($address, $name)
            ->build();
        $response = (new NonBlockingClient(['readTimeoutSec' => $this->modbusTimeoutSeconds]))
            ->sendRequests($request)->getData();
        return $response[$name];
    }

    /**
     * Clean up memory by removing old entries and forcing garbage collection
     */
    public function cleanupMemory()
    {
        $activeDeviceKeys = InsIbmsDevice::active()
            ->get()
            ->map(function ($device) {
                return $device->ip_address ?: $device->name;
            })
            ->filter()
            ->values()
            ->toArray();

        $this->lastDurationValues = array_filter(
            $this->lastDurationValues,
            function ($value, $key) use ($activeDeviceKeys) {
                foreach ($activeDeviceKeys as $deviceKey) {
                    if (str_starts_with($key, $deviceKey . '|')) {
                        return true;
                    }
                }

                return false;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $this->durationWasCounting = array_filter(
            $this->durationWasCounting,
            function ($value, $key) use ($activeDeviceKeys) {
                foreach ($activeDeviceKeys as $deviceKey) {
                    if (str_starts_with($key, $deviceKey . '|')) {
                        return true;
                    }
                }

                return false;
            },
            ARRAY_FILTER_USE_BOTH
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
