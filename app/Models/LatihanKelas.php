<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LatihanKelas extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',         // text contoh: VII-G
        'lantai',       // nomor: 1
    ];
}
