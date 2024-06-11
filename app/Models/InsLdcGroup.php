<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsLdcGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'line',
        'workdate',
        'style',
        'material',
    ];
}
