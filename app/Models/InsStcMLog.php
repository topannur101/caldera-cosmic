<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsStcMLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_stc_machine_id',
        'position',
        'speed',
        'pv_1',
        'pv_2',
        'pv_3',
        'pv_4',
        'pv_5',
        'pv_6',
        'pv_7',
        'pv_8',
        
        'sv_1',
        'sv_2',
        'sv_3',
        'sv_4',
        'sv_5',
        'sv_6',
        'sv_7',
        'sv_8',
    ];
    
    // protected $casts = [
    //     'taken_at' => 'datetime',
    //     'temp' => 'float',
    //     'speed' => 'float',
    // ];
}
