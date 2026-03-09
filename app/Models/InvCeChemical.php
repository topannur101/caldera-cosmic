<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeChemical extends Model
{
    protected $table = 'inv_ce_chemicals';
    protected $fillable = ['item_code', 'name', 'inv_ce_vendor_id', 'uom','unit_price', 'category_chemical', 'photo', 'location_id', 'area_id', 'is_active', 'status_bom'];

    public function inv_ce_vendor()
    {
        return $this->belongsTo(InvCeVendor::class, 'inv_ce_vendor_id');
    }

    public function inv_ce_location()
    {
        return $this->belongsTo(InvCeLocation::class, 'location_id');
    }

    public function inv_ce_area()
    {
        return $this->belongsTo(InvCeArea::class, 'area_id');
    }

    public function inv_ce_stocks()
    {
        return $this->hasMany(InvCeStock::class, 'inv_ce_chemical_id');
    }

    /**
     * Recipes where this chemical is the base component (A).
     */
    public function recipes_as_chemical()
    {
        return $this->hasMany(InvCeRecipe::class, 'chemical_id');
    }

    /**
     * Recipes where this chemical is the hardener (B).
     */
    public function recipes_as_hardener()
    {
        return $this->hasMany(InvCeRecipe::class, 'hardener_id');
    }
}
