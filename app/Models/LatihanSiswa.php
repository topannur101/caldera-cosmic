<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LatihanSiswa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',         // text
        'umur',         // nomor
        'jk',           // enum
        'kelas_id',     // foreignId
    ];
}
