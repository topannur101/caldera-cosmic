<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InsRtcDevice extends Model
{
    use HasFactory;

    public function ins_rtc_metrics(): HasMany
    {
        return $this->hasMany(InsRtcMetric::class);
    }
}
