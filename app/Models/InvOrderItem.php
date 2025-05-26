<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvOrderItem extends Model
{
    use HasFactory;

    protected $table = 'inv_order_items';

    protected $fillable = [
        'inv_order_id',
        'inv_item_id',
        'inv_area_id',
        'inv_curr_id',
        'inv_order_budget_id',
        'name',
        'desc',
        'code',
        'photo',
        'purpose',
        'qty',
        'uom',
        'unit_price',
        'total_amount',
        'amount_budget',
        'exchange_rate_used',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_budget' => 'decimal:2',
        'exchange_rate_used' => 'decimal:2',
    ];

    /**
     * Get the order this item belongs to (null for open orders)
     */
    public function inv_order(): BelongsTo
    {
        return $this->belongsTo(InvOrder::class, 'inv_order_id');
    }

    /**
     * Get the inventory item this is based on (nullable)
     */
    public function inv_item(): BelongsTo
    {
        return $this->belongsTo(InvItem::class, 'inv_item_id');
    }

    /**
     * Get the area this item belongs to
     */
    public function inv_area(): BelongsTo
    {
        return $this->belongsTo(InvArea::class, 'inv_area_id');
    }

    /**
     * Get the currency for this item
     */
    public function inv_curr(): BelongsTo
    {
        return $this->belongsTo(InvCurr::class, 'inv_curr_id');
    }

    /**
     * Get the budget this item is allocated to
     */
    public function inv_order_budget(): BelongsTo
    {
        return $this->belongsTo(InvOrderBudget::class, 'inv_order_budget_id');
    }

    /**
     * Get all evaluations/revisions for this item
     */
    public function inv_order_evals(): HasMany
    {
        return $this->hasMany(InvOrderEval::class, 'inv_order_item_id');
    }

    /**
     * Get the latest evaluation for this item
     */
    public function latest_inv_order_eval()
    {
        return $this->hasOne(InvOrderEval::class, 'inv_order_item_id')->latest();
    }

    /**
     * Check if this is an open order (not yet finalized)
     */
    public function getIsOpenOrderAttribute(): bool
    {
        return is_null($this->inv_order_id);
    }

    /**
     * Check if this is a finalized order
     */
    public function getIsFinalizedAttribute(): bool
    {
        return !is_null($this->inv_order_id);
    }

    /**
     * Check if this item is based on an existing inventory item
     */
    public function getIsInventoryBasedAttribute(): bool
    {
        return !is_null($this->inv_item_id);
    }

    /**
     * Check if this item was manually entered
     */
    public function getIsManualEntryAttribute(): bool
    {
        return is_null($this->inv_item_id);
    }

    /**
     * Get formatted photo URL
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? '/storage/inv-order-items/' . $this->photo : null;
    }

    /**
     * Calculate total amount based on quantity and unit price
     */
    public function calculateTotalAmount(): void
    {
        $this->total_amount = $this->qty * $this->unit_price;
    }

    /**
     * Calculate budget amount based on total amount and exchange rate
     */
    public function calculateBudgetAmount(): void
    {
        $this->amount_budget = $this->total_amount * $this->exchange_rate_used;
    }

    /**
     * Update budget allocation with current exchange rate
     */
    public function updateBudgetAllocation(): void
    {
        $this->calculateTotalAmount();
        
        // Get current exchange rate
        $itemCurrency = $this->inv_curr;
        $budgetCurrency = $this->inv_order_budget->inv_curr;
        
        if ($itemCurrency->id !== $budgetCurrency->id) {
            $this->exchange_rate_used = $budgetCurrency->rate / $itemCurrency->rate;
        } else {
            $this->exchange_rate_used = 1.00;
        }
        
        $this->calculateBudgetAmount();
        $this->save();
    }

    /**
     * Scope to get only open orders
     */
    public function scopeOpenOrders($query)
    {
        return $query->whereNull('inv_order_id');
    }

    /**
     * Scope to get only finalized orders
     */
    public function scopeFinalizedOrders($query)
    {
        return $query->whereNotNull('inv_order_id');
    }

    /**
     * Scope to filter by area
     */
    public function scopeForArea($query, int $areaId)
    {
        return $query->where('inv_area_id', $areaId);
    }

    /**
     * Scope to filter by budget
     */
    public function scopeForBudget($query, int $budgetId)
    {
        return $query->where('inv_order_budget_id', $budgetId);
    }

    /**
     * Scope to search by name, description, or code
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('desc', 'like', "%{$term}%")
              ->orWhere('code', 'like', "%{$term}%")
              ->orWhere('purpose', 'like', "%{$term}%");
        });
    }

    /**
     * Scope to filter by purpose
     */
    public function scopeByPurpose($query, string $purpose)
    {
        return $query->where('purpose', 'like', "%{$purpose}%");
    }
}