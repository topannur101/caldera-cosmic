<?php

namespace App\Services\DWP;

use App\Models\InsDwpCount;
use App\Services\DWP\DTOs\CycleData;
use App\Services\DWP\DTOs\ModbusReading;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DwpDataService
{
    private array $lastCumulativeValues = [];
    private WaveformNormalizer $waveformNormalizer;

    public function __construct(WaveformNormalizer $waveformNormalizer = null)
    {
        $this->waveformNormalizer = $waveformNormalizer ?? new WaveformNormalizer();
    }

    /**
     * Initialize last cumulative values from database
     */
    public function initializeLastValues(Collection $devices): void
    {
        foreach ($devices as $device) {
            foreach ($device->getLines() as $line) {
                $lastCount = InsDwpCount::latestForLine($line);
                $this->lastCumulativeValues[$line] = $lastCount ? $lastCount->count : 0;
            }
        }
    }

    /**
     * Save a completed cycle to database
     */
    public function saveCycle(CycleData $cycleData): bool
    {
        if (!$cycleData->isValid) {
            Log::debug('Skipping invalid cycle', [
                'cycle_key' => $cycleData->getCycleKey(),
                'peaks' => $cycleData->getPeaks(),
            ]);
            return false;
        }

        if (!$cycleData->hasSufficientSamples(DwpPollingConfig::MIN_SAMPLES_PER_CYCLE)) {
            Log::debug('Skipping cycle with insufficient samples', [
                'cycle_key' => $cycleData->getCycleKey(),
                'samples' => count($cycleData->toeHeelBuffer),
                'minimum_required' => DwpPollingConfig::MIN_SAMPLES_PER_CYCLE,
            ]);
            return false;
        }

        try {
            return DB::transaction(function () use ($cycleData) {
                $lastCumulative = $this->lastCumulativeValues[$cycleData->line] ?? 0;
                $newCumulative = $lastCumulative + 1;

                // Normalize waveforms
                $normalizedData = [
                    $this->waveformNormalizer->normalize($cycleData->toeHeelBuffer),
                    $this->waveformNormalizer->normalize($cycleData->sideBuffer),
                ];

                $count = new InsDwpCount([
                    'mechine' => $cycleData->getMachineNumber(),
                    'line' => $cycleData->line,
                    'count' => $newCumulative,
                    'pv' => json_encode($normalizedData),
                    'position' => $cycleData->position,
                    'duration' => $cycleData->getDuration(),
                    'incremental' => 1,
                    'std_error' => json_encode([0, 0]),
                ]);

                $count->save();

                // Update in-memory cache
                $this->lastCumulativeValues[$cycleData->line] = $newCumulative;

                Log::info('Cycle saved successfully', [
                    'cycle_key' => $cycleData->getCycleKey(),
                    'cumulative_count' => $newCumulative,
                    'duration' => $cycleData->getDuration(),
                    'peaks' => $cycleData->getPeaks(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to save cycle', [
                'cycle_key' => $cycleData->getCycleKey(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get the latest cumulative count for a line
     */
    public function getLastCumulativeValue(string $line): int
    {
        return $this->lastCumulativeValues[$line] ?? 0;
    }

    /**
     * Update last cumulative value for a line
     */
    public function setLastCumulativeValue(string $line, int $value): void
    {
        $this->lastCumulativeValues[$line] = $value;
    }

    /**
     * Get statistics for a specific line
     */
    public function getLineStats(string $line): array
    {
        $latestCount = InsDwpCount::latestForLine($line);
        $todayCount = InsDwpCount::todayCountForLine($line);

        return [
            'line' => $line,
            'latest_count' => $latestCount?->count ?? 0,
            'today_incremental' => $todayCount,
            'last_updated' => $latestCount?->created_at?->toISOString(),
            'cached_cumulative' => $this->lastCumulativeValues[$line] ?? 0,
        ];
    }

    /**
     * Get overall statistics
     */
    public function getOverallStats(): array
    {
        return [
            'total_lines' => count($this->lastCumulativeValues),
            'total_cumulative' => array_sum($this->lastCumulativeValues),
            'lines_summary' => array_map(
                fn($line) => $this->getLineStats($line),
                array_keys($this->lastCumulativeValues)
            ),
        ];
    }

    /**
     * Clean up old data (optional maintenance method)
     */
    public function cleanup(Collection $activeDevices): void
    {
        // Get active lines
        $activeLines = $activeDevices->flatMap(function ($device) {
            return $device->getLines();
        })->unique()->toArray();

        // Remove inactive lines from cache
        $this->lastCumulativeValues = array_intersect_key(
            $this->lastCumulativeValues,
            array_flip($activeLines)
        );

        Log::debug('Data service cleanup completed', [
            'active_lines' => count($activeLines),
            'cached_lines' => count($this->lastCumulativeValues),
        ]);
    }

    /**
     * Batch save multiple cycles (for performance optimization)
     */
    public function saveCycles(array $cycleDataArray): int
    {
        $savedCount = 0;

        DB::transaction(function () use ($cycleDataArray, &$savedCount) {
            foreach ($cycleDataArray as $cycleData) {
                if ($this->saveCycle($cycleData)) {
                    $savedCount++;
                }
            }
        });

        return $savedCount;
    }

    /**
     * Validate cycle data before saving
     */
    public function validateCycleData(CycleData $cycleData): array
    {
        $errors = [];

        if (empty($cycleData->line)) {
            $errors[] = 'Line is required';
        }

        if (empty($cycleData->machineName)) {
            $errors[] = 'Machine name is required';
        }

        if (!in_array($cycleData->position, ['L', 'R'])) {
            $errors[] = 'Position must be L or R';
        }

        if (empty($cycleData->toeHeelBuffer)) {
            $errors[] = 'Toe heel buffer is empty';
        }

        if (empty($cycleData->sideBuffer)) {
            $errors[] = 'Side buffer is empty';
        }

        if (count($cycleData->toeHeelBuffer) !== count($cycleData->sideBuffer)) {
            $errors[] = 'Toe heel and side buffers must have same length';
        }

        if ($cycleData->startTime >= $cycleData->endTime) {
            $errors[] = 'Invalid time range';
        }

        return $errors;
    }

    /**
     * Export cycle data for analysis
     */
    public function exportCycleData(string $line, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return InsDwpCount::where('line', $line)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get()
            ->map(function ($count) {
                return [
                    'id' => $count->id,
                    'machine' => $count->mechine,
                    'line' => $count->line,
                    'position' => $count->position,
                    'count' => $count->count,
                    'duration' => $count->duration,
                    'waveform_data' => json_decode($count->pv, true),
                    'created_at' => $count->created_at->toISOString(),
                ];
            });
    }
}
