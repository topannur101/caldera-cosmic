<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsStcAdjust extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_stc_d_sum_id',
        'current_temp',
        'delta_temp',
        'sv_before',
        'sv_after',
        'adjustment_applied',
        'adjustment_reason',
    ];

    protected $casts = [
        'current_temp' => 'float',
        'delta_temp' => 'float',
        'sv_before' => 'array',
        'sv_after' => 'array',
        'adjustment_applied' => 'boolean',
    ];

    /**
     * Get the d_sum that this adjustment belongs to
     */
    public function ins_stc_d_sum(): BelongsTo
    {
        return $this->belongsTo(InsStcDSum::class);
    }

    /**
     * Get adjustment status in human-readable format
     */
    public function getStatusAttribute(): string
    {
        if ($this->adjustment_applied) {
            return 'Applied';
        }

        if (str_contains($this->adjustment_reason, 'DRY RUN')) {
            return 'Dry Run';
        }

        return 'Failed';
    }

    /**
     * Get formatted delta temperature with sign
     */
    public function getFormattedDeltaAttribute(): string
    {
        return sprintf('%+.1f°C', $this->delta_temp);
    }

    /**
     * Get formatted created_at timestamp
     */
    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('Y-m-d H:i');
    }

    /**
     * Get SV change summary
     */
    public function getSvChangeSummaryAttribute(): string
    {
        $before = $this->sv_before;
        $after = $this->sv_after;

        if (! is_array($before) || ! is_array($after) || count($before) !== 8 || count($after) !== 8) {
            return 'Invalid SV data';
        }

        $changes = [];
        for ($i = 0; $i < 8; $i++) {
            $diff = $after[$i] - $before[$i];
            if ($diff != 0) {
                $changes[] = sprintf('SV%d: %+.1f', $i + 1, $diff);
            }
        }

        return empty($changes) ? 'No changes' : implode(', ', $changes);
    }
}
