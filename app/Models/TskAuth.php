<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TskAuth extends Model
{
    use HasFactory;

    protected $primaryKey = null;
    public $incrementing = false;
    
    protected $fillable = [
        'user_id',
        'tsk_team_id',
        'perms',
        'is_active',
    ];

    protected $casts = [
        'perms' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the auth
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team that owns the auth
     */
    public function tsk_team(): BelongsTo
    {
        return $this->belongsTo(TskTeam::class);
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check if permission exists in perms array
        return in_array($permission, $this->perms ?? []);
    }

    /**
     * Add permission to user
     */
    public function addPermission(string $permission): void
    {
        $perms = $this->perms ?? [];
        if (!in_array($permission, $perms)) {
            $perms[] = $permission;
            $this->perms = $perms;
            $this->save();
        }
    }

    /**
     * Remove permission from user
     */
    public function removePermission(string $permission): void
    {
        $perms = $this->perms ?? [];
        $key = array_search($permission, $perms);
        if ($key !== false) {
            unset($perms[$key]);
            $this->perms = array_values($perms);
            $this->save();
        }
    }

    /**
     * Get all available task permissions
     */
    public static function getAvailablePermissions(): array
    {
        return [
            'task-assign' => __('Beri Tugas'),
            'task-manage' => __('Kelola Tugas'),
            'project-manage' => __('Kelola Proyek'),
        ];
    }

    /**
     * Scope for active auths only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}