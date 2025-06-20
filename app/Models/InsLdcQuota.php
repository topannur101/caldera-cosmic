<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsLdcQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine',
        'value',
    ];

}
