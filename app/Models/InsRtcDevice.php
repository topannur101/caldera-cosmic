<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class InsRtcDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'line',
        'ip_address',
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
}
