<?php

namespace App\Services\DWP\DTOs;

class ModbusReading
{
    public function __construct(
        public readonly string $line,
        public readonly string $machineName,
        public readonly int $toeHeelLeft,
        public readonly int $toeHeelRight,
        public readonly int $sideLeft,
        public readonly int $sideRight,
        public readonly int $timestamp,
        public readonly bool $successful = true,
        public readonly ?string $error = null,
    ) {}

    /**
     * Get reading data for a specific position (L or R)
     */
    public function getPositionData(string $position): array
    {
        return match (strtoupper($position)) {
            'L' => [
                'toe_heel' => $this->toeHeelLeft,
                'side' => $this->sideLeft,
            ],
            'R' => [
                'toe_heel' => $this->toeHeelRight,
                'side' => $this->sideRight,
            ],
            default => throw new \InvalidArgumentException("Invalid position: {$position}. Must be 'L' or 'R'"),
        };
    }

    /**
     * Get all position data
     */
    public function getAllPositionData(): array
    {
        return [
            'L' => $this->getPositionData('L'),
            'R' => $this->getPositionData('R'),
        ];
    }

    /**
     * Check if any sensor has active reading (above threshold)
     */
    public function hasActiveReading(int $threshold = 2): bool
    {
        return $this->toeHeelLeft >= $threshold ||
               $this->toeHeelRight >= $threshold ||
               $this->sideLeft >= $threshold ||
               $this->sideRight >= $threshold;
    }

    /**
     * Check if specific position has active reading
     */
    public function hasActiveReadingForPosition(string $position, int $threshold = 2): bool
    {
        $data = $this->getPositionData($position);
        return $data['toe_heel'] >= $threshold || $data['side'] >= $threshold;
    }

    /**
     * Create a failed reading instance
     */
    public static function failed(string $line, string $machineName, string $error): self
    {
        return new self(
            line: $line,
            machineName: $machineName,
            toeHeelLeft: 0,
            toeHeelRight: 0,
            sideLeft: 0,
            sideRight: 0,
            timestamp: time(),
            successful: false,
            error: $error,
        );
    }

    /**
     * Create from Modbus response data
     */
    public static function fromModbusResponse(
        string $line,
        string $machineName,
        array $response
    ): self {
        return new self(
            line: $line,
            machineName: $machineName,
            toeHeelLeft: (int) $response['toe_heel_left'],
            toeHeelRight: (int) $response['toe_heel_right'],
            sideLeft: (int) $response['side_left'],
            sideRight: (int) $response['side_right'],
            timestamp: time(),
        );
    }

    /**
     * Get a summary string for logging
     */
    public function getSummary(): string
    {
        if (!$this->successful) {
            return "FAILED: {$this->error}";
        }

        return "TH-L:{$this->toeHeelLeft} TH-R:{$this->toeHeelRight} S-L:{$this->sideLeft} S-R:{$this->sideRight}";
    }

    /**
     * Convert to array for logging or debugging
     */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'machine_name' => $this->machineName,
            'toe_heel_left' => $this->toeHeelLeft,
            'toe_heel_right' => $this->toeHeelRight,
            'side_left' => $this->sideLeft,
            'side_right' => $this->sideRight,
            'timestamp' => $this->timestamp,
            'successful' => $this->successful,
            'error' => $this->error,
        ];
    }
}
