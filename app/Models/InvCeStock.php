<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeStock extends Model
{
    protected $table = 'inv_ce_stocks';
    protected $fillable = ['inv_ce_chemical_id', 'quantity', 'unit_size', 'unit_uom', 'lot_number', 'expiry_date', 'planning_area', 'status', 'remarks'];

    public function inv_ce_chemical()
    {
        return $this->belongsTo(InvCeChemical::class);
    }

    public function inv_ce_circ()
    {
        return $this->hasMany(InvCeCirc::class);
    }

    public function inv_ce_return()
    {
        return $this->hasOne(InvCeReturn::class);
    }
}
