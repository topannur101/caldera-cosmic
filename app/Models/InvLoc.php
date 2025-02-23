<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvLoc extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent',
        'bin'
    ];

}
