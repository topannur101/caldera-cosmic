<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsOmvMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_omv_recipe_id',
        'user_1_id',
        'user_2_id',
        'eval',
        'start_at',
        'end_at'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
