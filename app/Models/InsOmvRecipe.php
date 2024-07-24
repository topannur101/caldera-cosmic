<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsOmvRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'capture_points',
        'steps'
    ];

    protected $casts = [
        'capture_points' => 'array',
        'steps' => 'array',
    ];

    public function capturePointsCount()
    {
        $capture_points = json_decode($this->capture_points ?? '{}', true);
        return count($capture_points);
    }

    public function stepsCount()
    {
        $steps = json_decode($this->steps ?? '{}', true);
        return count($steps);
    }

    public function durationSum()
    {
        $steps = json_decode($this->steps ?? '{}', true);
        $totalDuration = 0;

        foreach ($steps as $step) {
            if (isset($step['duration'])) {
                $totalDuration += $step['duration'];
            }
        }
        return $totalDuration;
    }

    public function durationSumFormatted()
{
    $totalDuration = $this->durationSum();
    $minutes = floor($totalDuration / 60);
    $seconds = $totalDuration % 60;

    return sprintf('%02d:%02d', $minutes, $seconds);
}


}
