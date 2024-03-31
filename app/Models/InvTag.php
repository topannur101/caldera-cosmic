<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'inv_area_id'
    ];

    public function inv_area(): BelongsTo
    {
        return $this->belongsTo(InvArea::class);
    }
}
