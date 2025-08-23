<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsStcDLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_stc_d_sum_id',
        'taken_at',
        'temp',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'temp' => 'float',
    ];

    public function ins_stc_d_sum(): BelongsTo
    {
        return $this->belongsTo(InsStcDSum::class);
    }
}
