<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsOmvMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_omv_recipe_id',
        'line',
        'code',
        'team',
        'user_1_id',
        'user_2_id',
        'eval',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function ins_omv_recipe(): BelongsTo
    {
        return $this->belongsTo(InsOmvRecipe::class);
    }

    public function ins_omv_captures(): HasMany
    {
        return $this->hasMany(InsOmvCapture::class);
    }

    public function capturesCount()
    {
        return $this->ins_omv_captures->count();
    }

    public function user_1(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function user_2(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function duration()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at);
        
        $duration = $start->diffInSeconds($end);
        
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        
        return sprintf("%02d:%02d", $minutes, $seconds);
    }

    public function evalFriendly()
    {
        $eval = '';
        switch ($this->eval) {
            case 'on_time':
                $eval = __('Tepat waktu');
                break;
            
            case 'too_soon':
                $eval = __('Terlalu awal');
                break;
            case 'too_late':
                $eval = __('Terlambat');
                break;
        }
        return $eval;
    }
}
