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

    /**
     * Check if the task is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->end_date || $this->status === 'done') {
            return false;
        }
        
        return now()->isAfter($this->end_date);
    }

    /**
     * Get status color for UI
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'todo' => 'neutral',
            'in_progress' => 'blue',
            'review' => 'yellow',
            'done' => 'green',
            default => 'neutral',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'todo' => __('To Do'),
            'in_progress' => __('Dalam Proses'),
            'review' => __('Review'),
            'done' => __('Selesai'),
            default => $this->status,
        };
    }

    /**
     * Get priority color for UI
     */
    public function getPriorityColor(): string
    {
        return match($this->priority) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'neutral',
        };
    }

    /**
     * Get priority label
     */
    public function getPriorityLabel(): string
    {
        return match($this->priority) {
            'low' => __('Rendah'),
            'medium' => __('Sedang'),
            'high' => __('Tinggi'),
            'urgent' => __('Mendesak'),
            default => $this->priority,
        };
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): int
    {
        return match($this->status) {
            'todo' => 0,
            'in_progress' => 50,
            'review' => 80,
            'done' => 100,
            default => 0,
        };
    }

    /**
     * Check if task can be edited by user
     */
    public function canEdit(User $user): bool
    {
        return Gate::allows('update', [$this, $user]);
    }

    /**
     * Check if task can be deleted by user
     */
    public function canDelete(User $user): bool
    {
        return Gate::allows('delete', [$this, $user]);
    }

    /**
     * Get estimated hours formatted
     */
    public function getEstimatedHoursFormattedAttribute(): string
    {
        if (!$this->estimated_hours) {
            return '-';
        }
        
        return $this->estimated_hours . 'h';
    }

    /**
     * Get days until deadline
     */
    public function getDaysUntilDeadline(): ?int
    {
        if (!$this->end_date) {
            return null;
        }
        
        return now()->diffInDays($this->end_date, false);
    }

    /**
     * Get deadline status
     */
    public function getDeadlineStatus(): string
    {
        $days = $this->getDaysUntilDeadline();
        
        if ($days === null) {
            return 'none';
        }
        
        if ($days < 0) {
            return 'overdue';
        }
        
        if ($days <= 1) {
            return 'urgent';
        }
        
        if ($days <= 3) {
            return 'soon';
        }
        
        return 'normal';
    }
}