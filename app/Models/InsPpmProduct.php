<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InsPpmProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'dev_style',
        'product_code',
        'color_way',
        'production_date',
    ];

    protected $casts = [
        'production_date' => 'date',
    ];

    /**
     * Get the components for this product
     */
    public function components(): HasMany
    {
        return $this->hasMany(InsPpmComponent::class, 'product_id');
    }

    /**
     * Get line running products for this product
     */
    public function lineRunningProducts(): HasMany
    {
        return $this->hasMany(InsPpmLineRunningProduct::class, 'product_id');
    }
}
