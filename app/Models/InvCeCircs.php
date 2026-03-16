<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeCircs extends Model
{
    protected $table = 'inv_ce_circs';
    protected $fillable = ['inv_ce_stock_id', 'inv_ce_auth_id', 'actual_area', 'issued_quantity', 'type_circ', 'remarks'];

    public function inv_ce_stock()
    {
        return $this->belongsTo(InvCeStock::class, 'inv_ce_stock_id');
    }

    public function inv_ce_auth()
    {
        return $this->belongsTo(InvCeAuth::class, 'inv_ce_auth_id');
    }

    public function inv_ce_return()
    {
        return $this->hasOne(InvCeReturn::class);
    }
}
