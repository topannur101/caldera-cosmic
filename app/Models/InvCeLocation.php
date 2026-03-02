<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeLocation extends Model
{
    protected $table = 'inv_ce_locations';
    protected $fillable = ['parent', 'bin', 'is_active'];

    public function chemicals()
    {
        return $this->hasMany(InvCeChemical::class);
    }

    public function area()
    {
        return $this->belongsTo(InvCeArea::class, 'area_id');
    }
}
