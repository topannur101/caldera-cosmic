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

        'pv_r_1',
        'pv_r_2',
        'pv_r_3',
        'pv_r_4',
        'pv_r_5',
        'pv_r_6',
        'pv_r_7',
        'pv_r_8',

        'sv_c_1',
        'sv_c_2',
        'sv_c_3',
        'sv_c_4',
        'sv_c_5',
        'sv_c_6',
        'sv_c_7',
        'sv_c_8',

        'sv_w_1',
        'sv_w_2',
        'sv_w_3',
        'sv_w_4',
        'sv_w_5',
        'sv_w_6',
        'sv_w_7',
        'sv_w_8',
        
        'sv_r_1',
        'sv_r_2',
        'sv_r_3',
        'sv_r_4',
        'sv_r_5',
        'sv_r_6',
        'sv_r_7',
        'sv_r_8',
        
    ];
    
    // protected $casts = [
    //     'taken_at' => 'datetime',
    //     'temp' => 'float',
    //     'speed' => 'float',
    // ];
}
