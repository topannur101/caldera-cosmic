<?php

namespace App\Console\Commands;

use App\InsStcPush;
use App\Models\InsStcAdjust;
use App\Models\InsStcDSum;
use App\Models\InsStcMachine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Network\NonBlockingClient;

class InsStcRoutine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-stc-routine {--v : Verbose output} {--d : Debug output} {--dry-run : Log adjustments but don\'t send to machines}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adjust STC machine SV values based on ambient temperature changes from baseline (fixed n+1 reference drift)';

    // Configuration - same timing as InsClmPoll
    protected $ambient_machine_id = 7;      // ID 7 = Chamber Line 6 (ambient sensor)

    protected $unit_id = 1;                 // Modbus unit ID

    protected $buffer_timeout = 1800;       // 30 minutes in seconds

    protected $polling_interval = 30;       // 30 seconds

    protected $reset_timeout = 300;         // 5 minutes in seconds

    protected $adjustment_threshold = 2.0;  // ±2.0°C threshold

    // State management
    protected $data_buffer = [];            // Buffer for ambient temperature measurements

    protected $last_successful_poll = null; // Last successful measurement timestamp

    // Logging
    protected $log_file_path;

    protected $log_retention_days = 30; // Keep logs for 30 days

    public function __construct()
    {
        parent::__construct();

        // Set up log file path with date rotation
        $this->log_file_path = storage_path('logs/stc_adjustments_'.date('Y-m-d').'.log');
    }

    /**
     * Clean up old log files
     */
    public function cleanupOldLogs()
    {
        try {
            $log_dir = storage_path('logs');
            $cutoff_date = Carbon::now()->subDays($this->log_retention_days);

            $deleted_count = 0;

            // Find and delete old STC adjustment log files
            $pattern = $log_dir.'/stc_adjustments_*.log';
            $log_files = glob($pattern);

            foreach ($log_files as $log_file) {
                // Extract date from filename
                if (preg_match('/stc_adjustments_(\d{4}-\d{2}-\d{2})\.log$/', $log_file, $matches)) {
                    $file_date = Carbon::createFromFormat('Y-m-d', $matches[1]);

                    if ($file_date->lt($cutoff_date)) {
                        if (unlink($log_file)) {
                            $deleted_count++;
                            if ($this->option('d')) {
                                $this->line('Deleted old log file: '.basename($log_file));
                            }
                        }
                    }
                }
            }

            if ($deleted_count > 0 && $this->option('v')) {
                $this->comment("✓ Cleaned up {$deleted_count} old log files (older than {$this->log_retention_days} days)");
            }

        } catch (\Exception $e) {
            if ($this->option('v')) {
                $this->comment("⚠ Failed to cleanup old logs: {$e->getMessage()}");
            }
        }
    }

    /**
     * Write log entry for adjustments
     */
    public function writeAdjustmentLog($d_sum, $machine, $position, $current_temp, $delta_temp, $applied, $reason, $sv_before = [], $sv_after = [], $reference_info = null)
    {
        $timestamp = Carbon::now();

        $status = $applied ? 'APPLIED' : (str_contains($reason, 'DRY RUN') ? 'DRY_RUN' : 'FAILED');

        // Build reference info string
        $reference_str = 'Unknown Reference';
        if ($reference_info) {
            $ref_source = ucfirst($reference_info['source']);
            $ref_timestamp = $reference_info['timestamp']->format('H:i:s');
            $ref_id = $reference_info['id'];
            $reference_str = sprintf('Reference: %.1f°C (%s ID:%d @ %s)', 
                $current_temp - $delta_temp, 
                $ref_source, 
                $ref_id, 
                $ref_timestamp
            );
        } else {
            $reference_str = sprintf('Reference: %.1f°C (Legacy baseline)', $current_temp - $delta_temp);
        }

        $log_entry = sprintf(
            "[%s] %s - %s %s - %s, Current: %.1f°C, Delta: %+.1f°C - D_Sum ID: %d - %s\n",
            $timestamp->format('Y-m-d H:i:s'),
            $status,
            $machine->line,
            strtoupper($position),
            $reference_str,
            $current_temp,
            $delta_temp,
            $d_sum->id,
            $reason
        );

        // Add SV change details if available
        if (! empty($sv_before) && ! empty($sv_after)) {
            $sv_changes = [];
            for ($i = 0; $i < min(count($sv_before), count($sv_after)); $i++) {
                $diff = $sv_after[$i] - $sv_before[$i];
                if ($diff != 0) {
                    $sv_changes[] = sprintf('SV%d:%+.1f', $i + 1, $diff);
                }
            }

            if (! empty($sv_changes)) {
                $log_entry = rtrim($log_entry, "\n").' - Changes: '.implode(', ', $sv_changes)."\n";
            }
        }

        try {
            // Ensure log directory exists
            $log_dir = dirname($this->log_file_path);
            if (! is_dir($log_dir)) {
                mkdir($log_dir, 0755, true);
            }

            // Append to daily log file
            file_put_contents($this->log_file_path, $log_entry, FILE_APPEND | LOCK_EX);

            if ($this->option('d')) {
                $this->line('Log entry written to: '.basename($this->log_file_path));
            }

        } catch (\Throwable $e) {
            if ($this->option('v')) {
                $this->comment("⚠ Failed to write to log file: {$e->getMessage()}");
            }
        }
    }

    public function convertToDecimal($value)
    {
        $value = (int) $value;

        return (float) ($value / 10);
    }

    /**
     * Calculate median of an array of values
     */
    public function calculateMedian($values)
    {
        if (empty($values)) {
            return null;
        }

        sort($values);
        $count = count($values);

        if ($count % 2 == 0) {
            $mid1 = $values[($count / 2) - 1];
            $mid2 = $values[$count / 2];

            return ($mid1 + $mid2) / 2;
        } else {
            return $values[floor($count / 2)];
        }
    }

    /**
     * Add measurement to buffer
     */
    public function addToBuffer($temperature)
    {
        $timestamp = Carbon::now();

        $this->data_buffer[] = [
            'timestamp' => $timestamp->format('Y-m-d H:i:s'),
            'temperature' => $temperature,
        ];

        $this->last_successful_poll = $timestamp;

        if ($this->option('d')) {
            $buffer_size = count($this->data_buffer);
            $this->line("Added to buffer: T={$temperature}°C (Buffer size: {$buffer_size})");
        }
    }

    /**
     * Reset the data buffer
     */
    public function resetBuffer()
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
    public function checkBufferStatus()
    {
        $now = Carbon::now();
        $buffer_size = count($this->data_buffer);

        if ($this->option('d')) {
            $this->line("\n=== BUFFER STATUS CHECK ===");
            $this->line('Current time: '.$now->format('H:i:s'));
            $this->line("Buffer size: {$buffer_size}");
            $this->line('Last successful poll: '.($this->last_successful_poll ? $this->last_successful_poll->format('H:i:s') : 'null'));
        }

        // Check if we need to reset due to timeout (no successful polls for 5 minutes)
        if ($this->last_successful_poll) {
            $seconds_since_last = $this->last_successful_poll->diffInSeconds($now);

            if ($this->option('d')) {
                $this->line("Seconds since last successful poll: {$seconds_since_last}");
                $this->line("Reset timeout: {$this->reset_timeout} seconds");
            }

            if ($seconds_since_last >= $this->reset_timeout) {
                $this->line('⚠ No successful measurements for 5+ minutes, resetting buffer');
                $this->resetBuffer();
                $this->last_successful_poll = null;

                return;
            }
        }

        // Check if buffer is ready for processing (30 minutes worth of data)
        if (! empty($this->data_buffer)) {
            $first_measurement_time = Carbon::parse($this->data_buffer[0]['timestamp']);
            $seconds_since_first = $first_measurement_time->diffInSeconds($now);

            if ($this->option('d')) {
                $this->line('First measurement: '.$first_measurement_time->format('H:i:s'));
                $this->line("Seconds since first measurement: {$seconds_since_first}");
                $this->line("Buffer timeout: {$this->buffer_timeout} seconds");
            }

            if ($seconds_since_first >= $this->buffer_timeout) {
                if ($this->option('d')) {
                    $this->line('→ BUFFER TIMEOUT REACHED! Processing...');
                }
                $this->processBuffer();
            }
        }

        if ($this->option('d')) {
            $this->line("=== END BUFFER CHECK ===\n");
        }
    }

    /**
     * Poll ambient temperature from the designated sensor machine
     */
    public function pollAmbientTemperature($machine)
    {
        if ($this->option('v')) {
            $this->comment("→ Polling ambient temperature from {$machine->name} ({$machine->ip_address})");
        }

        try {
            // Build Modbus request for input registers (same as InsClmPoll)
            $fc4 = ReadRegistersBuilder::newReadInputRegisters('tcp://'.$machine->ip_address.':503', $this->unit_id)
                ->int16(0, 'temperature')
                ->int16(1, 'humidity')
                ->build();

            // Execute Modbus request
            $fc4_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc4);
            $fc4_data = $fc4_response->getData();

            // Convert raw temperature to decimal format
            $temperature = $this->convertToDecimal($fc4_data['temperature']);

            if ($this->option('d')) {
                $this->line("\nAmbient data from {$machine->name} ({$machine->ip_address}):");
                $this->table(['Field', 'Raw Value', 'Converted Value'], [
                    ['Temperature', $fc4_data['temperature'], $temperature.'°C'],
                ]);
            }

            // Add to buffer
            $this->addToBuffer($temperature);

            return true;

        } catch (\Throwable $th) {
            $this->error("✗ Error polling ambient temperature from {$machine->name} ({$machine->ip_address}): ".$th->getMessage());

            return false;
        }
    }

    /**
     * Process completed buffer and perform STC adjustments
     */
    public function processBuffer()
    {
        $buffer_size = count($this->data_buffer);

        if (empty($this->data_buffer)) {
            if ($this->option('d')) {
                $this->line('Buffer is empty, nothing to process');
            }

            return;
        }

        if ($this->option('v')) {
            $this->comment("→ Processing buffer: {$buffer_size} measurements");
        }

        // Calculate median ambient temperature
        $temperatures = array_column($this->data_buffer, 'temperature');
        $current_ambient_temp = $this->calculateMedian($temperatures);

        if ($this->option('d')) {
            $this->line("Current ambient temperature (median): {$current_ambient_temp}°C");
        }

        // Find eligible d_sums and perform adjustments
        $this->performAdjustments($current_ambient_temp);

        // Clear buffer after processing
        $this->resetBuffer();
    }

    /**
     * Get the latest ambient temperature prioritizing d-sum within 8 hours, then linked adjustments
     * 
     * @param int $machine_id
     * @param string $position
     * @param int $hours_back How many hours back to look for d-sum (default: 8)
     * @return array|null Returns ['temp' => float, 'source' => string, 'timestamp' => Carbon] or null if not found
     */
    public function getLatestAmbientTemperature($machine_id, $position, $hours_back = 8)
    {
        $cutoff_time = Carbon::now()->subHours($hours_back);
        
        if ($this->option('d')) {
            $this->line("→ Looking for d-sum within {$hours_back} hours for {$machine_id} {$position} since {$cutoff_time->format('H:i:s')}");
        }

        // Step 1: Find latest d_sum within specified hours (default 8 hours)
        $latest_d_sum = InsStcDSum::where('ins_stc_machine_id', $machine_id)
            ->where('position', $position)
            ->where('created_at', '>=', $cutoff_time)
            ->whereRaw('JSON_EXTRACT(at_values, "$[1]") > 0') // Valid baseline temp in at_values[1]
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latest_d_sum) {
            if ($this->option('d')) {
                $this->line("  → No d-sum found within {$hours_back} hour(s)");
            }
            return null;
        }

        if ($this->option('d')) {
            $this->line("  → Found d-sum ID: {$latest_d_sum->id} at {$latest_d_sum->created_at->format('H:i:s')}");
        }

        // Step 2: Look for latest ins_stc_adjust linked to this specific d-sum
        $linked_adjustment = InsStcAdjust::where('ins_stc_d_sum_id', $latest_d_sum->id)
            ->where('current_temp', '>', 0) // Valid temperature
            ->orderBy('created_at', 'desc')
            ->first();

        // Step 3: Return adjustment temp if found, otherwise return d-sum AT value
        if ($linked_adjustment) {
            if ($this->option('d')) {
                $this->line("  → Using linked adjustment ID: {$linked_adjustment->id} temp: {$linked_adjustment->current_temp}°C");
            }
            
            return [
                'temp' => $linked_adjustment->current_temp,
                'source' => 'adjustment',
                'timestamp' => $linked_adjustment->created_at,
                'id' => $linked_adjustment->id
            ];
        } else {
            // Use d-sum AT value
            $at_values = json_decode($latest_d_sum->at_values, true);
            if (is_array($at_values) && isset($at_values[1]) && $at_values[1] > 0) {
                if ($this->option('d')) {
                    $this->line("  → Using d-sum AT value: {$at_values[1]}°C (no linked adjustments found)");
                }
                
                return [
                    'temp' => (float) $at_values[1],
                    'source' => 'd_sum',
                    'timestamp' => $latest_d_sum->created_at,
                    'id' => $latest_d_sum->id
                ];
            }
        }

        if ($this->option('d')) {
            $this->line("  → D-sum found but no valid AT value");
        }
        
        return null;
    }

    /**
     * Find eligible d_sums and perform STC adjustments
     */
    public function performAdjustments($current_ambient_temp)
    {
        // Get all machines that have AT adjustment enabled and are not loopback addresses
        $machines = InsStcMachine::where('is_at_adjusted', true)
            ->whereNot('ip_address', 'like', '127.%')
            ->get();

        $adjustments_made = 0;
        $total_checked = 0;

        foreach ($machines as $machine) {
            foreach (['upper', 'lower'] as $position) {
                $total_checked++;

                // Find latest d_sum for this machine/position within 4 hours (for SV application)
                $latest_d_sum = InsStcDSum::where('ins_stc_machine_id', $machine->id)
                    ->where('position', $position)
                    ->where('created_at', '>=', Carbon::now()->subHours(4))
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (! $latest_d_sum) {
                    if ($this->option('d')) {
                        $this->line("No recent d_sum found for {$machine->line} {$position} (within 4 hours)");
                    }

                    continue;
                }

                // Get latest ambient temperature reference (within 8 hours) - this fixes the n+1 problem
                $latest_ambient = $this->getLatestAmbientTemperature($machine->id, $position, 8);
                
                if (! $latest_ambient) {
                    if ($this->option('d')) {
                        $this->line("No recent ambient temperature reference found for {$machine->line} {$position} (within 8 hours)");
                    }

                    continue;
                }

                $reference_temp = $latest_ambient['temp'];
                $delta_temp = $current_ambient_temp - $reference_temp;

                if ($this->option('d')) {
                    $this->line("→ {$machine->line} {$position}: reference={$reference_temp}°C ({$latest_ambient['source']}), current={$current_ambient_temp}°C, delta={$delta_temp}°C");
                }

                // Check if adjustment is needed (threshold ±2.0°C)
                if (abs($delta_temp) >= $this->adjustment_threshold) {
                    $adjustment_result = $this->adjustMachine($latest_d_sum, $machine, $position, $current_ambient_temp, $reference_temp, $delta_temp, $latest_ambient);

                    if ($adjustment_result) {
                        $adjustments_made++;
                    }
                } else {
                    if ($this->option('d')) {
                        $this->line('  → No adjustment needed (below threshold)');
                    }
                }
            }
        }

        $total_machines = $machines->count();
        $this->info("✓ Found {$total_machines} machines with AT adjustment enabled");
        $this->info("✓ Processed {$total_checked} machine/position combinations, made {$adjustments_made} adjustments");
    }

    /**
     * Adjust a specific machine/position
     */
    public function adjustMachine($d_sum, $machine, $position, $current_temp, $reference_temp, $delta_temp, $reference_info = null)
    {
        if ($this->option('v')) {
            $this->comment("→ Adjusting {$machine->line} {$position} by {$delta_temp}°C");
        }

        try {
            // Get current SV values from machine
            $current_sv = $this->getCurrentSvFromMachine($machine, $position);

            if (! $current_sv) {
                $reason = 'Failed to read current SV values from machine';
                $this->createAdjustmentRecord($d_sum, $current_temp, $delta_temp, [], [], false, $reason, $reference_info);
                $this->writeAdjustmentLog($d_sum, $machine, $position, $current_temp, $delta_temp, false, $reason, [], [], $reference_info);

                return false;
            }

            // Calculate adjusted SV values using machine-specific strength settings
            $adjusted_sv = $this->calculateAdjustedSv($current_sv, $delta_temp, $machine, $position);

            // Apply adjustment to machine (unless dry-run)
            $applied = false;
            $reason = '';

            if ($this->option('dry-run')) {
                $reason = 'DRY RUN - adjustment not sent to machine';
                $this->info("  → [DRY RUN] Would adjust {$machine->line} {$position}: ".json_encode($adjusted_sv));
            } else {
                $applied = $this->sendSvToMachine($machine, $position, $adjusted_sv);
                $reason = $applied ? 'Adjustment applied successfully' : 'Failed to send adjustment to machine';

                if ($applied) {
                    $this->info("  → ✓ Applied adjustment to {$machine->line} {$position}: ".json_encode($adjusted_sv));
                } else {
                    $this->error("  → ✗ Failed to apply adjustment to {$machine->line} {$position}");
                }
            }

            // Create adjustment record and write log
            $this->createAdjustmentRecord($d_sum, $current_temp, $delta_temp, $current_sv, $adjusted_sv, $applied, $reason, $reference_info);
            $this->writeAdjustmentLog($d_sum, $machine, $position, $current_temp, $delta_temp, $applied, $reason, $current_sv, $adjusted_sv, $reference_info);

            return true;

        } catch (\Exception $e) {
            $reason = 'Exception during adjustment: '.$e->getMessage();
            $this->error("  → ✗ Error adjusting {$machine->line} {$position}: ".$e->getMessage());
            $this->createAdjustmentRecord($d_sum, $current_temp, $delta_temp, [], [], false, $reason, $reference_info);
            $this->writeAdjustmentLog($d_sum, $machine, $position, $current_temp, $delta_temp, false, $reason, [], [], $reference_info);

            return false;
        }
    }

    /**
     * Get current SV values from machine via Modbus
     */
    public function getCurrentSvFromMachine($machine, $position)
    {
        try {
            // Use same logic as InsStcPoll for reading SV values
            $sv_request = \App\InsStc::buildRegisterRequest($position.'_sv_r', $machine->ip_address, 503, $this->unit_id);
            $sv_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($sv_request);
            $sv_data = $sv_response->getData();

            // Extract SV values (assuming 8 values)
            $sv_values = [];
            for ($i = 1; $i <= 8; $i++) {
                $key = $position.'_sv_r_'.$i;
                $sv_values[] = isset($sv_data[$key]) ? (float) $sv_data[$key] : 0.0;
            }

            return $sv_values;

        } catch (\Exception $e) {
            if ($this->option('d')) {
                $this->line("Failed to read SV from {$machine->line} {$position}: ".$e->getMessage());
            }

            return null;
        }
    }

    /**
     * Calculate adjusted SV values based on temperature delta and machine-specific strength settings
     */
    public function calculateAdjustedSv($current_sv, $delta_temp, $machine, $position)
    {
        // Get machine-specific SV limits with fallback to defaults
        $svp_highs = $machine->section_limits_high ?? [83, 78, 73, 68, 63, 58, 53, 48];
        $svp_lows = $machine->section_limits_low ?? [73, 68, 63, 58, 53, 48, 43, 38];

        $adjusted_sv = [];

        // Get machine-specific adjustment strength for this position (0-100%)
        $adjustment_strengths = $machine->at_adjust_strength[$position] ?? [0, 0, 0, 0, 0, 0, 0, 0];

        if ($this->option('d')) {
            $this->line("  → Machine {$machine->line} {$position} adjustment strengths: ".json_encode($adjustment_strengths));
        }

        // Negative correlation: temperature increase causes SV decrease
        $base_adjustment = $delta_temp * -1;

        foreach ($current_sv as $index => $sv) {
            // Get adjustment strength for this section (convert percentage to ratio)
            $strength_ratio = ($adjustment_strengths[$index] ?? 0) / 100;

            // Calculate section-specific adjustment
            $section_adjustment = $base_adjustment * $strength_ratio;
            $new_sv = $sv + $section_adjustment;

            // Apply section-specific min/max validation
            $section_max = $svp_highs[$index] ?? 99;
            $section_min = $svp_lows[$index] ?? 30;

            $new_sv = max($section_min, min($section_max, $new_sv));

            // Round to integer
            $adjusted_sv[] = round($new_sv, 0);
        }

        return $adjusted_sv;
    }

    /**
     * Send adjusted SV values to machine
     */
    public function sendSvToMachine($machine, $position, $adjusted_sv)
    {
        try {
            $push = new InsStcPush;

            // First send the SV values using section_svp
            $result1 = $push->send(
                'section_svp',
                $machine->ip_address,
                $position,
                $adjusted_sv
            );

            // Then apply the SV write using apply_svw
            $result2 = $push->send(
                'apply_svw',
                $machine->ip_address,
                $position,
                [true]
            );

            return $result1 && $result2;

        } catch (\Exception $e) {
            if ($this->option('d')) {
                $this->line("Failed to send SV to {$machine->line} {$position}: ".$e->getMessage());
            }

            return false;
        }
    }

    /**
     * Create adjustment record in database
     */
    public function createAdjustmentRecord($d_sum, $current_temp, $delta_temp, $sv_before, $sv_after, $applied, $reason, $reference_info = null)
    {
        try {
            InsStcAdjust::create([
                'ins_stc_d_sum_id' => $d_sum->id,
                'current_temp' => round($current_temp, 1),
                'delta_temp' => round($delta_temp, 1),
                'sv_before' => $sv_before,
                'sv_after' => $sv_after,
                'adjustment_applied' => $applied,
                'adjustment_reason' => $reason,
            ]);

            if ($this->option('d')) {
                $this->line("  → Adjustment record created for d_sum ID: {$d_sum->id}");
            }

        } catch (\Exception $e) {
            $this->error('Failed to create adjustment record: '.$e->getMessage());
        }
    }

    /**
     * Execute the console command
     */
    public function handle()
    {
        // Get the ambient sensor machine
        $ambient_machine = InsStcMachine::find($this->ambient_machine_id);

        if (! $ambient_machine) {
            $this->error("✗ Ambient sensor machine not found with ID: {$this->ambient_machine_id}");

            return 1;
        }

        $this->info("✓ InsStcRoutine started - monitoring ambient from {$ambient_machine->name}");
        $this->info("✓ Configuration: {$this->polling_interval}s interval, {$this->buffer_timeout}s buffer timeout (30 min)");
        $this->info('✓ Daily log file: '.basename($this->log_file_path)." (retention: {$this->log_retention_days} days)");

        if ($this->option('dry-run')) {
            $this->info('✓ DRY-RUN MODE: Adjustments will be logged but not sent to machines');
        }

        if ($this->option('v')) {
            $this->comment("Ambient sensor: {$ambient_machine->name} ({$ambient_machine->ip_address})");
            $this->comment("Polling every {$this->polling_interval} seconds for temperature");
            $this->comment('Processing 30-minute median values for STC adjustments');
        }

        // Clean up old log files on startup
        $this->cleanupOldLogs();

        // Initialize state
        $this->data_buffer = [];
        $this->last_successful_poll = null;

        // Main polling loop
        $last_poll_time = 0;

        while (true) {
            $current_time = time();

            // Check if it's time to poll (every 30 seconds)
            if (($current_time - $last_poll_time) >= $this->polling_interval) {

                // Poll ambient temperature
                $success = $this->pollAmbientTemperature($ambient_machine);

                if ($success) {
                    $last_poll_time = $current_time;
                } else {
                    if ($this->option('v')) {
                        $this->comment('→ Polling failed, will retry in next cycle');
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
