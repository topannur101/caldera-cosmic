<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvOrderBudgetSnapshot extends Model
{
    use HasFactory;

    protected $table = 'inv_order_budget_snapshots';

    protected $fillable = [
        'inv_order_id',
        'inv_order_budget_id',
        'balance_before',
        'balance_after',
        'inv_curr_id',
    ];

    protected $casts = [
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * Get the order this snapshot belongs to
     */
    public function inv_order(): BelongsTo
    {
        return $this->belongsTo(InvOrder::class, 'inv_order_id');
    }

    /**
     * Get the budget this snapshot is for
     */
    public function inv_order_budget(): BelongsTo
    {
        return $this->belongsTo(InvOrderBudget::class, 'inv_order_budget_id');
    }

    /**
     * Get the currency for this snapshot
     */
    public function inv_curr(): BelongsTo
    {
        return $this->belongsTo(InvCurr::class, 'inv_curr_id');
    }

    /**
     * Get the amount allocated by this order
     */
    public function getAllocatedAmountAttribute(): string
    {
        return $this->balance_before - $this->balance_after;
    }

    /**
     * Check if this snapshot shows a budget reduction
     */
    public function getIsBudgetReductionAttribute(): bool
    {
        return $this->balance_after < $this->balance_before;
    }

    /**
     * Check if this snapshot shows a budget increase
     */
    public function getIsBudgetIncreaseAttribute(): bool
    {
        return $this->balance_after > $this->balance_before;
    }

    /**
     * Get the percentage of budget used
     */
    public function getBudgetUsagePercentageAttribute(): float
    {
        if ($this->balance_before == 0) {
            return 0;
        }
        
        return ($this->allocated_amount / $this->balance_before) * 100;
    }

    /**
     * Scope to filter by order
     */
    public function scopeForOrder($query, int $orderId)
    {
        return $query->where('inv_order_id', $orderId);
    }

    /**
     * Scope to filter by budget
     */
    public function scopeForBudget($query, int $budgetId)
    {
        return $query->where('inv_order_budget_id', $budgetId);
    }

    /**
     * Scope to get snapshots with budget reductions
     */
    public function scopeBudgetReductions($query)
    {
        return $query->whereColumn('balance_after', '<', 'balance_before');
    }

    /**
     * Scope to get snapshots with budget increases
     */
    public function scopeBudgetIncreases($query)
    {
        return $query->whereColumn('balance_after', '>', 'balance_before');
    }
}