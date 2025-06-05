<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PjtTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
    ];

    /**
     * Get all project items for this team
     */
    public function pjt_items(): HasMany
    {
        return $this->hasMany(PjtItem::class, 'pjt_team_id');
    }

    /**
     * Get active project items for this team
     */
    public function active_pjt_items(): HasMany
    {
        return $this->hasMany(PjtItem::class, 'pjt_team_id')
                   ->where('status', 'active');
    }

    /**
     * Scope to search teams by name
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('short_name', 'like', "%{$term}%");
        });
    }
}
