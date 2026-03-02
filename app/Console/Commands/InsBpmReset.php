<?php

namespace App\Console\Commands;

use App\Models\InsBpmDevice;
use Illuminate\Console\Command;
use ModbusTcpClient\Composer\Write\WriteRegistersBuilder;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Composer\Write\WriteCoilsBuilder;
use ModbusTcpClient\Packet\ModbusFunction\WriteMultipleRegistersRequest;
use ModbusTcpClient\Utils\Types;
use App\Models\InsBpmCount;

class InsBpmReset extends Command
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 500;
    private const MOMENTARY_PULSE_MS = 200;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-bpm-reset {--v : Verbose output} {--d : Debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reset pulse to all BPM devices (writes 1 then 0 to reset addresses)';

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

        $this->info('✓ InsBpmReset started - resetting ' . count($devices) . ' devices');
        $successCount = 0;
        $errorCount = 0;

        foreach ($devices as $device) {
            if ($this->option('v')) {
                $this->comment("→ Resetting {$device->name} ({$device->ip_address})");
            }

            try {
                $this->resetDevice($device);
                $this->deleteTodayBpmCounts();
                $successCount++;
            } catch (\Throwable $th) {
                $this->error("✗ Error resetting {$device->name} ({$device->ip_address}): " . $th->getMessage() . " on line " . $th->getLine());
                $errorCount++;
            }
        }

        $this->info("✓ Reset completed. Success: {$successCount}, Errors: {$errorCount}");
        
        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Retry a callback up to MAX_RETRIES times with a delay between attempts.
     */
    private function retry(callable $callback, string $operationLabel): void
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $callback();
                return;
            } catch (\Exception $e) {
                $lastException = $e;

                if ($this->option('v')) {
                    $this->warn("    ⟳ {$operationLabel} failed (attempt {$attempt}/" . self::MAX_RETRIES . "): " . $e->getMessage());
                }

                if ($attempt < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Reset a single device by writing a momentary pulse (1 then 0)
     */
    private function resetDevice(InsBpmDevice $device)
    {
        $unit_id = 1; // Standard Modbus unit ID
        $resetAddr = $device->config['addr_reset'] ?? null;

        if ($resetAddr === null) {
            throw new \RuntimeException("Reset address is not configured for {$device->name}");
        }

        $this->retry(function () use ($device, $unit_id, $resetAddr) {
            $requestOn = WriteCoilsBuilder::newWriteMultipleCoils(
                'tcp://' . $device->ip_address . ':503',
                $unit_id,
                1
            )
            ->coil($resetAddr, 1)
            ->build();

            $client = new NonBlockingClient(['readTimeoutSec' => 2]);
            $client->sendRequests($requestOn);

            // Simulate HMI momentary button: write back to false after a short pulse.
            usleep(self::MOMENTARY_PULSE_MS * 1000);

            $requestOff = WriteCoilsBuilder::newWriteMultipleCoils(
                'tcp://' . $device->ip_address . ':503',
                $unit_id,
                1
            )
            ->coil($resetAddr, 0)
            ->build();

            $client->sendRequests($requestOff);
        }, "Reset {$device->name} line {$device->line} addr {$resetAddr}");

        if ($this->option('v')) {
            $this->info("  ✓ Reset pulse sent to line {$device->line} at address {$resetAddr}");
        }
    }

    /**
     * Delete data on database for today's BPM counts (before reset)
     */
    private function deleteTodayBpmCounts()
    {
        if ($this->option('v')) {
            $this->info("  → Deleting today's BPM counts");
        }
        try {
            $data = InsBpmCount::where('created_at', '>=', now()->startOfDay())->get();
            if ($data->isEmpty()) {
                if ($this->option('v')) {
                    $this->info("  ✓ No today's BPM counts found");
                }
                return;
            }

            $data->each(function ($item) {
                $item->delete();
            });

            if ($this->option('v')) {
                $this->info("  ✓ Today's BPM counts deleted");
            }
        } catch (\Throwable $th) {
            if ($this->option('v')) {
                $this->error("  ✗ Error deleting today's BPM counts: " . $th->getMessage());
            }
            throw $th;
        }
    }
}
