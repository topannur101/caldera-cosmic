<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InsOmvCapture extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_omv_metric_id',
        'file_name',
    ];

    public function ins_omv_metric(): BelongsTo
    {
        return $this->belongsTo(InsOmvMetric::class);
    }
}
