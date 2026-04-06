<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeMixingDevice extends Model
{
    protected $table = 'inv_ce_mixing_devices';

    protected $fillable = [
        'name',
        'node_id',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];
}
