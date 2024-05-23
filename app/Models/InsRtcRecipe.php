<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsRtcRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'og_rs',
        'std_min',
        'std_max',
        'std_mid',
        'scale',
        'pfc_min',
        'pfc_max',
    ];
}
