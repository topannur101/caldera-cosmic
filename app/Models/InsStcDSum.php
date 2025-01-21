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
        'started_at'    => 'datetime:Y-m-d H:i',
        'ended_at'      => 'datetime:Y-m-d H:i',
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
                return '<i class="fa fa-check-circle me-2 text-green-500"></i>' . __('SV konsisten');
            case 'modified':
                return '<i class="fa fa-exclamation-circle me-2 text-yellow-500"></i>' . __('SV berubah');
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
                return '<i class="fa fa-check-circle me-2 text-green-500"></i>' . __('Auto (SV auto)');
            case 'semi-auto':
                return '<i class="fa fa-check-circle me-2 text-yellow-500"></i>' . __('Auto (SV manual)');
            case 'manual':
                return __('Manual');
            default:
                return __('Manual');
        }
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

}
