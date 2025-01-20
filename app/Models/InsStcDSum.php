<?php

namespace App\Models;

use App\InsStc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InsStcDSum extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_stc_device_id',
        'ins_stc_machine_id',

        'user_id',
        'formula_id',
        'sv_used',
        'is_applied',

        'target_values',
        'hb_values',
        'sv_values',
        'svp_values',

        'integrity',

        'started_at',
        'ended_at',

        'speed',
        'sequence',
        'position',
    ];
    
    protected $casts = [
        'started_at'    => 'datetime',
        'ended_at'      => 'datetime',
        'speed' => 'float',
    ];

    public function duration(): string
    {
        return InsStc::duration($this->started_at, $this->ended_at);
    }

    public function uploadLatency(): string
    {
        return InsStc::duration($this->ended_at, $this->updated_at);
    }

    public function ins_stc_d_logs(): HasMany
    {
        return $this->hasMany(InsStcDlog::class);
    }

    public function user_1(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function user_2(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ins_stc_machine(): BelongsTo
    {
        return $this->belongsTo(InsStcMachine::class);
    }

    public function ins_stc_device(): BelongsTo
    {
        return $this->belongsTo(InsStcDevice::class);
    }

}
