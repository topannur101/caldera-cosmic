<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsCtcMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_ctc_machine_id',
        'ins_rubber_batch_id',
        'ins_ctc_recipe_id',
        'is_auto',
        'correction_uptime',
        'correction_rate',
        't_mae_left',
        't_ssd_left',
        't_avg_left',
        't_mae_right',
        't_ssd_right',
        't_avg_right',
        't_balance',
        't_mae',
        't_ssd',
        't_avg',
        'data',
    ];

    protected $casts = [
        'data' => 'array',  // This is crucial for JSON handling
        'is_auto' => 'boolean',
        'correction_uptime' => 'integer',
        'correction_rate' => 'integer',
        't_mae_left' => 'decimal:2',
        't_mae_right' => 'decimal:2',
        't_mae' => 'decimal:2',
        't_ssd_left' => 'decimal:2',
        't_ssd_right' => 'decimal:2',
        't_ssd' => 'decimal:2',
        't_avg_left' => 'decimal:2',
        't_avg_right' => 'decimal:2',
        't_avg' => 'decimal:2',
        't_balance' => 'decimal:2',
    ];

    /**
     * Get the machine that this metric belongs to
     */
    public function ins_ctc_machine(): BelongsTo
    {
        return $this->belongsTo(InsCtcMachine::class);
    }

    /**
     * Get the recipe used for this metric
     */
    public function ins_ctc_recipe(): BelongsTo
    {
        return $this->belongsTo(InsCtcRecipe::class);
    }

    /**
     * Get the rubber batch processed
     */
    public function ins_rubber_batch(): BelongsTo
    {
        return $this->belongsTo(InsRubberBatch::class);
    }

    /**
     * Check if this batch passed quality control
     * Based on MAE threshold of 1.0
     */
    public function getQualityStatusAttribute(): string
    {
        return $this->t_mae <= 1.0 ? 'pass' : 'fail';
    }

    /**
     * Get the duration of this batch from the data array
     */
    public function getDurationAttribute(): string
    {
        if (! $this->data || ! is_array($this->data) || count($this->data) < 2) {
            return '00:00:00';
        }

        $firstTimestamp = $this->data[0][0] ?? null;
        $lastTimestamp = $this->data[count($this->data) - 1][0] ?? null;

        if (! $firstTimestamp || ! $lastTimestamp) {
            return '00:00:00';
        }

        try {
            $start = new \DateTime($firstTimestamp);
            $end = new \DateTime($lastTimestamp);
            $interval = $start->diff($end);

            return sprintf('%02d:%02d:%02d',
                $interval->h,
                $interval->i,
                $interval->s
            );
        } catch (\Exception $e) {
            return '00:00:00';
        }
    }

    /**
     * Get the start time of this batch from the data array
     */
    public function getStartedAtAttribute(): string
    {
        if (! $this->data || ! is_array($this->data) || count($this->data) === 0) {
            return 'N/A';
        }

        $firstTimestamp = $this->data[0][0] ?? null;

        if (! $firstTimestamp) {
            return 'N/A';
        }

        try {
            return (new \DateTime($firstTimestamp))->format('H:i:s');
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Get formatted correction uptime with percentage
     */
    public function getFormattedCorrectionUptimeAttribute(): string
    {
        return $this->correction_uptime.'%';
    }

    /**
     * Get formatted correction rate with percentage
     */
    public function getFormattedCorrectionRateAttribute(): string
    {
        return $this->correction_rate.'%';
    }

    /**
     * Get AVG evaluation based on balance (BAL)
     */
    public function getAvgEvaluationAttribute(): array
    {
        $bal = $this->t_balance;
        $absBAL = abs($bal);

        if ($absBAL <= 0.3) {
            return [
                'status' => 'seimbang',
                'color' => 'text-green-600',
                'icon_color' => 'text-green-500',
                'is_good' => true,
            ];
        } elseif ($bal > 1) {
            return [
                'status' => 'jomplang kiri',
                'color' => 'text-red-600',
                'icon_color' => 'text-red-500',
                'is_good' => false,
            ];
        } else {
            return [
                'status' => 'jomplang kanan',
                'color' => 'text-red-600',
                'icon_color' => 'text-red-500',
                'is_good' => false,
            ];
        }
    }

    /**
     * Get MAE evaluation based on standard threshold
     */
    public function getMaeEvaluationAttribute(): array
    {
        $mae = $this->t_mae;

        if ($mae <= 0.3) {
            return [
                'status' => 'sesuai standar',
                'color' => 'text-green-600',
                'icon_color' => 'text-green-500',
                'is_good' => true,
            ];
        } else {
            return [
                'status' => 'di luar standar',
                'color' => 'text-red-600',
                'icon_color' => 'text-red-500',
                'is_good' => false,
            ];
        }
    }

    /**
     * Get SSD evaluation based on consistency threshold
     */
    public function getSsdEvaluationAttribute(): array
    {
        $ssd = $this->t_ssd;

        if ($ssd <= 0.3) {
            return [
                'status' => 'tebal konsisten',
                'color' => 'text-green-600',
                'icon_color' => 'text-green-500',
                'is_good' => true,
            ];
        } else {
            return [
                'status' => 'tebal fluktuatif',
                'color' => 'text-red-600',
                'icon_color' => 'text-red-500',
                'is_good' => false,
            ];
        }
    }

    /**
     * Get Correction evaluation based on correction uptime
     */
    public function getCorrectionEvaluationAttribute(): array
    {
        $cu = $this->correction_uptime;

        if ($cu > 40) {
            return [
                'status' => 'auto',
                'color' => 'text-green-600',
                'icon_color' => 'text-green-500',
                'is_good' => true,
            ];
        } else {
            return [
                'status' => 'manual',
                'color' => 'text-red-600',
                'icon_color' => 'text-red-500',
                'is_good' => false,
            ];
        }
    }

    /**
     * Get all evaluations at once
     */
    public function getAllEvaluationsAttribute(): array
    {
        return [
            'avg' => $this->avg_evaluation,
            'mae' => $this->mae_evaluation,
            'ssd' => $this->ssd_evaluation,
            'correction' => $this->correction_evaluation,
        ];
    }

    /**
     * Get overall batch score based on all evaluations
     */
    public function getBatchScoreAttribute(): array
    {
        $evaluations = $this->all_evaluations;
        $goodCount = 0;
        $totalCount = count($evaluations);

        foreach ($evaluations as $eval) {
            if ($eval['is_good']) {
                $goodCount++;
            }
        }

        $percentage = round(($goodCount / $totalCount) * 100);

        return [
            'good_count' => $goodCount,
            'total_count' => $totalCount,
            'percentage' => $percentage,
            'grade' => $this->getGradeFromPercentage($percentage),
        ];
    }

    /**
     * Get grade letter based on percentage
     */
    private function getGradeFromPercentage(int $percentage): string
    {
        if ($percentage >= 90) {
            return 'A';
        }
        if ($percentage >= 80) {
            return 'B';
        }
        if ($percentage >= 70) {
            return 'C';
        }
        if ($percentage >= 60) {
            return 'D';
        }

        return 'F';
    }
}
