<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InsStcMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'line',
        'ip_address',
        'is_at_adjusted',
        'at_adjust_strength',
    ];

    protected $casts = [
        'line' => 'integer',
        'is_at_adjusted' => 'boolean',
        'at_adjust_strength' => 'array',
    ];

    public function ins_stc_m_logs(): HasMany
    {
        return $this->hasMany(InsStcMLog::class);
    }

    public function ins_stc_d_sums(): HasMany
    {
        return $this->hasMany(InsStcDSum::class);
    }

    public function ins_stc_m_log($position): HasOne
    {
        return $this->hasOne(InsStcMLog::class)
            ->latest()
            ->where('position', $position)
            ->where('created_at', '>=', now()->subHour());
    }
}
