<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsRtcMetric extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ins_rtc_recipe_id',
        'ins_rtc_device_id',
        'thick_act_left',
        'thick_act_right',
        'dt_client',
    ];

    protected $casts = [
        'dt_client' => 'datetime',
    ];

    public function ins_rtc_device(): BelongsTo
    {
        return $this->belongsTo(InsRtcDevice::class);
    }
}
