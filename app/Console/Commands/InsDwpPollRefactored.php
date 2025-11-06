<?php

namespace App\Console\Commands;

use App\Models\InsDwpDevice;
use App\Services\DWP\CycleStateMachine;
use App\Services\DWP\DwpDataService;
use App\Services\DWP\DwpPollingConfig;
use App\Services\DWP\ModbusService;
use App\Services\DWP\WaveformNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InsDwpPollRefactored extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-dwp-poll-refactored {--v : Verbose output} {--d : Debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refactored DWP (Deep-Well Press) counter polling with improved architecture';

    private ModbusService $modbusService;
    private CycleStateMachine $stateMachine;
    private DwpDataService $dataService;
    private DwpPollingConfig $config;

    // Statistics tracking
    private array $deviceStats = [];
    private int $totalReadings = 0;
    private int $totalErrors = 0;
    private int $totalCyclesSaved = 0;
    private int $pollCycleCount = 0;

    public function __construct(
        ModbusService $modbusService = null,
        CycleStateMachine $stateMachine = null,
        DwpDataService $dataService = null,
        DwpPollingConfig $config = null
    ) {
        parent::__construct();

        $this->config = $config ?? new DwpPollingConfig();
        $this->modbusService = $modbusService ?? new ModbusService($this->config);
        $this->stateMachine = $stateMachine ?? new CycleStateMachine($this->config);
        $this->dataService = $dataService ?? new DwpDataService(new WaveformNormalizer());
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $devices = $this->getActiveDevices();
            if ($devices->isEmpty()) {
                $this->error('✗ No active DWP devices found');
                return 1;
            }

            $this->displayStartupInfo($devices);
            $this->validateDeviceConfigurations($devices);
            $this->initializeServices($devices);

            $this->info('✓ Starting polling loop...');
            $this->runPollingLoop($devices);

        } catch (\Exception $e) {
            $this->error('✗ Fatal error in polling command: ' . $e->getMessage());
            Log::error('InsDwpPollRefactored fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Get active devices from database
     */
    private function getActiveDevices()
    {
        return InsDwpDevice::active()->get();
    }

    /**
     * Display startup information
     */
    private function displayStartupInfo($devices): void
    {
        $this->info('✓ InsDwpPoll (Refactored) started - monitoring ' . count($devices) . ' devices');

        if ($this->option('v')) {
            $this->comment('Configuration:');
            $this->comment('  → Poll interval: ' . DwpPollingConfig::POLL_INTERVAL_SECONDS . 's');
            $this->comment('  → Modbus timeout: ' . DwpPollingConfig::MODBUS_TIMEOUT_SECONDS . 's');
            $this->comment('  → Cycle timeout: ' . DwpPollingConfig::CYCLE_TIMEOUT_SECONDS . 's');
            $this->comment('  → Memory cleanup interval: ' . DwpPollingConfig::MEMORY_CLEANUP_INTERVAL . ' cycles');

            $this->comment('Devices:');
            foreach ($devices as $device) {
                $lines = implode(', ', $device->getLines());
                $stats = $this->modbusService->getDeviceStats($device);
                $this->comment("  → {$device->name} ({$device->ip_address}) - Lines: {$lines} - Machines: {$stats['machines_count']}");
            }
        }
    }

    /**
     * Validate device configurations
     */
    private function validateDeviceConfigurations($devices): void
    {
        foreach ($devices as $device) {
            $errors = $this->modbusService->validateDeviceConfig($device);
            if (!empty($errors)) {
                $this->warn("Configuration issues for device {$device->name}:");
                foreach ($errors as $error) {
                    $this->warn("  → {$error}");
                }
            }
        }
    }

    /**
     * Initialize services with device data
     */
    private function initializeServices($devices): void
    {
        $this->dataService->initializeLastValues($devices);

        if ($this->option('d')) {
            foreach ($devices as $device) {
                foreach ($device->getLines() as $line) {
                    $lastValue = $this->dataService->getLastCumulativeValue($line);
                    $this->line("Initialized line {$line} with last cumulative: {$lastValue}");
                }
            }
        }

        // Initialize device statistics
        foreach ($devices as $device) {
            $this->deviceStats[$device->name] = [
                'success_count' => 0,
                'error_count' => 0,
                'last_success' => null,
                'last_error' => null,
                'cycles_saved' => 0,
            ];
        }
    }

    /**
     * Main polling loop
     */
    private function runPollingLoop($devices): void
    {
        while (true) {
            $cycleStartTime = microtime(true);
            $cycleStats = $this->processPollCycle($devices);

            $this->updateOverallStats($cycleStats);
            $this->displayCycleInfo($cycleStats, $cycleStartTime);
            $this->performMaintenanceTasks($devices);

            sleep(DwpPollingConfig::POLL_INTERVAL_SECONDS);
            $this->pollCycleCount++;
        }
    }

    /**
     * Process one complete polling cycle
     */
    private function processPollCycle($devices): array
    {
        $stats = [
            'readings' => 0,
            'errors' => 0,
            'cycles_saved' => 0,
            'failed_devices' => [],
        ];

        foreach ($devices as $device) {
            try {
                if ($this->option('v')) {
                    $this->comment("→ Polling {$device->name} ({$device->ip_address})");
                }

                $deviceStats = $this->pollSingleDevice($device);
                $stats['readings'] += $deviceStats['readings'];
                $stats['cycles_saved'] += $deviceStats['cycles_saved'];

                $this->updateDeviceStats($device->name, true, $deviceStats['cycles_saved']);

            } catch (\Exception $e) {
                $this->error("✗ Error polling {$device->name} ({$device->ip_address}): " . $e->getMessage());
                $stats['errors']++;
                $stats['failed_devices'][] = $device->name;
                $this->updateDeviceStats($device->name, false);

                Log::error('Device polling failed', [
                    'device' => $device->name,
                    'ip_address' => $device->ip_address,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Poll a single device and process all its readings
     */
    private function pollSingleDevice($device): array
    {
        $readings = $this->modbusService->pollDevice($device);
        $cyclesSaved = 0;
        $successfulReadings = 0;

        foreach ($readings as $reading) {
            if ($reading->successful) {
                $successfulReadings++;

                if ($this->option('d')) {
                    $this->line("Reading from {$reading->line}-{$reading->machineName}: {$reading->getSummary()}");
                }

                // Process reading through state machine for each position
                foreach (['L', 'R'] as $position) {
                    $completedCycle = $this->stateMachine->processReading($reading);

                    if ($completedCycle && $completedCycle->position === $position) {
                        if ($this->dataService->saveCycle($completedCycle)) {
                            $cyclesSaved++;

                            if ($this->option('v')) {
                                $peaks = $completedCycle->getPeaks();
                                $this->line("✓ Saved cycle for {$completedCycle->getCycleKey()}. " .
                                          "TH peak: {$peaks['toe_heel']}, Side peak: {$peaks['side']}. " .
                                          "Total: {$this->dataService->getLastCumulativeValue($completedCycle->line)}");
                            }
                        }
                    }
                }
            } else {
                if ($this->option('d')) {
                    $this->warn("Failed reading from {$reading->line}-{$reading->machineName}: {$reading->error}");
                }
            }
        }

        return [
            'readings' => $successfulReadings,
            'cycles_saved' => $cyclesSaved,
            'total_readings' => $readings->count(),
        ];
    }

    /**
     * Update overall statistics
     */
    private function updateOverallStats(array $cycleStats): void
    {
        $this->totalReadings += $cycleStats['readings'];
        $this->totalErrors += $cycleStats['errors'];
        $this->totalCyclesSaved += $cycleStats['cycles_saved'];
    }

    /**
     * Display cycle information
     */
    private function displayCycleInfo(array $cycleStats, float $cycleStartTime): void
    {
        if ($this->option('v') && ($cycleStats['readings'] > 0 || $cycleStats['errors'] > 0 || $cycleStats['cycles_saved'] > 0)) {
            $cycleTime = microtime(true) - $cycleStartTime;
            $this->info("Cycle #{$this->pollCycleCount}: " .
                       "{$cycleStats['readings']} readings, " .
                       "{$cycleStats['cycles_saved']} cycles saved, " .
                       "{$cycleStats['errors']} errors, " .
                       number_format($cycleTime * 1000, 2) . "ms");
        }

        // Display periodic summary
        if ($this->option('v') && $this->pollCycleCount > 0 && $this->pollCycleCount % DwpPollingConfig::STATS_DISPLAY_INTERVAL === 0) {
            $this->displayPeriodicSummary();
        }
    }

    /**
     * Display periodic summary statistics
     */
    private function displayPeriodicSummary(): void
    {
        $this->comment("=== Summary (Cycle #{$this->pollCycleCount}) ===");
        $this->comment("Total readings: {$this->totalReadings}");
        $this->comment("Total cycles saved: {$this->totalCyclesSaved}");
        $this->comment("Total errors: {$this->totalErrors}");

        foreach ($this->deviceStats as $deviceName => $stats) {
            $total = $stats['success_count'] + $stats['error_count'];
            $successRate = $total > 0 ? round(($stats['success_count'] / $total) * 100, 1) : 0;
            $this->comment("Device {$deviceName}: {$successRate}% success rate ({$stats['cycles_saved']} cycles saved)");
        }

        // Display memory usage
        $memoryUsage = memory_get_usage(true);
        $this->comment("Memory usage: " . number_format($memoryUsage / 1024 / 1024, 2) . " MB");
    }

    /**
     * Perform maintenance tasks
     */
    private function performMaintenanceTasks($devices): void
    {
        if ($this->pollCycleCount % DwpPollingConfig::MEMORY_CLEANUP_INTERVAL === 0) {
            $this->performCleanup($devices);
        }
    }

    /**
     * Perform cleanup tasks
     */
    private function performCleanup($devices): void
    {
        // Clean up data service
        $this->dataService->cleanup($devices);

        // Clean up state machine
        $activeCycleKeys = $this->modbusService->extractActiveCycleKeys($devices);
        $this->stateMachine->cleanup($activeCycleKeys);

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        if ($this->option('d')) {
            $memoryUsage = memory_get_usage(true);
            $this->line("Memory cleanup performed. Current usage: " .
                       number_format($memoryUsage / 1024 / 1024, 2) . " MB");
        }

        Log::info('Maintenance cleanup completed', [
            'cycle' => $this->pollCycleCount,
            'active_cycle_keys' => count($activeCycleKeys),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);
    }

    /**
     * Update device statistics
     */
    private function updateDeviceStats(string $deviceName, bool $success, int $cyclesSaved = 0): void
    {
        if (!isset($this->deviceStats[$deviceName])) {
            $this->deviceStats[$deviceName] = [
                'success_count' => 0,
                'error_count' => 0,
                'last_success' => null,
                'last_error' => null,
                'cycles_saved' => 0,
            ];
        }

        if ($success) {
            $this->deviceStats[$deviceName]['success_count']++;
            $this->deviceStats[$deviceName]['last_success'] = now();
            $this->deviceStats[$deviceName]['cycles_saved'] += $cyclesSaved;
        } else {
            $this->deviceStats[$deviceName]['error_count']++;
            $this->deviceStats[$deviceName]['last_error'] = now();
        }
    }

    /**
     * Get overall statistics (for external monitoring)
     */
    public function getStatistics(): array
    {
        return [
            'poll_cycle_count' => $this->pollCycleCount,
            'total_readings' => $this->totalReadings,
            'total_errors' => $this->totalErrors,
            'total_cycles_saved' => $this->totalCyclesSaved,
            'device_stats' => $this->deviceStats,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'data_service_stats' => $this->dataService->getOverallStats(),
        ];
    }
}
