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
                    ->withPivot(['perms', 'is_active'])
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
     * Get team members
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
     * Get tasks count for this team
     */
    public function getTasksCountAttribute(): int
    {
        return TskItem::whereHas('tsk_project', function ($query) {
            $query->where('tsk_team_id', $this->id);
        })->count();
    }

    /**
     * Get members count for this team
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
     * Scope teams with projects
     */
    public function scopeWithProjects($query)
    {
        return $query->whereHas('tsk_projects');
    }

    /**
     * Scope teams for specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('tsk_auths', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('is_active', true);
        });
    }
}