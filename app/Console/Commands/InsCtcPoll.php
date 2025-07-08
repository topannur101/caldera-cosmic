<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\InsCtcMachine;
use App\Models\InsCtcMetric;
use App\Models\InsCtcRecipe;
use Illuminate\Console\Command;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadCoilsBuilder;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;

class InsCtcPoll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-ctc-poll {--v : Verbose output} {--d : Debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll rubber thickness data from Modbus servers and save as aggregated batch metrics';

    // Configuration
    protected $batch_timeout = 60;        // seconds - same as old clump timeout
    protected $minimum_measurements = 10; // minimum measurements per batch (configurable)

    // State management arrays (per machine)
    protected $batch_buffers = [];        // Raw measurement data per machine
    protected $last_activity = [];       // Last measurement timestamp per machine
    protected $sensor_prev = [];         // Previous sensor readings per machine  
    protected $recipe_cache = [];        // Cached recipe targets
    protected $recipe_id_prev = [];      // Previous recipe ID per machine
    protected $st_cl_prev = [];          // Previous system time correction left
    protected $st_cr_prev = [];          // Previous system time correction right

    /**
     * Convert integer value to decimal format for thickness measurements
     */
    function convertToDecimal($value)
    {
        $value = (int) $value;
        $length = strlen((string) $value);

        if ($length == 3) {
            $decimal = substr((string) $value, 0, -2) . '.' . substr((string) $value, -2);
        } elseif ($length == 2) {
            $decimal = '0.' . (string) $value;
        } elseif ($length == 1) {
            $decimal = '0.0' . (string) $value;
        } else {
            $decimal = '0.00';
        }

        return (float) $decimal;
    }

    /**
     * Convert push time values to decimal format
     */
    function convertPushTime($value)
    {
        $value = (int) $value;
        $length = strlen((string) $value);

        if ($length == 3 || $length == 2) {
            $decimal = substr((string) $value, 0, -1) . '.' . substr((string) $value, -1);
        } elseif ($length == 1) {
            $decimal = '0.' . (string) $value;
        } else {
            $decimal = '0.0';
        }

        return (float) $decimal;
    }

    /**
     * Determine correction action based on push values
     * Returns: 0 = no action, 1 = thin, 2 = thick
     */
    function getActionCode($pushThin, $pushThick)
    {
        if ($pushThin > 0 && $pushThick == 0) {
            return 1; // thin action
        } elseif ($pushThin == 0 && $pushThick > 0) {
            return 2; // thick action
        }
        return 0; // no action
    }

    /**
     * Get recipe target thickness (middle of std_min and std_max)
     */
    function getRecipeTarget($recipe_id)
    {
        if (!isset($this->recipe_cache[$recipe_id])) {
            $recipe = InsCtcRecipe::find($recipe_id);
            if ($recipe) {
                // Use the model's computed target_thickness attribute
                $this->recipe_cache[$recipe_id] = $recipe->target_thickness;
                
                if ($this->option('verbose')) {
                    $this->comment("→ Recipe {$recipe_id} ({$recipe->name}) target: {$recipe->target_thickness} (range: {$recipe->std_min}-{$recipe->std_max})");
                }
            } else {
                $this->recipe_cache[$recipe_id] = null;
                $this->warn("⚠ Recipe {$recipe_id} not found");
            }
        }
        
        return $this->recipe_cache[$recipe_id];
    }

    /**
     * Calculate statistical metrics for a batch
     */
    function calculateBatchStatistics($batch_data, $target)
    {
        if (empty($batch_data)) {
            return null;
        }

        $left_values = [];
        $right_values = [];
        $left_errors = [];
        $right_errors = [];

        // Extract values and calculate errors
        foreach ($batch_data as $record) {
            $left = $record[4];   // position 4: left thickness
            $right = $record[5];  // position 5: right thickness
            
            $left_values[] = $left;
            $right_values[] = $right;
            
            if ($target !== null) {
                $left_errors[] = abs($left - $target);
                $right_errors[] = abs($right - $target);
            }
        }

        // Calculate averages
        $avg_left = array_sum($left_values) / count($left_values);
        $avg_right = array_sum($right_values) / count($right_values);
        $avg_combined = ($avg_left + $avg_right) / 2;

        // Calculate MAE (Mean Absolute Error)
        $mae_left = $target !== null ? array_sum($left_errors) / count($left_errors) : null;
        $mae_right = $target !== null ? array_sum($right_errors) / count($right_errors) : null;
        $mae_combined = ($mae_left !== null && $mae_right !== null) ? ($mae_left + $mae_right) / 2 : null;

        // Calculate Sample Standard Deviation (n-1)
        $ssd_left = $this->calculateSampleStandardDeviation($left_values);
        $ssd_right = $this->calculateSampleStandardDeviation($right_values);
        $ssd_combined = ($ssd_left + $ssd_right) / 2;

        // Calculate balance
        $balance = $avg_left - $avg_right;

        return [
            't_mae_left' => $mae_left,
            't_mae_right' => $mae_right,
            't_mae' => $mae_combined,
            't_ssd_left' => $ssd_left,
            't_ssd_right' => $ssd_right,
            't_ssd' => $ssd_combined,
            't_avg_left' => $avg_left,
            't_avg_right' => $avg_right,
            't_avg' => $avg_combined,
            't_balance' => $balance
        ];
    }

    /**
     * Calculate sample standard deviation (n-1 denominator)
     */
    function calculateSampleStandardDeviation($values)
    {
        $count = count($values);
        if ($count < 2) {
            return 0;
        }

        $mean = array_sum($values) / $count;
        $sum_squares = 0;

        foreach ($values as $value) {
            $sum_squares += pow($value - $mean, 2);
        }

        return sqrt($sum_squares / ($count - 1));
    }

    /**
     * Process completed batch and save to database
     */
    function processBatch($machine_id, $batch_data)
    {
        $batch_size = count($batch_data);
        $machine = InsCtcMachine::find($machine_id);

        if ($this->option('v')) {
            $this->comment("→ Processing batch for {$machine->name}: {$batch_size} measurements");
        }

        // Check minimum measurements requirement
        if ($batch_size < $this->minimum_measurements) {
            $this->warn("⚠ Batch discarded: {$batch_size} < {$this->minimum_measurements} minimum measurements (Machine: {$machine->name})");
            return;
        }

        // Get recipe target from first measurement in batch
        $first_record = $batch_data[0];
        $recipe_id = null;
        
        // Extract recipe_id from the batch context (you may need to store this separately)
        // For now, using the last known recipe_id for this machine
        if (isset($this->recipe_id_prev[$machine_id])) {
            $recipe_id = $this->recipe_id_prev[$machine_id];
        }

        $target = $recipe_id ? $this->getRecipeTarget($recipe_id) : null;

        // Calculate statistics
        $stats = $this->calculateBatchStatistics($batch_data, $target);
        
        if (!$stats) {
            $this->error("✗ Failed to calculate statistics for batch (Machine: {$machine->name})");
            return;
        }

        if ($this->option('d')) {
            $this->line("Statistics calculated:");
            $this->line("  MAE: L={$stats['t_mae_left']}, R={$stats['t_mae_right']}, C={$stats['t_mae']}");
            $this->line("  SSD: L={$stats['t_ssd_left']}, R={$stats['t_ssd_right']}, C={$stats['t_ssd']}");
            $this->line("  AVG: L={$stats['t_avg_left']}, R={$stats['t_avg_right']}, C={$stats['t_avg']}");
            $this->line("  Balance: {$stats['t_balance']}");
        }

        try {
            // Create batch metric record
            InsCtcMetric::create([
                'ins_ctc_machine_id' => $machine_id,
                'ins_rubber_batch_id' => null, // TODO: Implement batch detection logic
                'ins_ctc_recipe_id' => $recipe_id,
                'is_auto' => true, // Assuming automatic mode for polling
                't_mae_left' => round($stats['t_mae_left'], 2),
                't_mae_right' => round($stats['t_mae_right'], 2),
                't_mae' => round($stats['t_mae'], 2),
                't_ssd_left' => round($stats['t_ssd_left'], 2),
                't_ssd_right' => round($stats['t_ssd_right'], 2),
                't_ssd' => round($stats['t_ssd'], 2),
                't_avg_left' => round($stats['t_avg_left'], 2),
                't_avg_right' => round($stats['t_avg_right'], 2),
                't_avg' => round($stats['t_avg'], 2),
                't_balance' => round($stats['t_balance'], 2),
                'data' => $batch_data // JSON array of arrays
            ]);

            $this->info("✓ Batch saved: {$machine->name}, {$batch_size} measurements, Recipe {$recipe_id}");

        } catch (\Exception $e) {
            $this->error("✗ Failed to save batch: {$e->getMessage()}");
        }
    }

    /**
     * Add measurement to batch buffer
     */
    function addToBatch($machine_id, $metric)
    {
        $dt_now = Carbon::now()->format('Y-m-d H:i:s');
        
        // Detect sensor value changes (same logic as old system)
        $sensor_signature = $metric['sensor_left'] . $metric['st_correct_left'] . $metric['sensor_right'] . $metric['st_correct_right'];
        
        // Only process if sensor values changed AND at least one sensor is not zero
        if ($sensor_signature !== $this->sensor_prev[$machine_id] && ($metric['sensor_left'] || $metric['sensor_right'])) {
            
            // Convert sensor values
            $left_thickness = $this->convertToDecimal($metric['sensor_left']);
            $right_thickness = $this->convertToDecimal($metric['sensor_right']);
            
            // Determine actions (same logic as old system)
            $action_left = 0;
            $action_right = 0;
            
            $st_cl = $metric['st_correct_left'];
            $st_cr = $metric['st_correct_right'];
            
            // Check for correction actions
            if (($st_cl !== $this->st_cl_prev[$machine_id]) && $metric['is_correcting']) {
                $action_left = $this->getActionCode($metric['push_thin_left'], $metric['push_thick_left']);
                $this->st_cl_prev[$machine_id] = $st_cl;
            }
            
            if (($st_cr !== $this->st_cr_prev[$machine_id]) && $metric['is_correcting']) {
                $action_right = $this->getActionCode($metric['push_thin_right'], $metric['push_thick_right']);
                $this->st_cr_prev[$machine_id] = $st_cr;
            }
            
            // Add to batch buffer (array format: timestamp, is_correcting, action_left, action_right, left, right)
            $this->batch_buffers[$machine_id][] = [
                $dt_now,                           // 0: timestamp
                (bool) $metric['is_correcting'],   // 1: is_correcting
                $action_left,                      // 2: action_left (0,1,2)
                $action_right,                     // 3: action_right (0,1,2)
                $left_thickness,                   // 4: left thickness
                $right_thickness                   // 5: right thickness
            ];
            
            // Update last activity timestamp
            $this->last_activity[$machine_id] = Carbon::now();
            
            // Store recipe_id for batch processing
            $this->recipe_id_prev[$machine_id] = $metric['recipe_id'];
            
            // Update sensor signature
            $this->sensor_prev[$machine_id] = $sensor_signature;
            
            if ($this->option('d')) {
                $this->line("Measurement added: Machine {$machine_id}, L={$left_thickness}, R={$right_thickness}, Actions=({$action_left},{$action_right})");
            }
            
        } else {
            // Log filtered measurements
            if (!$metric['sensor_left'] && !$metric['sensor_right']) {
                if ($this->option('d')) {
                    $this->line("Zero reading filtered: ID {$machine_id}");
                }
            } else {
                if ($this->option('d')) {
                    $this->line("Consecutive reading filtered: ID {$machine_id}");
                }
            }
        }
    }

    /**
     * Check for batch timeouts and process completed batches
     */
    function checkBatchTimeouts()
    {
        $now = Carbon::now();
        
        foreach ($this->last_activity as $machine_id => $last_time) {
            if ($last_time && $now->diffInSeconds($last_time) >= $this->batch_timeout) {
                
                if (!empty($this->batch_buffers[$machine_id])) {
                    // Process the completed batch
                    $this->processBatch($machine_id, $this->batch_buffers[$machine_id]);
                    
                    // Clear batch buffer
                    $this->batch_buffers[$machine_id] = [];
                    $this->last_activity[$machine_id] = null;
                }
            }
        }
    }

    /**
     * Initialize state variables for all machines
     */
    function initializeState($machines)
    {
        foreach ($machines as $machine) {
            $this->batch_buffers[$machine->id] = [];
            $this->last_activity[$machine->id] = null;
            $this->sensor_prev[$machine->id] = null;
            $this->recipe_id_prev[$machine->id] = null;
            $this->st_cl_prev[$machine->id] = null;
            $this->st_cr_prev[$machine->id] = null;
        }
    }

    /**
     * Execute the console command
     */
    public function handle()
    {
        // Get all registered machines
        $machines = InsCtcMachine::all();
        $unit_id = 1;

        if ($machines->isEmpty()) {
            $this->error("✗ No machines found in database");
            return 1;
        }

        $this->info("✓ InsCtcPoll started - monitoring " . count($machines) . " machines");
        $this->info("✓ Configuration: {$this->batch_timeout}s timeout, {$this->minimum_measurements} min measurements");

        if ($this->option('v')) {
            $this->comment("Machines:");
            foreach ($machines as $machine) {
                $this->comment("  → {$machine->name} ({$machine->ip_address})");
            }
        }

        // Initialize state
        $this->initializeState($machines);

        // Main polling loop
        while (true) {
            $dt_now = Carbon::now()->format('Y-m-d H:i:s');
            
            // Poll all machines
            foreach ($machines as $machine) {
                
                if ($this->option('v')) {
                    $this->comment("→ Polling {$machine->name} ({$machine->ip_address})");
                }
                
                try {
                    // Build Modbus requests (same as old system)
                    $fc2 = ReadCoilsBuilder::newReadInputDiscretes('tcp://' . $machine->ip_address . ':502', $unit_id)
                        ->coil(0, 'is_correcting')
                        ->build();

                    $fc3 = ReadRegistersBuilder::newReadHoldingRegisters('tcp://' . $machine->ip_address . ':502', $unit_id)
                        ->int16(0, 'sensor_left')
                        ->int16(1, 'sensor_right')
                        // ->int16(2, 'unknown') // missing register
                        ->int16(3, 'recipe_id')
                        ->int16(4, 'push_thin_left')
                        ->int16(5, 'push_thick_left')
                        ->int16(6, 'push_thin_right')
                        ->int16(7, 'push_thick_right')
                        ->int16(8, 'st_correct_left')
                        ->int16(9, 'st_correct_right')
                        ->build();

                    // Execute Modbus requests
                    $fc2_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc2);
                    $fc3_response = (new NonBlockingClient(['readTimeoutSec' => 2]))->sendRequests($fc3);
                    
                    $fc2_data = $fc2_response->getData();
                    $fc3_data = $fc3_response->getData();

                    // Prepare metric data
                    $metric = [
                        'sensor_left' => $fc3_data['sensor_left'],
                        'sensor_right' => $fc3_data['sensor_right'],
                        'recipe_id' => $fc3_data['recipe_id'],
                        'is_correcting' => $fc2_data['is_correcting'],
                        'push_thin_left' => $fc3_data['push_thin_left'],
                        'push_thick_left' => $fc3_data['push_thick_left'],
                        'push_thin_right' => $fc3_data['push_thin_right'],
                        'push_thick_right' => $fc3_data['push_thick_right'],
                        'st_correct_left' => $fc3_data['st_correct_left'],
                        'st_correct_right' => $fc3_data['st_correct_right'],
                    ];

                    if ($this->option('d')) {
                        $this->line("");
                        $this->line("Raw data from {$machine->name}");
                        $this->line("IP address {$machine->ip_address}:");
                        $this->table(['Field', 'Value'], [
                            ['Sensor Left', $metric['sensor_left']],
                            ['Sensor Right', $metric['sensor_right']],
                            ['Recipe ID', $metric['recipe_id']],
                            ['Is Correcting', $metric['is_correcting'] ? 'Yes' : 'No'],
                            ['Push Thin L', $metric['push_thin_left']],
                            ['Push Thick L', $metric['push_thick_left']],
                            ['Push Thin R', $metric['push_thin_right']],
                            ['Push Thick R', $metric['push_thick_right']]
                        ]);
                    }

                    // Add to batch buffer
                    $this->addToBatch($machine->id, $metric);

                } catch (\Throwable $th) {
                    $this->error("✗ Error polling {$machine->name} ({$machine->ip_address}): " . $th->getMessage());
                }
            }
            
            // Check for batch timeouts and process completed batches
            $this->checkBatchTimeouts();
            
            // Sleep before next iteration
            sleep(1);
        }
    }
}