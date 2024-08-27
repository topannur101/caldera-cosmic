<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsRdcMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'name',
        'cells'
    ];

    public function cellsCount()
    {
        $cells = json_decode($this->cells ?? '{}', true);
        return count($cells);
    }
}
