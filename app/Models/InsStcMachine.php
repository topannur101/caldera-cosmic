<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsStcMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'line',
        'ip_address',
    ];

    protected $casts = [
        'line' => 'integer',
    ];
}
