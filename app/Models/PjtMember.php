<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PjtMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'pjt_item_id',
        'user_id',
    ];

    /**
     * Get the project this membership belongs to
     */
    public function pjt_item(): BelongsTo
    {
        return $this->belongsTo(PjtItem::class, 'pjt_item_id');
    }

    /**
     * Get the user for this membership
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by project
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('pjt_item_id', $projectId);
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}