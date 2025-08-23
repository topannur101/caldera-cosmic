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
        'ins_rtc_clump_id',
        'is_correcting',
        'action_left',
        'action_right',
        'push_left',
        'push_right',
        'sensor_left',
        'sensor_right',
        'dt_client',
    ];

    protected $casts = [
        'dt_client' => 'datetime',
    ];

    public function ins_rtc_clump(): BelongsTo
    {
        return $this->belongsTo(InsRtcClump::class);
    }
}
