<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvOrderEval extends Model
{
    use HasFactory;

    protected $table = 'inv_order_evals';

    protected $fillable = [
        'inv_order_item_id',
        'user_id',
        'qty_before',
        'qty_after',
        'message',
    ];

    protected $casts = [
        'qty_before' => 'integer',
        'qty_after' => 'integer',
    ];

    /**
     * Get the order item this evaluation belongs to
     */
    public function inv_order_item(): BelongsTo
    {
        return $this->belongsTo(InvOrderItem::class, 'inv_order_item_id');
    }

    /**
     * Get the user who made this evaluation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the quantity change amount
     */
    public function getQuantityChangeAttribute(): int
    {
        return $this->qty_after - $this->qty_before;
    }

    /**
     * Check if this evaluation increased quantity
     */
    public function getIsIncreaseAttribute(): bool
    {
        return $this->qty_after > $this->qty_before;
    }

    /**
     * Check if this evaluation decreased quantity
     */
    public function getIsDecreaseAttribute(): bool
    {
        return $this->qty_after < $this->qty_before;
    }

    /**
     * Check if this evaluation kept quantity the same
     */
    public function getIsNoChangeAttribute(): bool
    {
        return $this->qty_after === $this->qty_before;
    }

    /**
     * Get formatted change description
     */
    public function getChangeDescriptionAttribute(): string
    {
        $change = $this->quantity_change;
        
        if ($change > 0) {
            return "+{$change}";
        } elseif ($change < 0) {
            return (string) $change;
        } else {
            return "No change";
        }
    }

    /**
     * Scope to filter by order item
     */
    public function scopeForOrderItem($query, int $orderItemId)
    {
        return $query->where('inv_order_item_id', $orderItemId);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get only evaluations with quantity increases
     */
    public function scopeIncreases($query)
    {
        return $query->whereColumn('qty_after', '>', 'qty_before');
    }

    /**
     * Scope to get only evaluations with quantity decreases
     */
    public function scopeDecreases($query)
    {
        return $query->whereColumn('qty_after', '<', 'qty_before');
    }

    /**
     * Scope to get evaluations with no quantity change
     */
    public function scopeNoChange($query)
    {
        return $query->whereColumn('qty_after', '=', 'qty_before');
    }
}