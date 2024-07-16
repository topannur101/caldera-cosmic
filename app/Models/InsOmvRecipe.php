<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsOmvRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'capture_points',
        'steps'
    ];

    protected $casts = [
        'capture_points' => 'array',
        'steps' => 'array',
    ];
}
