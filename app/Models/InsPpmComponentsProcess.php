<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsPpmComponentsProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'process_data',
    ];

    protected $casts = [
        'process_data' => 'array',
    ];

    /**
     * Get the component that owns this process
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(InsPpmComponent::class, 'component_id');
    }
}
