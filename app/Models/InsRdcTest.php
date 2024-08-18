<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsRdcTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_rubber_batch_id',
        'eval',
        'machine',
        's_min',
        's_max',
        'tc10',
        'tc50',
        'tc90',
        'data',
        'user_id',
    ];

    public function evalHuman(): string
    {
        $this->eval;

        switch ($this->eval) {
            case 'pass':
                return __('Lolos');
                break;
            case 'fail':
                return __('Gagal');
                break;
        }
        return __('Baru');
    }
}
