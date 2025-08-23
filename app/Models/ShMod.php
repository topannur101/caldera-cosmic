<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShMod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            $model->manageActive();
        });

        static::updated(function ($model) {
            $model->manageActive();
        });
    }

    public function manageActive()
    {
        if ($this->is_active) {
            $activeCount = self::where('is_active', true)->count();

            if ($activeCount > 100) {
                self::where('is_active', true)
                    ->orderBy('updated_at')
                    ->first()
                    ->update(['is_active' => false]);
            }
        }
    }
}
