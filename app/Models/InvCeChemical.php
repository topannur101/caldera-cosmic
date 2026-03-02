<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeChemical extends Model
{
    protected $table = 'inv_ce_chemicals';
    protected $fillable = ['item_code', 'name', 'inv_ce_vendor_id', 'uom', 'category_chemical', 'photo', 'location_id', 'area_id', 'is_active'];

    public function inv_ce_vendor()
    {
        return $this->belongsTo(InvCeVendor::class);
    }

    public function inv_ce_location()
    {
        return $this->belongsTo(InvCeLocation::class);
    }

    public function inv_ce_area()
    {
        return $this->belongsTo(InvCeArea::class);
    }
}
