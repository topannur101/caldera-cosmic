<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsStcAdj extends Model
{
    use HasFactory;

    protected $fillable = [

        'ins_stc_machine_id',
        'user_id',
        'position',
        'use_m_log_sv',
        'ins_stc_d_sum_id',
        'ins_stc_m_log_id',
        'formula_id',
        'sv_p_1',        
        'sv_p_2',        
        'sv_p_3',        
        'sv_p_4',        
        'sv_p_5',        
        'sv_p_6',        
        'sv_p_7',        
        'sv_p_8',
        'remarks',
        'is_applied'

    ];
}
