<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsStcMachine;
use App\Models\InsClmRecord;
use App\InsStcAmbientAdjust;
use Illuminate\Console\Command;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;

class InsClmPoll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-clm-poll {--v : Verbose output} {--d : Debug output} {--dry-run : Log STC adjustments but don\'t send to machines}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll temperature and humidity data from Modbus server and save 30-minute median values with cumulative ambient-based STC adjustment';

    // Configuration
    protected $machine_id = 7;              // ID 7 = Chamber Line 6
    protected $unit_id = 1;                 // Modbus unit ID
    // Production timing - Changed to 30 minutes
    protected $buffer_timeout = 1800;       // 30 minutes in seconds (was 3600)
    protected $polling_interval = 30;       // 0.5 minute in seconds (was 1 minute)
    protected $reset_timeout = 300;         // 5 minutes in seconds (was 5 minutes)
    
    // State management
    protected $data_buffer = [];            // Buffer for temperature/humidity measurements
    protected $last_successful_poll = null; // Last successful measurement timestamp
    protected $ambient_at_last_stc_adjustment = null; // Ambient temp when we last made STC adjustment
    protected $log_file_path;               // Path for buffer processing logs

    /**
     * Convert 3-digit integer value to decimal format (divide by 10)
     * Examples: 253 -> 25.3, 506 -> 50.6
     */
    function convertToDecimal($value)
    {
        $value = (int) $value;
        return (float) ($value / 10);
    }

    /**
     * Calculate median of an array of values
     */
    function calculateMedian($values)
    {
        if (empty($values)) return null;
        
        sort($values);
        $count = count($values);
        
        if ($count % 2 == 0) {
            // Even number of values - average of two middle values
            $mid1 = $values[($count / 2) - 1];
            $mid2 = $values[$count / 2];
            return ($mid1 + $mid2) / 2;
        } else {
            // Odd number of values - middle value
            return $values[floor($count / 2)];
        }
    }

    /**
     * Add measurement to buffer
     */
    function addToBuffer($temperature, $humidity)
    {
        $timestamp = Carbon::now();
        
        $this->data_buffer[] = [
            'timestamp' => $timestamp->format('Y-m-d H:i:s'),
            'temperature' => $temperature,
            'humidity' => $humidity,
        ];
        
        $this->last_successful_poll = $timestamp;
        
        if ($this->option('d')) {
            $buffer_size = count($this->data_buffer);
            $this->line("Added to buffer: T={$temperature}°C, H={$humidity}% (Buffer size: {$buffer_size})");
        }
    }

    /**
     * Process completed buffer (30 minutes of data) with STC ambient adjustment
     */
    function processBuffer()
    {
        $buffer_size = count($this->data_buffer);
        
        if (empty($this->data_buffer)) {
            if ($this->option('d')) {
                $this->line("Buffer is empty, nothing to process");
            }
            return;
        }

        if ($this->option('v')) {
            $this->comment("→ Processing buffer: {$buffer_size} measurements");
        }

        // Extract temperature and humidity arrays
        $temperatures = [];
        $humidities = [];
        
        foreach ($this->data_buffer as $record) {
            $temperatures[] = $record['temperature'];
            $humidities[] = $record['humidity'];
        }

        // Calculate medians
        $median_temperature = $this->calculateMedian($temperatures);
        $median_humidity = $this->calculateMedian($humidities);

        if ($this->option('d')) {
            $this->line("Statistics calculated:");
            $this->table(['Metric', 'Value'], [
                ['Temperature Median', round($median_temperature, 1) . '°C'],
                ['Humidity Median', round($median_humidity, 1) . '%'],
                ['Sample Count', $buffer_size],
                ['Time Range', $this->data_buffer[0]['timestamp'] . ' to ' . end($this->data_buffer)['timestamp']],
            ]);
        }

        // Save to database
        try {
            $record = new InsClmRecord([
                'location' => 'ip',
                'temperature' => round($median_temperature, 1),
                'humidity' => round($median_humidity, 1),
            ]);

            $record->save();

            $this->info("✓ 30-minute record saved: T={$median_temperature}°C, H={$median_humidity}% ({$buffer_size} samples)");

        } catch (\Exception $e) {
            $this->error("✗ Failed to save record: {$e->getMessage()}");
            
            if ($this->option('d')) {
                $this->line("Debug info:");
                $this->line("  Temperature: {$median_temperature}");
                $this->line("  Humidity: {$median_humidity}");
                $this->line("  Buffer size: {$buffer_size}");
            }
        }

        // NEW: Check for STC ambient adjustment
        $adjustment_made = $this->checkAmbientAdjustment($median_temperature);

        // Log every buffer processing cycle
        $this->logBufferProcessing($median_temperature, $median_humidity, $buffer_size, $adjustment_made);

        // Clear buffer after processing
        $this->resetBuffer();
    }

    /**
     * Check if STC ambient adjustment should be triggered (cumulative approach)
     * Returns true if adjustment was made, false otherwise
     */
    function checkAmbientAdjustment($current_ambient_temp)
    {
        if ($this->option('d')) {
            $this->line("\n=== AMBIENT ADJUSTMENT CHECK ===");
            $this->line("Current ambient: {$current_ambient_temp}°C");
            $this->line("Baseline (last adjustment): " . ($this->ambient_at_last_stc_adjustment ?: 'null'));
        }

        // Initialize baseline if first run
        if ($this->ambient_at_last_stc_adjustment === null) {
            if ($this->option('v')) {
                $this->comment("→ Initializing ambient baseline: {$current_ambient_temp}°C");
            }
            $this->ambient_at_last_stc_adjustment = $current_ambient_temp;
            return false; // No adjustment made
        }

        // Calculate cumulative change since last STC adjustment
        $cumulative_change = $current_ambient_temp - $this->ambient_at_last_stc_adjustment;
        $abs_cumulative_change = abs($cumulative_change);

        if ($this->option('d')) {
            $this->line("Cumulative change: {$cumulative_change}°C (absolute: {$abs_cumulative_change}°C)");
        }

        // Check if cumulative change is significant (≥1°C)
        if ($abs_cumulative_change < 1.0) {
            if ($this->option('v')) {
                $this->comment("→ Cumulative change < 1°C, no STC adjustment needed");
            }
            return false; // No adjustment made
        }

        // Trigger STC adjustment
        if ($this->option('v')) {
            $this->comment("→ Significant cumulative ambient change detected: {$cumulative_change}°C");
            $this->comment("→ Triggering STC ambient adjustment...");
        }

        $adjustment_successful = false;

        try {
            $adjuster = new InsStcAmbientAdjust();
            $adjuster->setOutput($this->output);
            $adjuster->adjustForAmbient($cumulative_change, $this->option('dry-run'), $this->option('v'), $this->option('d'));
            
            $adjustment_successful = true;
            
            // Reset baseline after successful adjustment
            if (!$this->option('dry-run')) {
                $this->ambient_at_last_stc_adjustment = $current_ambient_temp;
                if ($this->option('v')) {
                    $this->comment("→ Baseline reset to: {$current_ambient_temp}°C");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("✗ STC ambient adjustment failed: {$e->getMessage()}");
            
            if ($this->option('d')) {
                $this->line("Stack trace: " . $e->getTraceAsString());
            }
        }

        if ($this->option('d')) {
            $this->line("=== END AMBIENT CHECK ===\n");
        }

        return $adjustment_successful;
    }

    /**
     * Log every buffer processing cycle
     */
    function logBufferProcessing($ambient_temp, $humidity, $buffer_size, $adjustment_made)
    {
        $timestamp = Carbon::now();
        $cumulative_change = $this->ambient_at_last_stc_adjustment !== null 
            ? $ambient_temp - $this->ambient_at_last_stc_adjustment 
            : 0;

        if ($adjustment_made) {
            $status = $this->option('dry-run') ? 'STC_DRY_RUN' : 'STC_ADJUSTED';
            $change_info = sprintf('Cumulative: %+.1f°C', $cumulative_change);
        } else {
            $status = 'NO_ADJUSTMENT';
            $change_info = sprintf('Cumulative: %+.1f°C (threshold: ±1.0°C)', $cumulative_change);
        }

        $log_entry = sprintf(
            "[%s] %s - Ambient: %.1f°C, Humidity: %.1f%% - %s - Samples: %d",
            $timestamp->format('Y-m-d H:i:s'),
            $status,
            $ambient_temp,
            $humidity,
            $change_info,
            $buffer_size
        );

        try {
            // Ensure log directory exists
            $log_dir = dirname($this->log_file_path);
            if (!is_dir($log_dir)) {
                mkdir($log_dir, 0755, true);
            }
            
            // Append to log file
            file_put_contents($this->log_file_path, $log_entry . "\n", FILE_APPEND | LOCK_EX);
            
            if ($this->option('d')) {
                $this->line("Buffer processing logged: {$status}");
            }
            
        } catch (\Throwable $e) {
            // Log file writing failed, but don't stop the process
            if ($this->option('v')) {
                $this->comment("⚠ Failed to write to buffer log file: {$e->getMessage()}");
            }
        }
    }

    /**
     * Reset the data buffer
     */
    function resetBuffer()
    {
        $buffer_size = count($this->data_buffer);
        $this->data_buffer = [];
        
        if ($this->option('d')) {
            $this->line("Buffer reset (was {$buffer_size} measurements)");
        }
    }

    /**
     * Check if buffer should be processed or reset
     */
    function checkBufferStatus()
    {
        $now = Carbon::now();
        $buffer_size = count($this->data_buffer);
        
        if ($this->option('d')) {
            $this->line("\n=== BUFFER STATUS CHECK ===");
            $this->line("Current time: " . $now->format('H:i:s'));
            $this->line("Buffer size: {$buffer_size}");
            $this->line("Last successful poll: " . ($this->last_successful_poll ? $this->last_successful_poll->format('H:i:s') : 'null'));
        }

        // Check if we need to reset due to timeout (no successful polls for 10 minutes)
        if ($this->last_successful_poll) {
            $seconds_since_last = $this->last_successful_poll->diffInSeconds($now);
            
            if ($this->option('d')) {
                $this->line("Seconds since last successful poll: {$seconds_since_last}");
                $this->line("Reset timeout: {$this->reset_timeout} seconds");
            }
            
            if ($seconds_since_last >= $this->reset_timeout) {
                $this->line("⚠ No successful measurements for 10+ minutes, resetting buffer");
                $this->resetBuffer();
                $this->last_successful_poll = null;
                return;
            }
        }

        // Check if buffer is ready for processing (30 minutes worth of data)
        if (!empty($this->data_buffer)) {
            $first_measurement_time = Carbon::parse($this->data_buffer[0]['timestamp']);
            $seconds_since_first = $first_measurement_time->diffInSeconds($now);
            
            if ($this->option('d')) {
                $this->line("First measurement: " . $first_measurement_time->format('H:i:s'));
                $this->line("Seconds since first measurement: {$seconds_since_first}");
                $this->line("Buffer timeout: {$this->buffer_timeout} seconds");
            }
            
            if ($seconds_since_first >= $this->buffer_timeout) {
                if ($this->option('d')) {
                    $this->line("→ BUFFER TIMEOUT REACHED! Processing...");
                }
                $this->processBuffer();
            }
        }

        if ($this->option('d')) {
            $this->line("=== END BUFFER CHECK ===\n");
        }
    }

    /**
     * Poll temperature and humidity from Modbus server
     */
    function pollData($machine)
    {
        if ($this->option('v')) {
            $this->comment("→ Polling {$machine->name} ({$machine->ip_address})");
        }

        try {
            // Build Modbus request for input registers
            $fc4 = ReadRegistersBuilder::newReadInputRegisters('tcp://' . $machine->ip_address . ':503', $this->unit_id)
                ->int16(0, 'temperature')
                ->int16(1, 'humidity')
                ->build();

            // Execute Modbus request
            $fc4_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc4);
            $fc4_data = $fc4_response->getData();

            // Convert raw values to decimal format
            $temperature = $this->convertToDecimal($fc4_data['temperature']);
            $humidity = $this->convertToDecimal($fc4_data['humidity']);

            if ($this->option('d')) {
                $this->line("\nRaw data from {$machine->name} ({$machine->ip_address}):");
                $this->table(['Field', 'Raw Value', 'Converted Value'], [
                    ['Temperature', $fc4_data['temperature'], $temperature . '°C'],
                    ['Humidity', $fc4_data['humidity'], $humidity . '%'],
                ]);
            }

            // Add to buffer
            $this->addToBuffer($temperature, $humidity);

            return true;

        } catch (\Throwable $th) {
            $this->error("✗ Error polling {$machine->name} ({$machine->ip_address}): " . $th->getMessage());
            return false;
        }
    }

    /**
     * Execute the console command
     */
    public function handle()
    {
        // Get the hardcoded machine
        $machine = InsStcMachine::find($this->machine_id);

        if (!$machine) {
            $this->error("✗ Machine not found with ID: {$this->machine_id}");
            return 1;
        }

        $this->info("✓ InsClmPoll started - monitoring {$machine->name}");
        $this->info("✓ Configuration: {$this->polling_interval}s interval, {$this->buffer_timeout}s buffer timeout (30 min)");
        
        if ($this->option('dry-run')) {
            $this->info("✓ DRY-RUN MODE: STC adjustments will be logged but not sent to machines");
        }

        if ($this->option('v')) {
            $this->comment("Machine: {$machine->name} ({$machine->ip_address})");
            $this->comment("Polling every {$this->polling_interval} seconds for temperature and humidity");
            $this->comment("Processing 30-minute median values with cumulative ambient-based STC adjustment");
        }

        // Initialize state
        $this->data_buffer = [];
        $this->last_successful_poll = null;
        $this->ambient_at_last_stc_adjustment = null;

        // Main polling loop
        $last_poll_time = 0;

        while (true) {
            $current_time = time();
            
            // Check if it's time to poll (every minute)
            if (($current_time - $last_poll_time) >= $this->polling_interval) {
                
                // Poll data from machine
                $success = $this->pollData($machine);
                
                if ($success) {
                    $last_poll_time = $current_time;
                } else {
                    // On failure, we don't update last_poll_time but continue the loop
                    if ($this->option('v')) {
                        $this->comment("→ Polling failed, will retry in next cycle");
                    }
                }
            }
            
            // Check buffer status (timeouts, processing)
            $this->checkBufferStatus();
            
            // Sleep for a short time before next iteration
            sleep(5);
        }
    }
}