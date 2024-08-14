<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsRubberBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'model',
        'color',
        'mcs',
        'rdc_eval',
        'omv_eval'
    ];
}
