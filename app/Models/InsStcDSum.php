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

        'user_1_id',
        'user_2_id',
        'started_at',
        'ended_at',

        'preheat',
        'section_1',
        'section_2',
        'section_3',
        'section_4',
        'section_5',
        'section_6',
        'section_7',
        'section_8',
        'postheat',

        'speed',
        'sequence',
        'position',
        'sv_temps',
    ];
    
    protected $casts = [
        'started_at'    => 'datetime',
        'ended_at'      => 'datetime',

        'preheat'       => 'float',
        'section_1'     => 'float',
        'section_2'     => 'float',
        'section_3'     => 'float',
        'section_4'     => 'float',
        'section_5'     => 'float',
        'section_6'     => 'float',
        'section_7'     => 'float',
        'section_8'     => 'float',
        'postheat'      => 'float',

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
