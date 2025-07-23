<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class TskProject extends Model
{
    protected $fillable = [
        'name',
        'desc', 
        'code',
        'tsk_team_id',
        'user_id',
        'status',
        'priority',
        'start_date',
        'end_date'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // ============== RELATIONSHIPS ==============

    public function tsk_team(): BelongsTo
    {
        return $this->belongsTo(TskTeam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tsk_items(): HasMany
    {
        return $this->hasMany(TskItem::class);
    }

    // Tasks by status for dashboard/stats
    public function todo_tasks(): HasMany
    {
        return $this->hasMany(TskItem::class)->where('status', 'todo');
    }

    public function in_progress_tasks(): HasMany
    {
        return $this->hasMany(TskItem::class)->where('status', 'in_progress');
    }

    public function review_tasks(): HasMany
    {
        return $this->hasMany(TskItem::class)->where('status', 'review');
    }

    public function done_tasks(): HasMany
    {
        return $this->hasMany(TskItem::class)->where('status', 'done');
    }

    // ============== ACCESSORS & BUSINESS LOGIC ==============

    public function getProgressAttribute(): float
    {
        $total = $this->tsk_items()->count();
        if ($total === 0) return 0;
        
        $completed = $this->done_tasks()->count();
        return round(($completed / $total) * 100, 1);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== 'completed';
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date) return null;
        
        return now()->diffInDays($this->end_date, false);
    }

    // Status helpers for UI
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active' => 'green',
            'completed' => 'blue', 
            'on_hold' => 'yellow',
            'cancelled' => 'red',
            default => 'neutral'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'on_hold' => 'Ditunda', 
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status)
        };
    }

    // Priority helpers for UI
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'neutral',
            'medium' => 'blue',
            'high' => 'yellow', 
            'urgent' => 'red',
            default => 'neutral'
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
            default => ucfirst($this->priority)
        };
    }

    // ============== SCOPES ==============

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('end_date', '<', now())
                    ->where('status', '!=', 'completed');
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('tsk_team_id', $teamId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // ============== HELPER METHODS ==============

    public function canBeEditedBy(User $user): bool
    {
        // Project owner can always edit
        if ($this->user_id === $user->id) {
            return true;
        }

        // Check if user has project-manage permission in this team
        $auth = TskAuth::where('user_id', $user->id)
            ->where('tsk_team_id', $this->tsk_team_id)
            ->where('is_active', true)
            ->first();

        if (!$auth) return false;

        $perms = is_array($auth->perms) ? $auth->perms : json_decode($auth->perms ?? '[]', true);
        return in_array('project-manage', $perms);
    }

    public function getTasksCount(): array
    {
        return [
            'total' => $this->tsk_items()->count(),
            'todo' => $this->todo_tasks()->count(),
            'in_progress' => $this->in_progress_tasks()->count(),
            'review' => $this->review_tasks()->count(),
            'done' => $this->done_tasks()->count(),
        ];
    }

    // For API/JSON responses
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // Add computed attributes
        $array['progress'] = $this->progress;
        $array['is_overdue'] = $this->is_overdue;
        $array['days_remaining'] = $this->days_remaining;
        $array['status_color'] = $this->status_color;
        $array['status_label'] = $this->status_label;
        $array['priority_color'] = $this->priority_color;
        $array['priority_label'] = $this->priority_label;
        $array['tasks_count'] = $this->getTasksCount();
        
        return $array;
    }
}