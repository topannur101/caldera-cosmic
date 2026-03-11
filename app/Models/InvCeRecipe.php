<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeRecipe extends Model
{
    protected $table = 'inv_ce_recipes';

    protected $fillable = [
        'line',
        'model',
        'area',
        'chemical_id',
        'hardener_id',
        'hardener_ratio',
        'output_code',
        'potlife',
        'is_active',
        'additional_settings',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'hardener_ratio'      => 'float',
        'potlife'             => 'float',
        'additional_settings' => 'array',
    ];

    /**
     * Base chemical (Component A).
     */
    public function chemical()
    {
        return $this->belongsTo(InvCeChemical::class, 'chemical_id');
    }

    /**
     * Hardener chemical (Component B).
     */
    public function hardener()
    {
        return $this->belongsTo(InvCeChemical::class, 'hardener_id');
    }
}
