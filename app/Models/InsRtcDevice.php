<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class InsRtcDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'line',
        'ip_address'
    ];

    public function ins_rtc_clumps(): HasMany
    {
        return $this->hasMany(InsRtcClump::class);
    }

    public function ins_rtc_metrics(): HasManyThrough
    {
        return $this->hasManyThrough(InsRtcMetric::class, InsRtcClump::class);
    }

    public function is_online(): bool
    {
        $latestMetric = $this->ins_rtc_metrics()->latest('dt_client')->first();

        if ($latestMetric) {
            $now = Carbon::now();
            $dt_client = Carbon::parse($latestMetric->dt_client);
            
            return $dt_client->greaterThanOrEqualTo($now->subMinutes(60));
        }

        return false;
    }

    public function total_time(): int
    {
        $metrics = $this->ins_rtc_metrics()->get();

        if(!$metrics->isEmpty()) {
            $min = Carbon::parse($metrics->min('dt_client'));
            $max = Carbon::parse($metrics->max('dt_client'));
            return $min->diffInSeconds($max);
        }

        return 0;
    }

    public function durations(): array
    {
        $durations = [];
        $clumps = $this->ins_rtc_clumps()->get();
        
        foreach ($clumps as $clump) {
            $metrics = $clump->ins_rtc_metrics()->get();
    
            if (!$metrics->isEmpty()) {
                $min = Carbon::parse($metrics->min('dt_client'));
                $max = Carbon::parse($metrics->max('dt_client'));
    
                $durations[] = $min->diffInSeconds($max);
            }
        }

        return $durations;
    }

    public function active_time(): int
    {
        return (int) array_sum($this->durations());
    }

    public function passive_time(): int
    {
        return (int) ($this->total_time() - $this->active_time());
    }

    public function avg_clump_duration(): int
    {
        $durations = $this->durations();
    
        // Check if the array is not empty to avoid division by zero
        if (empty($durations)) {
            return 0;
        }
    
        // Calculate the average duration
        $average = array_sum($durations) / count($durations);
    
        return (int) $average;
    }
}
