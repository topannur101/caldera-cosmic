<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsRtcClump extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_rtc_recipe_id',
        'ins_rtc_device_id'
    ];

    public function ins_rtc_device(): BelongsTo
    {
        return $this->belongsTo(InsRtcDevice::class);
    }

    public function ins_rtc_recipe(): BelongsTo
    {
        return $this->belongsTo(InsRtcRecipe::class);
    }

    public function ins_rtc_metrics():  HasMany
    {
        return $this->hasMany(InsRtcMetric::class);
    }
}
