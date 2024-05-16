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
        $min = $this->ins_rtc_metrics()->min('dt_client');
        $max = $this->ins_rtc_metrics()->max('dt_client');

        if ($min && $max) {
            return Carbon::parse($min)->diffInSeconds(Carbon::parse($max));
        }

        return 0;
    }

    public function durations(): array
    {
        $durations = [];
        $clumps = $this->ins_rtc_clumps()->with(['ins_rtc_metrics' => function($query) {
            $query->selectRaw('MIN(dt_client) as min_dt, MAX(dt_client) as max_dt, ins_rtc_clump_id')
                  ->groupBy('ins_rtc_clump_id');
        }])->get();

        foreach ($clumps as $clump) {
            if ($clump->ins_rtc_metrics->isNotEmpty()) {
                $min = Carbon::parse($clump->ins_rtc_metrics->first()->min_dt);
                $max = Carbon::parse($clump->ins_rtc_metrics->first()->max_dt);

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

        if (empty($durations)) {
            return 0;
        }

        $average = array_sum($durations) / count($durations);

        return (int) $average;
    }
}
