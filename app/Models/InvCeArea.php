<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeArea extends Model
{
    protected $table = 'inv_ce_areas';
    protected $fillable = ['name', 'is_active'];

    public function inv_ce_chemicals()
    {
        return $this->hasMany(InvCeChemical::class, 'area_id');
    }

    public function stocks()
    {
        return $this->hasManyThrough(
            InvCeStock::class,
            InvCeChemical::class,
            'area_id',
            'inv_ce_chemical_id'
        );
    }
}
