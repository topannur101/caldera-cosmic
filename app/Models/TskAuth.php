<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TskAuth extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tsk_team_id',
        'perms',
        'role',
        'is_active'
    ];

    protected $casts = [
        'perms' => 'array',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tsk_team()
    {
        return $this->belongsTo(TskTeam::class);
    }

    public function hasPermission($permission)
    {
        return in_array($permission, $this->perms ?? []);
    }

    public function isLeader()
    {
        return $this->role === 'leader';
    }

    public function isMember()
    {
        return $this->role === 'member';
    }
}
