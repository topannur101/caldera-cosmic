<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsOmvRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'capture_points',
        'steps'
    ];

    protected $casts = [
        'capture_points' => 'array',
        'steps' => 'array',
    ];

    public function capturePointsCount()
    {
        $capture_points = json_decode($this->capture_points ?? '{}', true);
        return count($capture_points);
    }

    public function stepsCount()
    {
        $steps = json_decode($this->steps ?? '{}', true);
        return count($steps);
    }


}
