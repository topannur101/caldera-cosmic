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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all active devices
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

        // Initialize last cumulative values from database
        $this->initializeLastValues($devices);

        // Main polling loop
        while (true) {
            foreach ($devices as $device) {
                if ($this->option('v')) {
                    $this->comment("→ Polling {$device->name} ({$device->ip_address})");
                }

                try {
                    $this->pollDevice($device);
                } catch (\Throwable $th) {
                    $this->error("✗ Error polling {$device->name} ({$device->ip_address}): " . $th->getMessage());
                }
            }

            // Sleep for 1 second before next poll
            sleep(1);
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
                if ($lastCount) {
                    $this->lastCumulativeValues[$line] = $lastCount->cumulative;
                    if ($this->option('d')) {
                        $this->line("Initialized line {$line} with last cumulative: {$lastCount->cumulative}");
                    }
                } else {
                    $this->lastCumulativeValues[$line] = null;
                    if ($this->option('d')) {
                        $this->line("Line {$line} has no previous data - will skip first reading");
                    }
                }
            }
        }
    }

    /**
     * Poll a single device and process all its lines
     */
    private function pollDevice(InsDwpDevice $device)
    {
        $unit_id = 1; // Standard Modbus unit ID

        foreach ($device->config as $lineConfig) {
            $line = strtoupper(trim($lineConfig['line']));
            $counterAddr = $lineConfig['addr_counter'];
            
            if ($this->option('d')) {
                $this->line("  Polling line {$line} at address {$counterAddr}");
            }

            try {
                // Build Modbus request for this line's counter
                $request = ReadRegistersBuilder::newReadHoldingRegisters(
                    'tcp://' . $device->ip_address . ':502', 
                    $unit_id
                )
                ->int32($counterAddr, 'counter_value')
                ->build();

                // Execute Modbus request
                $response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($request);
                $data = $response->getData();
                
                $currentCumulative = $data['counter_value'];

                if ($this->option('d')) {
                    $this->line("    Current cumulative: {$currentCumulative}");
                    $this->line("    Last cumulative: " . ($this->lastCumulativeValues[$line] ?? 'null'));
                }

                // Check if we have a previous value to compare against
                if ($this->lastCumulativeValues[$line] === null) {
                    // First reading - skip and store as reference
                    $this->lastCumulativeValues[$line] = $currentCumulative;
                    if ($this->option('d')) {
                        $this->line("    First reading - storing as reference");
                    }
                    continue;
                }

                // Calculate incremental value
                $incremental = $currentCumulative - $this->lastCumulativeValues[$line];

                // Handle counter reset (if current < previous, assume reset occurred)
                if ($incremental < 0) {
                    // Counter was reset, incremental = current value
                    $incremental = $currentCumulative;
                    if ($this->option('v')) {
                        $this->comment("  → Counter reset detected for line {$line}");
                    }
                }

                // Only store if there's an actual increment
                if ($incremental > 0) {
                    $count = new InsDwpCount([
                        'line' => $line,
                        'cumulative' => $currentCumulative,
                        'incremental' => $incremental,
                    ]);

                    $count->save();

                    if ($this->option('v')) {
                        $this->info("  ✓ Stored: Line {$line}, Cumulative: {$currentCumulative}, Incremental: +{$incremental}");
                    }
                }

                // Update last cumulative value
                $this->lastCumulativeValues[$line] = $currentCumulative;

            } catch (\Exception $e) {
                $this->error("    ✗ Error reading line {$line}: " . $e->getMessage());
                continue;
            }
        }
    }
}
