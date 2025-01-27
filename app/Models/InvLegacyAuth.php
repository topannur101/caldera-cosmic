<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvLegacyAuth extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'inv_area_id',
        'actions'
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inv_area(): BelongsTo
    {
        return $this->belongsTo(InvArea::class);
    }

    public function countActions()
    {
        $actions = json_decode($this->actions ?? '{}', true);
        return count($actions);
    }
}
