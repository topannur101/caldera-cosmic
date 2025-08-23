<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsOmvCapture extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_omv_metric_id',
        'file_name',
        'taken_at',
    ];

    public function ins_omv_metric(): BelongsTo
    {
        return $this->belongsTo(InsOmvMetric::class);
    }
}
