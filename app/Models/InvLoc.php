<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvLoc extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent',
        'bin',
    ];
}
