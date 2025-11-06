<?php

namespace App\Services\DWP;

class DwpPollingConfig
{
    public const POLL_INTERVAL_SECONDS = 1;
    public const MODBUS_TIMEOUT_SECONDS = 2;
    public const MODBUS_PORT = 503;

    // Cycle detection thresholds
    public const CYCLE_START_THRESHOLD = 2;
    public const TOE_HEEL_END_THRESHOLD = 0;
    public const SIDE_END_THRESHOLD = 0;
    public const END_HYSTERESIS_THRESHOLD = 2;

    // Value validation ranges
    public const GOOD_VALUE_MIN = 30;
    public const GOOD_VALUE_MAX = 45;

    // Cycle timing and safety
    public const CYCLE_TIMEOUT_SECONDS = 30;
    public const MIN_SAMPLES_PER_CYCLE = 3;
    public const MAX_BUFFER_SIZE = 100;
    public const MIN_END_COUNT_FOR_COMPLETION = 2;

    // Memory management
    public const MEMORY_CLEANUP_INTERVAL = 1000;
    public const STATS_DISPLAY_INTERVAL = 100;

    // Waveform normalization
    public const NORMALIZED_WAVEFORM_LENGTH = 30;

    /**
     * Get all configuration as an array
     */
    public static function toArray(): array
    {
        return [
            'poll_interval_seconds' => self::POLL_INTERVAL_SECONDS,
            'modbus_timeout_seconds' => self::MODBUS_TIMEOUT_SECONDS,
            'modbus_port' => self::MODBUS_PORT,
            'cycle_start_threshold' => self::CYCLE_START_THRESHOLD,
            'toe_heel_end_threshold' => self::TOE_HEEL_END_THRESHOLD,
            'side_end_threshold' => self::SIDE_END_THRESHOLD,
            'end_hysteresis_threshold' => self::END_HYSTERESIS_THRESHOLD,
            'good_value_min' => self::GOOD_VALUE_MIN,
            'good_value_max' => self::GOOD_VALUE_MAX,
            'cycle_timeout_seconds' => self::CYCLE_TIMEOUT_SECONDS,
            'min_samples_per_cycle' => self::MIN_SAMPLES_PER_CYCLE,
            'max_buffer_size' => self::MAX_BUFFER_SIZE,
            'min_end_count_for_completion' => self::MIN_END_COUNT_FOR_COMPLETION,
            'memory_cleanup_interval' => self::MEMORY_CLEANUP_INTERVAL,
            'stats_display_interval' => self::STATS_DISPLAY_INTERVAL,
            'normalized_waveform_length' => self::NORMALIZED_WAVEFORM_LENGTH,
        ];
    }

    /**
     * Validate if a reading value is within acceptable range
     */
    public static function isValidReading(int $value): bool
    {
        return $value >= self::GOOD_VALUE_MIN && $value <= self::GOOD_VALUE_MAX;
    }

    /**
     * Check if a value indicates cycle start
     */
    public static function isCycleStart(int $value): bool
    {
        return $value >= self::CYCLE_START_THRESHOLD;
    }

    /**
     * Check if values indicate cycle end (with hysteresis)
     */
    public static function isCycleEnd(int $toeHeelValue, int $sideValue): bool
    {
        return $toeHeelValue <= self::END_HYSTERESIS_THRESHOLD &&
               $sideValue <= self::END_HYSTERESIS_THRESHOLD;
    }
}
