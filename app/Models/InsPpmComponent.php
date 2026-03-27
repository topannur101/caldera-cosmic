<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsPpmComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'part_name',
        'base_part_name',
        'description',
        'material_number',
        'material_name',
        'mcs_number',
        'vendor_type',
        'hera_hardness',
        'size_distribution',
    ];

    protected $casts = [
        'size_distribution' => 'array',
    ];

    /**
     * Get the product that owns this component
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(InsPpmProduct::class, 'product_id');
    }

    /**
     * Get the processes for this component
     */
    public function processes(): HasMany
    {
        return $this->hasMany(InsPpmComponentsProcess::class, 'component_id');
    }
}
