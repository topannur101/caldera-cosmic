<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InsLdcHide extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'area_vn',
        'area_ab',
        'area_qt',
        'grade',
        'machine',
        'shift',
        'user_id',
        'ins_ldc_group_id'
    ];

    public function ins_ldc_group(): BelongsTo
    {
        return $this->belongsTo(InsLdcGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
