<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class TskType extends Model
{
    use HasFactory;

    protected $table = 'tsk_types';

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ============== RELATIONSHIPS ==============

    public function tsk_items(): HasMany
    {
        return $this->hasMany(TskItem::class, 'tsk_type_id');
    }

    public function active_tasks(): HasMany
    {
        return $this->tsk_items()->whereIn('status', ['todo', 'in_progress', 'review']);
    }

    public function completed_tasks(): HasMany
    {
        return $this->tsk_items()->where('status', 'done');
    }

    // ============== SCOPES ==============

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeWithTasksCount(Builder $query): Builder
    {
        return $query->withCount(['tsk_items', 'active_tasks', 'completed_tasks']);
    }

    // ============== HELPER METHODS ==============

    public function getTasksCount(): array
    {
        return [
            'total' => $this->tsk_items()->count(),
            'active' => $this->active_tasks()->count(),
            'completed' => $this->completed_tasks()->count(),
        ];
    }

    public function canBeDeleted(): bool
    {
        return $this->tsk_items()->count() === 0;
    }

    // ============== STATIC METHODS ==============

    public static function getActiveTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()->orderBy('name')->get();
    }

    public static function getTypesForSelect(): array
    {
        return static::active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}