<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TskProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
        'code',
        'tsk_team_id',
        'user_id',
        'status',
        'start_date',
        'end_date',
        'priority',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function tsk_team()
    {
        return $this->belongsTo(TskTeam::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tsk_items()
    {
        return $this->hasMany(TskItem::class);
    }

    public function com_items()
    {
        return ComItem::where('model_name', 'TskProject')->where('model_id', $this->id);
    }
}
