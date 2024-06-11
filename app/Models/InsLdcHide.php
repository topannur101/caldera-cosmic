<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsLdcHide extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'area_vn',
        'area_ab',
        'area_qt',
        'grade',
        'shift',
        'user_id',
        'ins_ldc_group_id'
    ];
}
