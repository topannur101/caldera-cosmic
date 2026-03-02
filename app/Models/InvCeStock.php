<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeStock extends Model
{
    protected $table = 'inv_ce_stock';
    protected $fillable = ['inv_ce_chemical_id', 'quantity', 'unit_size', 'unit_uom', 'lot_number', 'unit_price', 'expiry_date', 'planning_area', 'status', 'remarks'];

    public function inv_ce_chemical()
    {
        return $this->belongsTo(InvCeChemical::class, 'inv_ce_chemical_id');
    }

    public function inv_ce_circs()
    {
        return $this->hasMany(InvCeCirc::class, 'inv_ce_stock_id');
    }

    public function inv_ce_circ()
    {
        return $this->inv_ce_circs();
    }

    public function inv_ce_return()
    {
        return $this->hasOne(InvCeReturn::class);
    }
}
