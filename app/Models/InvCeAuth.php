<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeAuth extends Model
{
    protected $table = 'inv_ce_auths';
    protected $fillable = ['user_id', 'rf_code', 'area', 'action', 'action_data', 'resource_type', 'resource_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inv_ce_circs()
    {
        return $this->hasMany(InvCeCirc::class, 'inv_ce_auth_id');
    }
}
