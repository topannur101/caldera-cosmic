<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TskTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'desc',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the projects for the team
     */
    public function tsk_projects(): HasMany
    {
        return $this->hasMany(TskProject::class);
    }

    /**
     * Get the auths for the team
     */
    public function tsk_auths(): HasMany
    {
        return $this->hasMany(TskAuth::class);
    }

    /**
     * Get the users in this team
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tsk_auths')
                    ->withPivot(['perms', 'role', 'is_active'])
                    ->withTimestamps();
    }

    /**
     * Get active projects for the team
     */
    public function activeProjects(): HasMany
    {
        return $this->tsk_projects()->where('status', 'active');
    }

    /**
     * Get active auths for the team
     */
    public function activeAuths(): HasMany
    {
        return $this->tsk_auths()->where('is_active', true);
    }

    /**
     * Get team leaders
     */
    public function leaders(): BelongsToMany
    {
        return $this->users()
                    ->wherePivot('role', 'leader')
                    ->wherePivot('is_active', true);
    }

    /**
     * Get team members (including leaders)
     */
    public function members(): BelongsToMany
    {
        return $this->users()
                    ->wherePivot('is_active', true);
    }

    /**
     * Check if user is a member of this team
     */
    public function hasMember(int $userId): bool
    {
        return $this->tsk_auths()
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->exists();
    }

    /**
     * Check if user is a leader of this team
     */
    public function hasLeader(int $userId): bool
    {
        return $this->tsk_auths()
                    ->where('user_id', $userId)
                    ->where('role', 'leader')
                    ->where('is_active', true)
                    ->exists();
    }

    /**
     * Get tasks count for this team
     */
    public function getTasksCountAttribute(): int
    {
        return $this->tsk_projects()
                    ->withCount('tsk_items')
                    ->get()
                    ->sum('tsk_items_count');
    }

    /**
     * Get active projects count
     */
    public function getActiveProjectsCountAttribute(): int
    {
        return $this->activeProjects()->count();
    }

    /**
     * Get members count
     */
    public function getMembersCountAttribute(): int
    {
        return $this->activeAuths()->count();
    }

    /**
     * Scope for active teams only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope teams that have active projects
     */
    public function scopeWithActiveProjects($query)
    {
        return $query->whereHas('tsk_projects', function ($query) {
            $query->where('status', 'active');
        });
    }

    /**
     * Scope teams that user is a member of
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('tsk_auths', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->where('is_active', true);
        });
    }
}