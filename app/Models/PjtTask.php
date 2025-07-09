<?php

// app/Models/PjtTask.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PjtTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
        'start_date',
        'end_date',
        'pjt_item_id',
        'assignee_id',
        'assigner_id',
        'hour_work',
        'hour_remaining',
        'category',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'hour_work' => 'integer',
        'hour_remaining' => 'integer',
    ];

    /**
     * Task categories enum values
     */
    public const CATEGORIES = [
        'breakdown_repair' => 'Breakdown Repair',
        'project_improvement' => 'Project Improvement',
        'report' => 'Report',
        'tpm' => 'TPM',
        'meeting' => 'Meeting',
        'other' => 'Other'
    ];

    /**
     * Get the project this task belongs to
     */
    public function pjt_item(): BelongsTo
    {
        return $this->belongsTo(PjtItem::class, 'pjt_item_id');
    }

    /**
     * Get the user assigned to this task
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the user who assigned this task
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigner_id');
    }

    /**
     * Get hours completed for this task
     */
    public function getHourCompletedAttribute(): int
    {
        return $this->hour_work - $this->hour_remaining;
    }

    /**
     * Get completion percentage for this task
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->hour_work === 0) return 0;
        return round(($this->hour_completed / $this->hour_work) * 100);
    }

    /**
     * Check if task is completed
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->hour_remaining === 0;
    }

    /**
     * Check if task is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->end_date < now()->toDateString() && !$this->is_completed;
    }

    /**
     * Check if task is active (has remaining hours)
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->hour_remaining > 0;
    }

    /**
     * Get task status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        if ($this->is_completed) {
            return 'green';
        } elseif ($this->is_overdue) {
            return 'red';
        } elseif ($this->start_date <= now()->toDateString()) {
            return 'yellow';
        } else {
            return 'neutral';
        }
    }

    /**
     * Get task status text
     */
    public function getStatusTextAttribute(): string
    {
        if ($this->is_completed) {
            return 'Completed';
        } elseif ($this->is_overdue) {
            return 'Overdue';
        } elseif ($this->start_date <= now()->toDateString()) {
            return 'In Progress';
        } else {
            return 'Scheduled';
        }
    }

    /**
     * Get category display name
     */
    public function getCategoryDisplayAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    /**
     * Get category color for UI
     */
    public function getCategoryColorAttribute(): string
    {
        return match($this->category) {
            'breakdown_repair' => 'red',
            'project_improvement' => 'blue',
            'report' => 'purple',
            'tpm' => 'green',
            'meeting' => 'yellow',
            'other' => 'neutral',
            default => 'neutral'
        };
    }

    /**
     * Get task duration in days
     */
    public function getDurationDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Check if task is due today
     */
    public function getIsDueTodayAttribute(): bool
    {
        return $this->end_date->isToday() && !$this->is_completed;
    }

    /**
     * Check if task starts today
     */
    public function getStartsTodayAttribute(): bool
    {
        return $this->start_date->isToday();
    }

    /**
     * Update task hours and recalculate remaining
     */
    public function logHours(int $hoursWorked): void
    {
        $this->hour_remaining = max(0, $this->hour_remaining - $hoursWorked);
        $this->save();
    }

    /**
     * Mark task as completed
     */
    public function markCompleted(): void
    {
        $this->hour_remaining = 0;
        $this->save();
    }

    /**
     * Scope to filter by project
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('pjt_item_id', $projectId);
    }

    /**
     * Scope to filter by assignee
     */
    public function scopeForAssignee($query, int $userId)
    {
        return $query->where('assignee_id', $userId);
    }

    /**
     * Scope to filter by assigner
     */
    public function scopeForAssigner($query, int $userId)
    {
        return $query->where('assigner_id', $userId);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, array $categories)
    {
        return $query->whereIn('category', $categories);
    }

    /**
     * Scope to get completed tasks
     */
    public function scopeCompleted($query)
    {
        return $query->where('hour_remaining', 0);
    }

    /**
     * Scope to get active tasks
     */
    public function scopeActive($query)
    {
        return $query->where('hour_remaining', '>', 0);
    }

    /**
     * Scope to get overdue tasks
     */
    public function scopeOverdue($query)
    {
        return $query->where('end_date', '<', now()->toDateString())
                    ->where('hour_remaining', '>', 0);
    }

    /**
     * Scope to get tasks due today
     */
    public function scopeDueToday($query)
    {
        return $query->whereDate('end_date', now()->toDateString())
                    ->where('hour_remaining', '>', 0);
    }

    /**
     * Scope to get tasks starting today
     */
    public function scopeStartingToday($query)
    {
        return $query->whereDate('start_date', now()->toDateString());
    }

    /**
     * Scope to filter by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->where(function($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function($subQ) use ($startDate, $endDate) {
                  $subQ->where('start_date', '<=', $startDate)
                       ->where('end_date', '>=', $endDate);
              });
        });
    }

    /**
     * Scope to search tasks
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('desc', 'like', "%{$term}%");
        });
    }

    /**
     * Scope to get tasks for user (assigned or created)
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('assignee_id', $userId)
              ->orWhere('assigner_id', $userId);
        });
    }}
