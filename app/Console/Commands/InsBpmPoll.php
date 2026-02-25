<?php

namespace App\Console\Commands;

use App\Models\InsBpmDevice;
use App\Models\InsBpmCount;
use App\Models\UptimeLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Composer\Write\WriteCoilsBuilder;
use ModbusTcpClient\Network\NonBlockingClient;

class InsBpmPoll extends Command
{
    // Configuration variables
    private const POLL_INTERVAL_SECONDS = 1;
    private const MODBUS_TIMEOUT_SECONDS = 2;
    private const MODBUS_PORT = 503;
    private const MODBUS_UNIT_ID = 1;
    private const RESET_FLAG_CACHE_TTL_DAYS = 2;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-bpm-poll {--v : Verbose output} {--d : Debug output} {--dry-test : Run simulation without reset write and DB insert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll BPM (Back part mold emergency stop) counter data from Modbus servers and track incremental counts';

    /**
     * Read a Modbus counter value from the device
     */
    private function readModbusCounter(string $ipAddress, int $address, string $counterName): int
    {
        $request = ReadRegistersBuilder::newReadInputRegisters(
            "tcp://{$ipAddress}:" . self::MODBUS_PORT,
            self::MODBUS_UNIT_ID
        )
        ->int16($address, $counterName)
        ->build();
        
        $response = (new NonBlockingClient(['readTimeoutSec' => self::MODBUS_TIMEOUT_SECONDS]))
            ->sendRequests($request)
            ->getData();
        
        return abs($response[$counterName]);
    }

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
        if ($this->isDryTest()) {
            $this->warn('⚑ DRY TEST mode enabled: reset and DB writes are simulated');
        }
        if ($this->option('v')) {
            $this->comment('Devices:');
            foreach ($devices as $device) {
                $lines = implode(', ', $device->getLines());
                $this->comment("  → {$device->name} ({$device->ip_address}) - Lines: {$lines}");
            }
        }

        // For each device, poll once for testing
        foreach ($devices as $device) {
            if ($this->option('v')) {
                $this->comment("→ Polling {$device->name} ({$device->ip_address})");
            }
            try {
                $this->handleFirstOnlineDailyReset($device);
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

    private function pollDevice(InsBpmDevice $device): int
    {
        $readingsCount = 0;
        foreach ($device->config['list_mechine'] as $machineConfig) {
            try {
                $readings = $this->pollMachine($device, $machineConfig);
                $readingsCount++;
            } catch (\Exception $e) {
                $this->error("    ✗ Error reading machine {$machineConfig['name']}: {$e->getMessage()} at line {$e->getLine()}");
            }
        }

        return $readingsCount;
    }

    /**
     * Reset panel once per day when device is online for the first time today.
     */
    private function handleFirstOnlineDailyReset(InsBpmDevice $device): void
    {
        $resetAddr = $device->config['addr_reset'] ?? null;
        if ($resetAddr === null) {
            $this->debugLog("  → Skip reset {$device->name}: no addr_reset in config");
            return;
        }

        $latestLog = UptimeLog::where('ip_address', $device->ip_address)
            ->latest('checked_at')
            ->first();

        if (!$latestLog || $latestLog->status !== 'online') {
            $this->debugLog("  → Skip reset {$device->name}: latest uptime status is not online");
            return;
        }

        $firstOnlineToday = UptimeLog::where('ip_address', $device->ip_address)
            ->where('status', 'online')
            ->whereDate('checked_at', Carbon::today())
            ->orderBy('checked_at')
            ->first();

        if (!$firstOnlineToday) {
            $this->debugLog("  → Skip reset {$device->name}: no online log found for today");
            return;
        }

        $cacheKey = $this->dailyResetCacheKey($device);
        if (Cache::has($cacheKey)) {
            $this->debugLog("  → Skip reset {$device->name}: already reset for first online today");
            return;
        }

        if ($this->isDryTest()) {
            $this->info("⚑ [DRY TEST] Reset would be sent to {$device->name} (first online today)");
            return;
        }

        $request = WriteCoilsBuilder::newWriteMultipleCoils(
            "tcp://{$device->ip_address}:" . self::MODBUS_PORT,
            self::MODBUS_UNIT_ID,
            1
        )
        ->coil((int) $resetAddr, 1)
        ->build();

        (new NonBlockingClient(['readTimeoutSec' => self::MODBUS_TIMEOUT_SECONDS]))
            ->sendRequests($request);

        Cache::put($cacheKey, true, now()->addDays(self::RESET_FLAG_CACHE_TTL_DAYS));
        $this->info("✓ Reset sent to {$device->name} (first online today)");
    }

    private function dailyResetCacheKey(InsBpmDevice $device): string
    {
        $date = Carbon::today()->format('Ymd');
        return "ins_bpm_first_online_reset_{$device->ip_address}_{$date}";
    }

    private function isDryTest(): bool
    {
        return (bool) $this->option('dry-test');
    }

    /**
     * Poll a single machine for Hot and Cold counter values
     */
    private function pollMachine(InsBpmDevice $device, array $machineConfig): void
    {
        $machineName = $machineConfig['name'];
        $hotAddress  = $machineConfig['addr_hot'];
        $coldAddress = $machineConfig['addr_cold'];
        $line = $machineConfig['line'] ?? $device->line;
        $this->debugLog("  Polling machine {$machineName} at addresses hot: {$hotAddress}, cold: {$coldAddress}");
        
        // Read counter values from Modbus
        $counterHot = $this->readModbusCounter($device->ip_address, $hotAddress, 'counter_hot');
        $counterCold = $this->readModbusCounter($device->ip_address, $coldAddress, 'counter_cold');
        $this->debugLog("    Read values - Hot: {$counterHot}, Cold: {$counterCold}");
        // Process and save to database
        $this->processCondition($device, $line, $machineName, 'Hot', $counterHot);
        $this->processCondition($device, $line, $machineName, 'Cold', $counterCold);
    }

    /**
     * Normalize line name for consistent storage
     */
    private function normalizeName(string $name): string
    {
        return strtoupper(trim($name));
    }

    /**
     * Log debug message if debug mode is enabled
     */
    private function debugLog(string $message): void
    {
        if ($this->option('d')) {
            $this->line($message);
        }
    }

    /**
     * Get the latest counter record from database for today
     */
    private function getLatestRecord(InsBpmDevice $device, string $line, string $machineName, string $condition): ?InsBpmCount
    {
        return InsBpmCount::where('plant', $device->name)
            ->where('line', $this->normalizeName($line))
            ->where('machine', $this->normalizeName($machineName))
            ->where('condition', $condition)
            ->whereDate('created_at', Carbon::today())
            ->latest('created_at')
            ->first();
    }

    /**
     * Save counter reading to database
     */
    private function saveCounterReading(
        InsBpmDevice $device,
        string $line,
        string $machineName,
        string $condition,
        int $incremental,
        int $cumulative
    ): void {
        InsBpmCount::create([
            'plant' => $device->name,
            'line' => $line,
            'machine' => $machineName,
            'condition' => $condition,
            'incremental' => $incremental,
            'cumulative' => $cumulative,
        ]);
    }

    /**
     * Process a single condition (Hot or Cold) and save if changed
     */
    private function processCondition(InsBpmDevice $device, $line, string $machineName, string $condition, int $currentCumulative): int
    {
        $key = "{$machineName}_{$condition}";
        
        // Get latest record from database to compare
        $latestRecord = $this->getLatestRecord($device, $line, $machineName, $condition);
        
        // If cumulative value from HMI is same as latest DB record, don't save
        if ($latestRecord && (int)$latestRecord->cumulative === (int)$currentCumulative) {
            $this->debugLog("    → No change for {$key} - DB cumulative {$latestRecord->cumulative} = HMI {$currentCumulative} - skipping save");
            return 0;
        }
        
        // Calculate increment based on previous value
        $previousCumulative = $latestRecord ? $latestRecord->cumulative : 0;
        $increment = $currentCumulative - $previousCumulative;
        
        // Save if: increment is positive OR this is the very first record (initialize with 0 or any value)
        if ($increment > 0 || !$latestRecord) {
            $incremental = ($increment > 0) ? $increment : 0; // First record gets incremental = 0 for initialization

            if ($this->isDryTest()) {
                $this->info("⚑ [DRY TEST] {$device->name} {$line} {$machineName} {$condition} would save: inc {$incremental}, cum {$currentCumulative}");
                return 1;
            }

            $this->saveCounterReading($device, $line, $machineName, $condition, $incremental, $currentCumulative);
            $this->debugLog("    ✓ Saved {$condition}: increment {$incremental}, cumulative {$currentCumulative}");
            
            return 1;
        }
        
        // Handle negative or zero increment (counter reset or decrease)
        $this->debugLog("    ⚠ Negative or zero increment ({$increment}) for {$key} - skipping save");
        
        return 0;
    }

}
