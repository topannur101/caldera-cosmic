<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsStcDSum extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_stc_device_id',
        'ins_stc_machine_id',
        'start_time',
        'end_time',
        'preheat_temp',
        'z_1_temp',
        'z_2_temp',
        'z_3_temp',
        'z_4_temp',
        'speed',
    ];
    
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'preheat_temp' => 'float',
        'z_1_temp' => 'float',
        'z_2_temp' => 'float',
        'z_3_temp' => 'float',
        'z_4_temp' => 'float',
        'speed' => 'integer',
    ];

    public static function dataCountEvalHuman($dataCountEval): string
    {
        switch ($dataCountEval) {
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
}
