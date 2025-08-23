<?php

namespace App\Models;

use App\InsStc;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'at_values',

        'integrity',

        'started_at',
        'ended_at',

        'speed',
        'sequence',
        'position',
    ];

    protected $casts = [
        'started_at' => 'datetime:Y-m-d H:i',
        'ended_at' => 'datetime:Y-m-d H:i',
        'speed' => 'float',
    ];

    public function duration(): string
    {
        return InsStc::duration($this->started_at, $this->ended_at, 'short');
    }

    public function latency(): string
    {
        return InsStc::duration($this->ended_at, $this->created_at, 'short');
    }

    public function integrity_friendly(): string
    {
        switch ($this->integrity) {
            case 'stable':
                return '<i class="icon-circle-check me-2 text-green-500"></i>'.__('SV cocok');
            case 'modified':
                return '<i class="icon-circle-alert me-2 text-yellow-500"></i>'.__('SV berubah');
            case 'none':
                return __('Tak ada pembanding');
            default:
                return __('Tak ada pembanding');
        }
    }

    public function adjustment(): string
    {
        if ($this->is_applied) {
            if ($this->sv_used == 'm_log') {
                return 'full-auto';
            } elseif ($this->sv_used == 'd_sum') {
                return 'semi-auto';
            }
        }

        return 'none';
    }

    public function adjustment_friendly(): string
    {
        switch ($this->adjustment()) {
            case 'full-auto':
                return '<i class="icon-circle-check me-2 text-green-500"></i>'.__('Auto (SV auto)');
            case 'semi-auto':
                return '<i class="icon-circle-check me-2 text-yellow-500"></i>'.__('Auto (SV manual)');
            case 'manual':
                return __('Manual');
            default:
                return __('Manual');
        }
    }

    /**
     * Get the previous AT value (element 0 of at_values)
     */
    public function getPreviousAtAttribute(): float
    {
        $at_values = $this->at_values ? json_decode($this->at_values, true) : [0, 0, 0];

        return isset($at_values[0]) ? (float) $at_values[0] : 0.0;
    }

    /**
     * Get the current AT value (element 1 of at_values)
     */
    public function getCurrentAtAttribute(): float
    {
        $at_values = $this->at_values ? json_decode($this->at_values, true) : [0, 0, 0];

        return isset($at_values[1]) ? (float) $at_values[1] : 0.0;
    }

    /**
     * Get the delta AT value (element 2 of at_values)
     */
    public function getDeltaAtAttribute(): float
    {
        $at_values = $this->at_values ? json_decode($this->at_values, true) : [0, 0, 0];

        return isset($at_values[2]) ? (float) $at_values[2] : 0.0;
    }

    /**
     * Get all AT values as an array
     */
    public function getAtValuesArrayAttribute(): array
    {
        return $this->at_values ? json_decode($this->at_values, true) : [0, 0, 0];
    }

    /**
     * Check if AT adjustment should be applied based on delta
     */
    public function shouldApplyAtAdjustment(): bool
    {
        return $this->delta_at != 0 && $this->current_at > 0 && $this->previous_at > 0;
    }

    public function ins_stc_d_logs(): HasMany
    {
        return $this->hasMany(InsStcDlog::class);
    }

    public function user(): BelongsTo
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

    public function ins_stc_adjusts(): HasMany
    {
        return $this->hasMany(InsStcAdjust::class);
    }
}
