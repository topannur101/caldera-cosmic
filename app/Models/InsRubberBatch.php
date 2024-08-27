<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsRubberBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'model',
        'color',
        'mcs',
        'rdc_eval',
        'omv_eval',
    ];

    public function rdcEvalHuman(): string
    {
        $this->rdc_eval;

        switch ($this->rdc_eval) {
            case 'queue':
                return __('Antri');
                break;
            case 'pass':
                return __('Pass');
                break;
            case 'fail':
                return __('Fail');
                break;
            default:
            return __('Baru');
        }
    }

    public function omvEvalHuman(): string
    {
        switch ($this->omv_eval) {
            case 'on_time':
                return __('Tepat waktu');
            case 'too_soon':
                return __('Terlalu awal');
            case 'too_late':
                return __('Terlambat');
            default:
                return __('Baru');
        }
    }
}
