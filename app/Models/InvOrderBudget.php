<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvOrderBudget extends Model
{
    use HasFactory;

    protected $table = 'inv_order_budget';

    protected $fillable = [
        'name',
        'balance',
        'inv_curr_id',
        'inv_area_id',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the currency that this budget uses
     */
    public function inv_curr(): BelongsTo
    {
        return $this->belongsTo(InvCurr::class, 'inv_curr_id');
    }

    /**
     * Get the area this budget belongs to
     */
    public function inv_area(): BelongsTo
    {
        return $this->belongsTo(InvArea::class, 'inv_area_id');
    }

    /**
     * Get all order items using this budget
     */
    public function inv_order_items(): HasMany
    {
        return $this->hasMany(InvOrderItem::class, 'inv_order_budget_id');
    }

    /**
     * Get budget snapshots for this budget
     */
    public function inv_order_budget_snapshots(): HasMany
    {
        return $this->hasMany(InvOrderBudgetSnapshot::class, 'inv_order_budget_id');
    }

    /**
     * Get open order items (not yet finalized into orders)
     */
    public function open_inv_order_items(): HasMany
    {
        return $this->hasMany(InvOrderItem::class, 'inv_order_budget_id')
                   ->whereNull('inv_order_id');
    }

    /**
     * Get finalized order items
     */
    public function finalized_inv_order_items(): HasMany
    {
        return $this->hasMany(InvOrderItem::class, 'inv_order_budget_id')
                   ->whereNotNull('inv_order_id');
    }

    /**
     * Calculate currently allocated amount from open orders
     */
    public function getAllocatedAmountAttribute(): string
    {
        return $this->open_inv_order_items()->sum('amount_budget');
    }

    /**
     * Calculate available budget (balance - allocated)
     */
    public function getAvailableBudgetAttribute(): string
    {
        return $this->balance - $this->allocated_amount;
    }

    /**
     * Check if budget has sufficient funds for an amount
     */
    public function hasSufficientFunds(float $amount): bool
    {
        return $this->available_budget >= $amount;
    }

    /**
     * Scope to get only active budgets
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by area
     */
    public function scopeForArea($query, int $areaId)
    {
        return $query->where('inv_area_id', $areaId);
    }

    /**
     * Scope to filter by currency
     */
    public function scopeForCurrency($query, int $currencyId)
    {
        return $query->where('inv_curr_id', $currencyId);
    }
}