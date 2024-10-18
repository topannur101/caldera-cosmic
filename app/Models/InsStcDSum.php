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
        'start_time',
        'end_time',
        'preheat_temp',
        'z_1_temp',
        'z_2_temp',
        'z_3_temp',
        'z_4_temp',
        'postheat_temp',
        'speed',
        'sequence',
        'position',
        'set_temps',
    ];
    
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'preheat_temp' => 'float',
        'z_1_temp' => 'float',
        'z_2_temp' => 'float',
        'z_3_temp' => 'float',
        'z_4_temp' => 'float',
        'postheat_temp' => 'float',
        'speed' => 'float',
    ];

    public static function logsCountEvalHuman($logsCountEval): string
    {
        switch ($logsCountEval) {
            case 'optimal':
                return __('Optimal');
                break;
            
            case 'too_many':
                return __('Terlalu banyak');
                break;
            case 'too_few':
                return __('Terlalu sedikit');
                break;
            default:
                return __('Tak diketahui');
        }
    }

    public function duration(): string
    {
        return InsStc::duration($this->start_time, $this->end_time);
    }

    public function uploadLatency(): string
    {
        return InsStc::duration($this->end_time, $this->updated_at);
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
