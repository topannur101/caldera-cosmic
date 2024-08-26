<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsStcMSum extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_stc_machine_id',
        'start_time',
        'end_time',
        's_1_temp',
        's_2_temp',
        's_3_temp',
        's_4_temp',
        's_5_temp',
        's_6_temp',
        's_7_temp',
        's_8_temp',
        'median_speed',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        's_1_temp' => 'float',
        's_2_temp' => 'float',
        's_3_temp' => 'float',
        's_4_temp' => 'float',
        's_5_temp' => 'float',
        's_6_temp' => 'float',
        's_7_temp' => 'float',
        's_8_temp' => 'float',
        'median_speed' => 'integer',
    ];
}
