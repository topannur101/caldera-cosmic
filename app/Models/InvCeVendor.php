<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeVendor extends Model
{
    protected $table = 'inv_ce_vendors';
    protected $fillable = ['name', 'is_active'];

    public function chemicals()
    {
        return $this->hasMany(InvCeChemical::class, 'inv_ce_vendor_id');
    }

    public function inv_ce_chemicals()
    {
        return $this->chemicals();
    }
}
