<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsCtcAuth extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'actions'];

    protected $casts = [
        'actions' => 'array',
    ];

    // Relasi ke User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Optional: method untuk hitung jumlah action
    public function countActions()
    {
        $actions = $this->actions;
        if (is_string($actions)) {
            // Jika string, coba decode ke array
            $actions = json_decode($actions, true);
        }
        return count($actions ?: []);
    }
}