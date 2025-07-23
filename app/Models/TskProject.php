<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TskProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
        'code',
        'tsk_team_id',
        'user_id',
        'status',
        'priority',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the team that owns the project
     */
    public function tsk_team(): BelongsTo
    {
        return $this->belongsTo(TskTeam::class);
    }

    /**
     * Get the user who created the project
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tasks for the project
     */
    public function tsk_items(): HasMany
    {
        return $this->hasMany(TskItem::class);
    }

    /**
     * Get tasks with specific status
     */
    public function tasksWithStatus(string $status): HasMany
    {
        return $this->tsk_items()->where('status', $status);
    }

    /**
     * Get todo tasks
     */
    public function todoTasks(): HasMany
    {
        return $this->tasksWithStatus('todo');
    }

    /**
     * Get in progress tasks
     */
    public function inProgressTasks(): HasMany
    {
        return $this->tasksWithStatus('in_progress');
    }

    /**
     * Get review tasks
     */
    public function reviewTasks(): HasMany
    {
        return $this->tasksWithStatus('review');
    }

    /**
     * Get done tasks
     */
    public function doneTasks(): HasMany
    {
        return $this->tasksWithStatus('done');
    }

    /**
     * Get overdue tasks
     */
    public function overdueTasks(): HasMany
    {
        return $this->tsk_items()->overdue();
    }

    /**
     * Get project progress percentage
     */
    public function getProgressPercentageAttribute(): int
    {
        $totalTasks = $this->tsk_items()->count();
        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $this->doneTasks()->count();
        return round(($completedTasks / $totalTasks) * 100);
    }

    /**
     * Check if project is overdue
     */
    public function isOverdue(): bool
    {
        return $this->end_date && 
               $this->end_date->isPast() && 
               $this->status !== 'completed';
    }

    /**
     * Get status color for UI
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'active' => 'green',
            'completed' => 'blue',
            'on_hold' => 'yellow',
            'cancelled' => 'red',
            default => 'neutral'
        };
    }

    /**
     * Get priority color for UI
     */
    public function getPriorityColor(): string
    {
        return match($this->priority) {
            'low' => 'neutral',
            'medium' => 'blue',
            'high' => 'yellow',
            'urgent' => 'red',
            default => 'neutral'
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'on_hold' => 'Ditahan',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get priority label
     */
    public function getPriorityLabel(): string
    {
        return match($this->priority) {
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
            default => ucfirst($this->priority)
        };
    }

    /**
     * Scope for active projects
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for completed projects
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for overdue projects
     */
    public function scopeOverdue($query)
    {
        return $query->where('end_date', '<', now())
                    ->where('status', '!=', 'completed');
    }

    /**
     * Scope projects for specific team
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('tsk_team_id', $teamId);
    }

    /**
     * Scope projects that user can access
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('tsk_team.tsk_auths', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->where('is_active', true);
        });
    }
}