<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class TskItem extends Model
{
    use HasFactory;

    protected $table = 'tsk_items';

    protected $fillable = [
        'title',
        'desc',
        'tsk_project_id',
        'tsk_type_id',
        'created_by',
        'assigned_to',
        'status',
        'start_date',
        'end_date',
        'estimated_hours',
        'actual_hours',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
    ];

    // ============== RELATIONSHIPS ==============

    public function tsk_project(): BelongsTo
    {
        return $this->belongsTo(TskProject::class, 'tsk_project_id');
    }

    public function tsk_type(): BelongsTo
    {
        return $this->belongsTo(TskType::class, 'tsk_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ComItem::class, 'model_id')
            ->where('model_name', 'TskItem')
            ->orderBy('created_at', 'desc');
    }

    // ============== COMPUTED ATTRIBUTES ==============

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'todo' => 'gray',
            'in_progress' => 'blue',
            'review' => 'yellow',
            'done' => 'green',
            default => 'gray'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'todo' => 'Belum Mulai',
            'in_progress' => 'Sedang Dikerjakan',
            'review' => 'Review',
            'done' => 'Selesai',
            default => ucfirst($this->status)
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->end_date && 
               $this->end_date->isPast() && 
               $this->status !== 'done';
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date || $this->status === 'done') {
            return null;
        }

        return now()->diffInDays($this->end_date, false);
    }

    public function getDurationDaysAttribute(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getProgressAttribute(): float
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        $totalDays = $this->start_date->diffInDays($this->end_date) + 1;
        $elapsedDays = $this->start_date->diffInDays(now()) + 1;

        if ($elapsedDays <= 0) return 0;
        if ($elapsedDays >= $totalDays) return 100;

        return min(100, ($elapsedDays / $totalDays) * 100);
    }

    // ============== SCOPES ==============

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('end_date', '<', now())
            ->where('status', '!=', 'done');
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereDate('end_date', today())
            ->where('status', '!=', 'done');
    }

    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query->whereBetween('end_date', [today(), today()->addDays($days)])
            ->where('status', '!=', 'done');
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeCreatedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('tsk_project_id', $projectId);
    }

    public function scopeForType(Builder $query, int $typeId): Builder
    {
        return $query->where('tsk_type_id', $typeId);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('status', ['todo', 'in_progress', 'review']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'done');
    }

    // ============== HELPER METHODS ==============

    public function isOverdue(): bool
    {
        return $this->is_overdue;
    }

    public function canBeEditedBy(User $user): bool
    {
        // Task creator can always edit
        if ($this->created_by === $user->id) {
            return true;
        }

        // Task assignee can edit
        if ($this->assigned_to === $user->id) {
            return true;
        }

        // Check if user has task-manage permission in this project's team
        $teamId = $this->tsk_project->tsk_team_id;
        $auth = TskAuth::where('user_id', $user->id)
            ->where('tsk_team_id', $teamId)
            ->where('is_active', true)
            ->first();

        if (!$auth) return false;

        $perms = is_array($auth->perms) ? $auth->perms : json_decode($auth->perms ?? '[]', true);
        return in_array('task-manage', $perms);
    }

    public function canBeAssignedBy(User $user): bool
    {
        // Check if user has task-assign permission in this project's team
        $teamId = $this->tsk_project->tsk_team_id;
        $auth = TskAuth::where('user_id', $user->id)
            ->where('tsk_team_id', $teamId)
            ->where('is_active', true)
            ->first();

        if (!$auth) return false;

        $perms = is_array($auth->perms) ? $auth->perms : json_decode($auth->perms ?? '[]', true);
        return in_array('task-assign', $perms);
    }

    // For API/JSON responses
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // Add computed attributes
        $array['is_overdue'] = $this->is_overdue;
        $array['days_remaining'] = $this->days_remaining;
        $array['duration_days'] = $this->duration_days;
        $array['progress'] = $this->progress;
        $array['status_color'] = $this->status_color;
        $array['status_label'] = $this->status_label;
        
        return $array;
    }
}