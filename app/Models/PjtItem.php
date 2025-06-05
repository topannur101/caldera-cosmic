<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PjtItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
        'pjt_team_id',
        'user_id',
        'location',
        'photo',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the team this project belongs to
     */
    public function pjt_team(): BelongsTo
    {
        return $this->belongsTo(PjtTeam::class, 'pjt_team_id');
    }

    /**
     * Get the user who owns this project
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all members of this project
     */
    public function pjt_members(): HasMany
    {
        return $this->hasMany(PjtMember::class, 'pjt_item_id');
    }

    /**
     * Get all tasks for this project
     */
    public function pjt_tasks(): HasMany
    {
        return $this->hasMany(PjtTask::class, 'pjt_item_id');
    }

    /**
     * Get active tasks for this project
     */
    public function active_pjt_tasks(): HasMany
    {
        return $this->hasMany(PjtTask::class, 'pjt_item_id')
                   ->where('hour_remaining', '>', 0);
    }

    /**
     * Get completed tasks for this project
     */
    public function completed_pjt_tasks(): HasMany
    {
        return $this->hasMany(PjtTask::class, 'pjt_item_id')
                   ->where('hour_remaining', 0);
    }

    /**
     * Update project photo
     */
    public function updatePhoto($photo)
    {
        if ($photo) {
            if ($this->photo != $photo) {
                $path = storage_path('app/livewire-tmp/'.$photo);        

                // Process photo
                $manager = new ImageManager(new Driver());
                $image = $manager->read($path)
                    ->scaleDown(width: 400)
                    ->toJpeg(90);

                // Set file name and save to disk
                $id = $this->id;
                $time = Carbon::now()->format('YmdHis');
                $rand = Str::random(5);
                $name = $id.'_'.$time.'_'.$rand.'.jpg';

                Storage::put('/public/pjt-items/'.$name, $image);

                return $this->update([
                    'photo' => $name,
                ]);
            }
        } else {
            return $this->update([
                'photo' => null,
            ]);
        }
    }

    /**
     * Get formatted photo URL
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? '/storage/pjt-items/' . $this->photo : null;
    }

    /**
     * Calculate project progress percentage
     */
    public function getProgressPercentageAttribute(): int
    {
        $totalTasks = $this->pjt_tasks->count();
        if ($totalTasks === 0) return 0;
        
        $completedTasks = $this->completed_pjt_tasks->count();
        return round(($completedTasks / $totalTasks) * 100);
    }

    /**
     * Get total hours allocated for this project
     */
    public function getTotalHoursAttribute(): int
    {
        return $this->pjt_tasks->sum('hour_work');
    }

    /**
     * Get total hours remaining for this project
     */
    public function getRemainingHoursAttribute(): int
    {
        return $this->pjt_tasks->sum('hour_remaining');
    }

    /**
     * Get total hours completed for this project
     */
    public function getCompletedHoursAttribute(): int
    {
        return $this->total_hours - $this->remaining_hours;
    }

    /**
     * Check if project is overdue (has tasks past end_date)
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->pjt_tasks()
            ->where('end_date', '<', now())
            ->where('hour_remaining', '>', 0)
            ->exists();
    }

    /**
     * Get project status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active' => 'green',
            'inactive' => 'neutral',
            default => 'neutral'
        };
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, array $statuses)
    {
        return $query->whereIn('status', $statuses);
    }

    /**
     * Scope to filter by team
     */
    public function scopeByTeam($query, array $teamIds)
    {
        return $query->whereIn('pjt_team_id', $teamIds);
    }

    /**
     * Scope to get projects user owns or is member of
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereHas('pjt_members', function($subQuery) use ($userId) {
                  $subQuery->where('user_id', $userId);
              });
        });
    }

    /**
     * Scope to search projects
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('desc', 'like', "%{$term}%")
              ->orWhere('location', 'like', "%{$term}%");
        });
    }

    /**
     * Scope to get active projects
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get inactive projects
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}