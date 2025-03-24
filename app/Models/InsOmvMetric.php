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
        'ins_rubber_batch_id',
        'data',
        'kwh_usage'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function ins_rubber_batch(): BelongsTo
    {
        return $this->belongsTo(InsRubberBatch::class);
    }

    public function ins_omv_recipe(): BelongsTo
    {
        return $this->belongsTo(InsOmvRecipe::class);
    }

    public function ins_omv_captures(): HasMany
    {
        return $this->hasMany(InsOmvCapture::class)->orderBy('taken_at', 'asc');;
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

    public function durationSeconds(): int
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at);
        
        return $start->diffInSeconds($end);
    }

    public function duration()
    {
        $duration_seconds = $this->durationSeconds();
        $minutes = floor($duration_seconds / 60);
        $seconds = $duration_seconds % 60;
        
        return sprintf("%02d:%02d", $minutes, $seconds);
    }

    public function evalHuman()
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
            case 'on_time_manual':
                $eval = __('Tepat waktu');
                break;
        }
        return $eval;
    }
}
