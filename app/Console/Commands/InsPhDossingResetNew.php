<?php

namespace App\Console\Commands;

use App\Models\InsPhDosingDevice;
use Illuminate\Console\Command;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Write\WriteCoilsBuilder;

class InsPhDossingResetNew extends Command
{
    private const MOMENTARY_PULSE_MS = 500;
    private const HMI_RESET_BIT = 14;
    private const MODBUS_RESET_COIL = self::HMI_RESET_BIT - 1;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-ph-dossing-reset-new {--v : Verbose output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reset pulse to all PH Dossing devices (writes 1 then 0 to reset addresses)';


    public function handle()
    {
        // Get all active devices
        $devices = InsPhDosingDevice::active()->get();

        if ($devices->isEmpty()) {
            $this->error('✗ No active PH Dossing devices found');
            return 1;
        }

        $this->info('✓ InsPhDossingReset started - resetting ' . count($devices) . ' devices');
        $successCount = 0;
        $errorCount = 0;

        foreach ($devices as $device) {
            if ($this->option('v')) {
                $this->comment("→ Resetting {$device->name} ({$device->ip_address})");
            }
            try {
                $this->resetDevice($device);
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
     * Reset a single device by writing a momentary pulse (1 then 0)
     */
    private function resetDevice(InsPhDosingDevice $device)
    {
        $unit_id = 1; // Standard Modbus unit ID
        $resetAddr = self::MODBUS_RESET_COIL;
        try {
            $client = new NonBlockingClient(['readTimeoutSec' => 2]);

            $requestOn = WriteCoilsBuilder::newWriteMultipleCoils(
                'tcp://' . $device->ip_address . ':503', 
                $unit_id,
                1
            )
            ->coil($resetAddr, 1)
            ->build();
            $client->sendRequests($requestOn);

            usleep(self::MOMENTARY_PULSE_MS * 1000);

            $requestOff = WriteCoilsBuilder::newWriteMultipleCoils(
                'tcp://' . $device->ip_address . ':503',
                $unit_id,
                1
            )
            ->coil($resetAddr, 0)
            ->build();
            $client->sendRequests($requestOff);

            if ($this->option('v')) {
                $this->info("  ✓ Reset pulse sent to line {$device->line} (HMI MW_Bit " . self::HMI_RESET_BIT . " => coil {$resetAddr})");
            }

        } catch (\Exception $e) {
            $this->error("    ✗ Error resetting line {$device->line} at address {$resetAddr}: " . $e->getMessage() . "\n" . $e->getLine());
            throw $e; // Re-throw to be caught by parent try-catch
        }
    }

}
