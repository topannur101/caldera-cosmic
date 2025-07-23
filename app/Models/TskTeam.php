<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TskTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name', 
        'desc',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tsk_projects()
    {
        return $this->hasMany(TskProject::class);
    }

    public function tsk_auths()
    {
        return $this->hasMany(TskAuth::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tsk_auths')
                    ->withPivot(['perms', 'role', 'is_active'])
                    ->withTimestamps();
    }
}
