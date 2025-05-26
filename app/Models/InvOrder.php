<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvOrder extends Model
{
    use HasFactory;

    protected $table = 'inv_orders';

    protected $fillable = [
        'user_id',
        'order_number',
        'notes',
    ];

    /**
     * Get the user who created this order
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all items in this order
     */
    public function inv_order_items(): HasMany
    {
        return $this->hasMany(InvOrderItem::class, 'inv_order_id');
    }

    /**
     * Get all budget snapshots for this order
     */
    public function inv_order_budget_snapshots(): HasMany
    {
        return $this->hasMany(InvOrderBudgetSnapshot::class, 'inv_order_id');
    }

    /**
     * Get the total amount of this order in various currencies
     */
    public function getTotalAmountsByCurrency(): array
    {
        return $this->inv_order_items()
            ->selectRaw('inv_curr_id, SUM(total_amount) as total')
            ->with('inv_curr')
            ->groupBy('inv_curr_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->inv_curr->name => $item->total];
            })
            ->toArray();
    }

    /**
     * Get the total budget allocation of this order
     */
    public function getTotalBudgetAllocation(): string
    {
        return $this->inv_order_items()->sum('amount_budget');
    }

    /**
     * Get all affected budgets for this order
     */
    public function getAffectedBudgets()
    {
        return InvOrderBudget::whereIn('id', 
            $this->inv_order_items()->distinct()->pluck('inv_order_budget_id')
        )->get();
    }

    /**
     * Generate unique order number
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        
        $lastOrder = static::whereDate('created_at', now())
            ->orderBy('id', 'desc')
            ->first();
            
        $sequence = $lastOrder ? (int) substr($lastOrder->order_number, -4) + 1 : 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to search by order number or notes
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('order_number', 'like', "%{$term}%")
              ->orWhere('notes', 'like', "%{$term}%");
        });
    }
}