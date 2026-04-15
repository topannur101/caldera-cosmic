<?php

namespace App\Services;

use App\Models\UptimeLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UptimeCalculatorService
{
    private const TIMEOUT_THRESHOLD_SECONDS = 300; // 5 minutes
    
    public function calculateStats(string $projectName, Carbon $start, Carbon $end): array
    {
        $windowStart = $start->copy();
        $windowEnd = Carbon::now()->min($end);

        if ($windowEnd->lessThanOrEqualTo($windowStart)) {
            return $this->getEmptyStats();
        }

        $previousLog = $this->getLastLogBeforeOrAtStart($projectName, $windowStart);
        $windowLogs = $this->getLogsWithinWindow($projectName, $windowStart, $windowEnd);

        if ($previousLog === null && $windowLogs->isEmpty()) {
            return $this->getEmptyStats();
        }

        $timeline = $this->buildTimeline($windowStart, $windowLogs, $previousLog);
        $durations = $this->calculateDurationsFromTimeline($timeline, $windowEnd);

        $onlineDuration = $durations['online'];
        $offlineDuration = $durations['offline'];
        $timeoutDuration = $durations['timeout'];
        
        $totalDuration = $onlineDuration + $offlineDuration + $timeoutDuration;
        
        return [
            'online_duration' => $onlineDuration,
            'offline_duration' => $offlineDuration,
            'timeout_duration' => $timeoutDuration,
            'online_percentage' => $this->calculatePercentage($onlineDuration, $totalDuration),
            'offline_percentage' => $this->calculatePercentage($offlineDuration, $totalDuration),
            'timeout_percentage' => $this->calculatePercentage($timeoutDuration, $totalDuration),
        ];
    }

    private function getLastLogBeforeOrAtStart(string $projectName, Carbon $start): ?UptimeLog
    {
        return UptimeLog::where('project_name', $projectName)
            ->where('checked_at', '<=', $start)
            ->orderBy('checked_at', 'desc')
            ->first();
    }

    private function getLogsWithinWindow(string $projectName, Carbon $start, Carbon $end): Collection
    {
        return UptimeLog::where('project_name', $projectName)
            ->where('checked_at', '>', $start)
            ->where('checked_at', '<=', $end)
            ->orderBy('checked_at', 'asc')
            ->get();
    }

    private function buildTimeline(Carbon $windowStart, Collection $windowLogs, ?UptimeLog $previousLog): Collection
    {
        $timeline = collect();

        if ($previousLog !== null) {
            $timeline->push([
                'checked_at' => $windowStart->copy(),
                'status' => $previousLog->status,
            ]);
        }

        foreach ($windowLogs as $log) {
            $timeline->push([
                'checked_at' => $log->checked_at->copy(),
                'status' => $log->status,
            ]);
        }

        return $timeline->sortBy('checked_at')->values();
    }

    private function calculateDurationsFromTimeline(Collection $timeline, Carbon $windowEnd): array
    {
        $durations = [
            'online' => 0,
            'offline' => 0,
            'timeout' => 0,
        ];

        $currentStatus = null;
        $segmentStart = null;

        foreach ($timeline as $point) {
            $pointTime = $point['checked_at'];
            $pointStatus = (string) ($point['status'] ?? '');

            if ($pointTime->greaterThan($windowEnd)) {
                break;
            }

            if ($currentStatus === null) {
                $currentStatus = $pointStatus;
                $segmentStart = $pointTime->copy();
                continue;
            }

            if ($pointTime->lessThanOrEqualTo($segmentStart)) {
                if ($pointStatus !== $currentStatus) {
                    $currentStatus = $pointStatus;
                }
                continue;
            }

            if ($pointStatus === $currentStatus) {
                continue;
            }

            $segmentDuration = $segmentStart->diffInSeconds($pointTime);
            $this->addSegmentDuration($durations, $currentStatus, $segmentDuration);

            $currentStatus = $pointStatus;
            $segmentStart = $pointTime->copy();
        }

        if ($currentStatus !== null && $segmentStart !== null && $windowEnd->greaterThan($segmentStart)) {
            $segmentDuration = $segmentStart->diffInSeconds($windowEnd);
            $this->addSegmentDuration($durations, $currentStatus, $segmentDuration);
        }

        return $durations;
    }

    private function addSegmentDuration(array &$durations, string $status, int $duration): void
    {
        if ($duration <= 0) {
            return;
        }

        if ($status === 'online') {
            $durations['online'] += $duration;
            return;
        }

        if ($duration >= self::TIMEOUT_THRESHOLD_SECONDS) {
            $durations['offline'] += $duration;
            return;
        }

        $durations['timeout'] += $duration;
    }
    
    private function calculatePercentage(int $part, int $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : 0;
    }
    
    private function getEmptyStats(): array
    {
        return [
            'online_duration' => 0,
            'offline_duration' => 0,
            'timeout_duration' => 0,
            'online_percentage' => 0,
            'offline_percentage' => 0,
            'timeout_percentage' => 0,
        ];
    }
}