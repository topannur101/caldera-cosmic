<?php

namespace App\Services\DWP;

class WaveformNormalizer
{
    private int $targetLength;

    public function __construct(int $targetLength = null)
    {
        $this->targetLength = $targetLength ?? DwpPollingConfig::NORMALIZED_WAVEFORM_LENGTH;
    }

    /**
     * Normalize a waveform to a fixed length using linear interpolation
     */
    public function normalize(array $buffer): array
    {
        $currentLength = count($buffer);

        if ($currentLength === 0) {
            return array_fill(0, $this->targetLength, 0);
        }

        if ($currentLength === $this->targetLength) {
            return $buffer;
        }

        return $this->interpolate($buffer, $this->targetLength);
    }

    /**
     * Perform linear interpolation to resize the buffer
     */
    private function interpolate(array $buffer, int $targetLength): array
    {
        $currentLength = count($buffer);
        $normalized = [];

        for ($i = 0; $i < $targetLength; $i++) {
            $ratio = $i / ($targetLength - 1);
            $index = $ratio * ($currentLength - 1);
            $floor = (int) floor($index);
            $ceil = min($floor + 1, $currentLength - 1);
            $weight = $index - $floor;

            if ($floor === $ceil) {
                $normalized[] = (int) $buffer[$floor];
            } else {
                $interpolated = $buffer[$floor] * (1 - $weight) + $buffer[$ceil] * $weight;
                $normalized[] = (int) round($interpolated);
            }
        }

        return $normalized;
    }

    /**
     * Normalize multiple waveforms to the same length
     */
    public function normalizeMultiple(array $waveforms): array
    {
        return array_map([$this, 'normalize'], $waveforms);
    }

    /**
     * Apply smoothing filter to reduce noise
     */
    public function smooth(array $buffer, int $windowSize = 3): array
    {
        if ($windowSize < 2 || count($buffer) < $windowSize) {
            return $buffer;
        }

        $smoothed = [];
        $halfWindow = intval($windowSize / 2);

        for ($i = 0; $i < count($buffer); $i++) {
            $sum = 0;
            $count = 0;

            $start = max(0, $i - $halfWindow);
            $end = min(count($buffer) - 1, $i + $halfWindow);

            for ($j = $start; $j <= $end; $j++) {
                $sum += $buffer[$j];
                $count++;
            }

            $smoothed[] = (int) round($sum / $count);
        }

        return $smoothed;
    }

    /**
     * Remove outliers using median filter
     */
    public function removeOutliers(array $buffer, int $windowSize = 5): array
    {
        if ($windowSize < 3 || count($buffer) < $windowSize) {
            return $buffer;
        }

        $filtered = [];
        $halfWindow = intval($windowSize / 2);

        for ($i = 0; $i < count($buffer); $i++) {
            $window = [];
            $start = max(0, $i - $halfWindow);
            $end = min(count($buffer) - 1, $i + $halfWindow);

            for ($j = $start; $j <= $end; $j++) {
                $window[] = $buffer[$j];
            }

            sort($window);
            $median = $window[intval(count($window) / 2)];
            $filtered[] = $median;
        }

        return $filtered;
    }

    /**
     * Calculate waveform statistics
     */
    public function getStatistics(array $buffer): array
    {
        if (empty($buffer)) {
            return [
                'min' => 0,
                'max' => 0,
                'mean' => 0,
                'median' => 0,
                'std_dev' => 0,
                'peak_to_peak' => 0,
                'rms' => 0,
            ];
        }

        $sorted = $buffer;
        sort($sorted);

        $min = min($buffer);
        $max = max($buffer);
        $mean = array_sum($buffer) / count($buffer);
        $median = $sorted[intval(count($sorted) / 2)];

        // Calculate standard deviation
        $variance = 0;
        foreach ($buffer as $value) {
            $variance += pow($value - $mean, 2);
        }
        $stdDev = sqrt($variance / count($buffer));

        // Calculate RMS (Root Mean Square)
        $sumSquares = array_sum(array_map(fn($x) => $x * $x, $buffer));
        $rms = sqrt($sumSquares / count($buffer));

        return [
            'min' => $min,
            'max' => $max,
            'mean' => round($mean, 2),
            'median' => $median,
            'std_dev' => round($stdDev, 2),
            'peak_to_peak' => $max - $min,
            'rms' => round($rms, 2),
        ];
    }

    /**
     * Detect peaks in the waveform
     */
    public function detectPeaks(array $buffer, int $minHeight = 0, int $minDistance = 1): array
    {
        $peaks = [];
        $bufferLength = count($buffer);

        for ($i = 1; $i < $bufferLength - 1; $i++) {
            $current = $buffer[$i];
            $prev = $buffer[$i - 1];
            $next = $buffer[$i + 1];

            // Check if current point is a local maximum
            if ($current > $prev && $current > $next && $current >= $minHeight) {
                // Check minimum distance constraint
                $tooClose = false;
                foreach ($peaks as $existingPeak) {
                    if (abs($i - $existingPeak['index']) < $minDistance) {
                        $tooClose = true;
                        break;
                    }
                }

                if (!$tooClose) {
                    $peaks[] = [
                        'index' => $i,
                        'value' => $current,
                        'prominence' => $this->calculateProminence($buffer, $i),
                    ];
                }
            }
        }

        // Sort peaks by value (highest first)
        usort($peaks, fn($a, $b) => $b['value'] <=> $a['value']);

        return $peaks;
    }

    /**
     * Calculate the prominence of a peak
     */
    private function calculateProminence(array $buffer, int $peakIndex): float
    {
        $peakValue = $buffer[$peakIndex];
        $leftMin = $peakValue;
        $rightMin = $peakValue;

        // Find minimum to the left
        for ($i = $peakIndex - 1; $i >= 0; $i--) {
            $leftMin = min($leftMin, $buffer[$i]);
        }

        // Find minimum to the right
        for ($i = $peakIndex + 1; $i < count($buffer); $i++) {
            $rightMin = min($rightMin, $buffer[$i]);
        }

        return $peakValue - max($leftMin, $rightMin);
    }

    /**
     * Validate waveform data
     */
    public function validate(array $buffer): array
    {
        $errors = [];

        if (empty($buffer)) {
            $errors[] = 'Waveform buffer is empty';
            return $errors;
        }

        // Check for non-numeric values
        foreach ($buffer as $index => $value) {
            if (!is_numeric($value)) {
                $errors[] = "Non-numeric value at index {$index}: {$value}";
            }
        }

        // Check for extremely large values that might indicate sensor errors
        $stats = $this->getStatistics($buffer);
        if ($stats['max'] > 10000) {
            $errors[] = "Extremely large value detected: {$stats['max']}";
        }

        // Check for negative values if they shouldn't exist
        if ($stats['min'] < 0) {
            $errors[] = "Negative value detected: {$stats['min']}";
        }

        return $errors;
    }

    /**
     * Set target length for normalization
     */
    public function setTargetLength(int $length): void
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Target length must be positive');
        }

        $this->targetLength = $length;
    }

    /**
     * Get current target length
     */
    public function getTargetLength(): int
    {
        return $this->targetLength;
    }
}
