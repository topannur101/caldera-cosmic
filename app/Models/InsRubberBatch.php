<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InsRubberBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'code_alt',
        'model',
        'color',
        'mcs',
        'rdc_eval',
        'omv_eval',
    ];

    public function ins_omv_metric(): HasOne
    {
        return $this->hasOne(InsOmvMetric::class)->latest();
    }

    public function ins_rdc_test(): HasOne
    {
        return $this->hasOne(InsRdcTest::class)->latest();
    }

    public function ins_rdc_tests(): HasMany
    {
        return $this->hasMany(InsRdcTest::class);
    }
}
