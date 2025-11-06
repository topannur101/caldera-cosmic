<?php

namespace App\Services\DWP;

use App\Services\DWP\DTOs\CycleData;
use App\Services\DWP\DTOs\ModbusReading;

class CycleStateMachine
{
    private array $cycleStates = [];
    private DwpPollingConfig $config;

    public function __construct(DwpPollingConfig $config = null)
    {
        $this->config = $config ?? new DwpPollingConfig();
    }

    /**
     * Process a modbus reading and update cycle state
     */
    public function processReading(ModbusReading $reading): ?CycleData
    {
        $completedCycles = [];

        foreach (['L', 'R'] as $position) {
            $cycleData = $this->processPositionReading($reading, $position);
            if ($cycleData) {
                $completedCycles[] = $cycleData;
            }
        }

        // Return the first completed cycle (if any)
        return $completedCycles[0] ?? null;
    }

    /**
     * Process reading for a specific position (L or R)
     */
    private function processPositionReading(ModbusReading $reading, string $position): ?CycleData
    {
        $cycleKey = $this->getCycleKey($reading->line, $reading->machineName, $position);
        $positionData = $reading->getPositionData($position);
        $toeHeelValue = $positionData['toe_heel'];
        $sideValue = $positionData['side'];

        // Initialize state if not exists
        if (!isset($this->cycleStates[$cycleKey])) {
            $this->cycleStates[$cycleKey] = $this->createIdleState();
        }

        $state = &$this->cycleStates[$cycleKey];

        // Handle timeout
        if ($this->isStateTimedOut($state)) {
            $this->resetStateToIdle($state);
            return null;
        }

        return match ($state['state']) {
            'idle' => $this->handleIdleState($state, $toeHeelValue, $sideValue, $reading->timestamp),
            'active' => $this->handleActiveState($state, $toeHeelValue, $sideValue, $reading, $position),
            default => null,
        };
    }

    /**
     * Handle idle state - waiting for cycle start
     */
    private function handleIdleState(array &$state, int $toeHeelValue, int $sideValue, int $timestamp): ?CycleData
    {
        if (DwpPollingConfig::isCycleStart($toeHeelValue) || DwpPollingConfig::isCycleStart($sideValue)) {
            $state = [
                'state' => 'active',
                'start_time' => $timestamp,
                'th_buffer' => [$toeHeelValue],
                'side_buffer' => [$sideValue],
                'end_count' => 0,
            ];
        }

        return null;
    }

    /**
     * Handle active state - collecting data and detecting cycle end
     */
    private function handleActiveState(array &$state, int $toeHeelValue, int $sideValue, ModbusReading $reading, string $position): ?CycleData
    {
        $shouldEnd = false;

        // Debounced end condition
        if (DwpPollingConfig::isCycleEnd($toeHeelValue, $sideValue)) {
            $state['end_count']++;
            $shouldEnd = $state['end_count'] >= DwpPollingConfig::MIN_END_COUNT_FOR_COMPLETION;
        } else {
            // Value is active, buffer it
            $state['th_buffer'][] = $toeHeelValue;
            $state['side_buffer'][] = $sideValue;
            $state['end_count'] = 0;
        }

        if ($shouldEnd) {
            return $this->completeCycle($state, $reading, $position);
        }

        // Prevent buffer overflow
        if (count($state['th_buffer']) > DwpPollingConfig::MAX_BUFFER_SIZE) {
            $this->resetStateToIdle($state);
        }

        return null;
    }

    /**
     * Complete a cycle and create CycleData
     */
    private function completeCycle(array &$state, ModbusReading $reading, string $position): ?CycleData
    {
        if (count($state['th_buffer']) < DwpPollingConfig::MIN_SAMPLES_PER_CYCLE) {
            $this->resetStateToIdle($state);
            return null;
        }

        // Add final zero values for clean cutoff
        $state['th_buffer'][] = 0;
        $state['side_buffer'][] = 0;

        $cycleData = CycleData::fromRawData(
            line: $reading->line,
            machineName: $reading->machineName,
            position: $position,
            toeHeelBuffer: $state['th_buffer'],
            sideBuffer: $state['side_buffer'],
            startTime: $state['start_time'],
            endTime: $reading->timestamp
        );

        $this->resetStateToIdle($state);
        return $cycleData;
    }

    /**
     * Check if state has timed out
     */
    private function isStateTimedOut(array $state): bool
    {
        return $state['state'] !== 'idle' &&
               (time() - ($state['start_time'] ?? 0)) > DwpPollingConfig::CYCLE_TIMEOUT_SECONDS;
    }

    /**
     * Create idle state structure
     */
    private function createIdleState(): array
    {
        return ['state' => 'idle'];
    }

    /**
     * Reset state to idle
     */
    private function resetStateToIdle(array &$state): void
    {
        $state = $this->createIdleState();
    }

    /**
     * Get cycle key for state tracking
     */
    private function getCycleKey(string $line, string $machineName, string $position): string
    {
        return "{$line}-{$machineName}-{$position}";
    }

    /**
     * Clean up stale cycle states
     */
    public function cleanup(array $activeCycleKeys): void
    {
        $this->cycleStates = array_intersect_key(
            $this->cycleStates,
            array_flip($activeCycleKeys)
        );
    }

    /**
     * Get current cycle states (for debugging)
     */
    public function getCycleStates(): array
    {
        return $this->cycleStates;
    }
}
