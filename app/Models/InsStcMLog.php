<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsStcMLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_stc_m_sum_id',
        'taken_at',
        'temp',
        'speed',
    ];
    
    protected $casts = [
        'taken_at' => 'datetime',
        'temp' => 'float',
        'speed' => 'float',
    ];
}
