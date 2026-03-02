<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeArea extends Model
{
    protected $table = 'inv_ce_areas';
    protected $fillable = ['name', 'is_active'];

    public function stocks()
    {
        return $this->hasMany(InvCeStock::class);
    }
}
