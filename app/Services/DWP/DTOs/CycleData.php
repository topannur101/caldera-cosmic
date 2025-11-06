<?php

namespace App\Services\DWP\DTOs;

class CycleData
{
    public function __construct(
        public readonly string $line,
        public readonly string $machineName,
        public readonly string $position,
        public readonly array $toeHeelBuffer,
        public readonly array $sideBuffer,
        public readonly int $startTime,
        public readonly int $endTime,
        public readonly int $maxToeHeel,
        public readonly int $maxSide,
        public readonly bool $isValid,
    ) {}

    /**
     * Get the duration of the cycle in seconds
     */
    public function getDuration(): int
    {
        return $this->endTime - $this->startTime;
    }

    /**
     * Get the cycle key for identification
     */
    public function getCycleKey(): string
    {
        return "{$this->line}-{$this->machineName}-{$this->position}";
    }

    /**
     * Get the machine number (extracted from machine name)
     */
    public function getMachineNumber(): int
    {
        return (int) trim($this->machineName, "mc");
    }

    /**
     * Get the peak values as an array
     */
    public function getPeaks(): array
    {
        return [
            'toe_heel' => $this->maxToeHeel,
            'side' => $this->maxSide,
        ];
    }

    /**
     * Check if the cycle has sufficient data points
     */
    public function hasSufficientSamples(int $minSamples): bool
    {
        return count($this->toeHeelBuffer) >= $minSamples;
    }

    /**
     * Create from raw sensor data
     */
    public static function fromRawData(
        string $line,
        string $machineName,
        string $position,
        array $toeHeelBuffer,
        array $sideBuffer,
        int $startTime,
        int $endTime = null
    ): self {
        $endTime = $endTime ?? time();
        $maxToeHeel = max($toeHeelBuffer);
        $maxSide = max($sideBuffer);

        // Validate if both peaks are within acceptable range
        $isValid = $maxToeHeel >= 30 && $maxToeHeel <= 45 &&
                   $maxSide >= 30 && $maxSide <= 45;

        return new self(
            line: $line,
            machineName: $machineName,
            position: $position,
            toeHeelBuffer: $toeHeelBuffer,
            sideBuffer: $sideBuffer,
            startTime: $startTime,
            endTime: $endTime,
            maxToeHeel: $maxToeHeel,
            maxSide: $maxSide,
            isValid: $isValid,
        );
    }
}
