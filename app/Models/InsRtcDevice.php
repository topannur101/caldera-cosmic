<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InsRtcDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'line',
        'ip_address'
    ];

    public function ins_rtc_metrics(): HasMany
    {
        return $this->hasMany(InsRtcMetric::class);
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
