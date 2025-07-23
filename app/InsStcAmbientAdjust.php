<?php

namespace App;

use Carbon\Carbon;
use App\Models\InsStcMachine;
use App\Models\InsStcDSum;
use App\InsStc;
use App\InsStcPush;
use ModbusTcpClient\Network\NonBlockingClient;
use Symfony\Component\Console\Output\OutputInterface;

class InsStcAmbientAdjust
{
    protected $output;

    // SV limits per section (from InsStc::calculateSVP)
    protected $svp_highs = [ 83, 78, 73, 68, 63, 58, 53, 48 ];
    protected $svp_lows  = [ 73, 68, 63, 58, 53, 48, 43, 38 ];

    /**
     * Set output for console logging
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Main method to adjust STC based on ambient temperature changes
     * Returns detailed adjustment information instead of logging
     */
    public function adjustForAmbient($ambient_change, $dry_run = false, $verbose = false, $debug = false)
    {
        $timestamp = Carbon::now();
        
        if ($verbose && $this->output) {
            $this->output->writeln("→ Starting STC ambient adjustment for {$ambient_change}°C change");
        }

        // Get all machines with non-loopback IP addresses
        $machines = $this->getEligibleMachines();
        
        if (empty($machines)) {
            if ($verbose && $this->output) {
                $this->output->writeln("→ No eligible machines found");
            }
            return null;
        }

        $adjustment_summary = [];
        $total_adjustments_made = 0;

        foreach ($machines as $machine) {
            if ($debug && $this->output) {
                $this->output->writeln("Processing machine: Line " . sprintf('%02d', $machine->line) . " ({$machine->ip_address})");
            }

            // Check both upper and lower positions
            foreach (['upper', 'lower'] as $position) {
                
                // Skip if recent worker adjustment
                if ($this->hasRecentWorkerAdjustment($machine->id, $position)) {
                    if ($verbose && $this->output) {
                        $this->output->writeln("→ Skipping Line " . sprintf('%02d', $machine->line) . " {$position} - recent worker adjustment");
                    }
                    continue;
                }

                // Get current SV values from machine
                $current_sv_values = $this->getCurrentSVValues($machine, $position);
                
                if ($current_sv_values === null) {
                    if ($debug && $this->output) {
                        $this->output->writeln("✗ Failed to read SV values from Line " . sprintf('%02d', $machine->line) . " {$position}");
                    }
                    continue;
                }

                // Calculate new SV values with ambient adjustment
                $new_sv_values = $this->calculateAdjustedSV($current_sv_values, $ambient_change);
                
                // Apply the adjustment
                $success = false;
                if ($dry_run) {
                    if ($verbose && $this->output) {
                        $this->output->writeln("→ [DRY-RUN] Would adjust Line " . sprintf('%02d', $machine->line) . " {$position} by {$ambient_change}°C");
                    }
                    $success = true; // Consider dry-run as successful
                } else {
                    $success = $this->applySVAdjustment($machine, $position, $new_sv_values);
                    
                    if ($success) {
                        $total_adjustments_made++;
                        if ($verbose && $this->output) {
                            $this->output->writeln("✓ Adjusted Line " . sprintf('%02d', $machine->line) . " {$position}");
                        }
                    } else {
                        if ($debug && $this->output) {
                            $this->output->writeln("✗ Failed to adjust Line " . sprintf('%02d', $machine->line) . " {$position}");
                        }
                    }
                }

                // Store adjustment details for unified logging
                $adjustment_summary[] = [
                    'machine_name' => "Line " . sprintf('%02d', $machine->line),
                    'machine_ip' => $machine->ip_address,
                    'position' => $position,
                    'ambient_change' => $ambient_change,
                    'current_sv' => $current_sv_values,
                    'new_sv' => $new_sv_values,
                    'applied' => $success,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s')
                ];
            }
        }

        if ($verbose && $this->output) {
            if ($dry_run) {
                $this->output->writeln("→ [DRY-RUN] Would have made adjustments to " . count($adjustment_summary) . " machine positions");
            } else {
                $this->output->writeln("✓ Successfully adjusted {$total_adjustments_made} machine positions");
            }
        }

        // Return detailed adjustment information instead of logging
        return [
            'total_machines' => count($machines),
            'total_positions_processed' => count($adjustment_summary),
            'successful_adjustments' => $total_adjustments_made,
            'adjustments' => $adjustment_summary,
            'dry_run' => $dry_run
        ];
    }

    /**
     * Get machines eligible for ambient adjustment (non-loopback IPs)
     */
    protected function getEligibleMachines()
    {
        return InsStcMachine::where('ip_address', 'not like', '127.%')
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->orderBy('line')
            ->get();
    }

    /**
     * Check if there was a recent worker adjustment within 30 minutes
     */
    protected function hasRecentWorkerAdjustment($machine_id, $position)
    {
        $cutoff_time = Carbon::now()->subMinutes(30);
        
        $recent_adjustment = InsStcDSum::where('ins_stc_machine_id', $machine_id)
            ->where('position', $position)
            ->where('created_at', '>=', $cutoff_time)
            ->exists();
            
        return $recent_adjustment;
    }

    /**
     * Read current SV values from machine
     */
    protected function getCurrentSVValues($machine, $position)
    {
        try {
            $ip = $machine->ip_address;
            $port = 503;
            $unit_id = 1;
            
            // Build request to read current SV values
            $sv_r_request = InsStc::buildRegisterRequest($position . '_sv_r', $ip, $port, $unit_id);
            
            if (strpos($ip, '127.') !== false) {
                // For loopback addresses, return mock data (shouldn't happen due to filtering)
                return [75, 73, 68, 63, 58, 53, 43, 43];
            }
            
            // Execute Modbus request
            $client = new NonBlockingClient(['readTimeoutSec' => 2]);
            $sv_r_response = $client->sendRequests($sv_r_request);
            $sv_r_data = $sv_r_response->getData();
            
            // Extract SV values from response (8 sections)
            $sv_values = [];
            for ($i = 1; $i <= 8; $i++) {
                $key = $position . '_sv_r_' . $i;
                $sv_values[] = $sv_r_data[$key] ?? 0;
            }
            
            return $sv_values;
            
        } catch (\Throwable $e) {
            // Log error but don't throw - just return null to skip this machine
            return null;
        }
    }

    /**
     * Calculate new SV values with ambient adjustment using section-specific limits
     */
    protected function calculateAdjustedSV($current_sv_values, $ambient_change)
    {
        $new_sv_values = [];
        
        // Uniform adjustment: ambient_change * -1 (negative correlation)
        $sv_adjustment = $ambient_change * -1;
        
        foreach ($current_sv_values as $index => $current_sv) {
            $new_sv = $current_sv + $sv_adjustment;
            
            // Apply section-specific min/max validation
            $section_max = $this->svp_highs[$index] ?? 99;
            $section_min = $this->svp_lows[$index] ?? 30;
            
            $new_sv = max($section_min, min($section_max, $new_sv));
            
            $new_sv_values[] = round($new_sv, 0); // Round to integer
        }
        
        return $new_sv_values;
    }

    /**
     * Apply SV adjustment to machine using InsStcPush
     */
    protected function applySVAdjustment($machine, $position, $new_sv_values)
    {
        try {
            $push = new InsStcPush();
            
            // Send the new SV values as SVP (predicted setpoints)
            $push->send(
                'section_svp',
                $machine->ip_address,
                $position,
                $new_sv_values
            );
            
            // Apply the setpoints
            $push->send(
                'apply_svw',
                $machine->ip_address,
                $position,
                [true]
            );
            
            return true;
            
        } catch (\Throwable $e) {
            return false;
        }
    }
}