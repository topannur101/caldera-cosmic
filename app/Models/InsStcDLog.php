<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsStcDLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_stc_d_sum_id',
        'taken_at',
        'temp',
    ];
    
    protected $casts = [
        'taken_at' => 'datetime',
        'temp' => 'float',
    ];
}
