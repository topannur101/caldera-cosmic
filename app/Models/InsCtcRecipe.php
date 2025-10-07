<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class InsCtcRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'og_rs',
        'std_min',
        'std_max',
        'scale',
        'pfc_min',
        'pfc_max',
        'priority',
        'is_active',
        'recommended_for_models',
    ];

    protected $casts = [
        'std_min' => 'decimal:2',
        'std_max' => 'decimal:2',
        'scale' => 'decimal:2',
        'pfc_min' => 'decimal:2',
        'pfc_max' => 'decimal:2',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'recommended_for_models' => 'array',
    ];

    protected $appends = [
        'std_mid', // Add computed attribute to JSON output
        'target_thickness',
    ];

    /**
     * Get all metrics that use this recipe
     */
    public function ins_ctc_metrics(): HasMany
    {
        return $this->hasMany(InsCtcMetric::class);
    }

    /**
     * Get machines that have used this recipe
     */
    public function machines()
    {
        return $this->belongsToMany(InsCtcMachine::class, 'ins_ctc_metrics', 'ins_ctc_recipe_id', 'ins_ctc_machine_id')
            ->distinct();
    }

    /**
     * Computed attribute: std_mid (target thickness)
     * Automatically calculates the middle point between std_min and std_max
     */
    public function getStdMidAttribute(): float
    {
        return round(($this->std_min + $this->std_max) / 2, 2);
    }

    /**
     * Alias for std_mid - more descriptive name
     */
    public function getTargetThicknessAttribute(): float
    {
        return $this->std_mid;
    }

    /**
     * Get the thickness tolerance (std_max - std_min)
     */
    public function getToleranceAttribute(): float
    {
        return round($this->std_max - $this->std_min, 2);
    }

    /**
     * Get the PFC (Pre-Final Check) target thickness
     */
    public function getPfcTargetAttribute(): float
    {
        return round(($this->pfc_min + $this->pfc_max) / 2, 2);
    }

    /**
     * Get the PFC tolerance
     */
    public function getPfcToleranceAttribute(): float
    {
        return round($this->pfc_max - $this->pfc_min, 2);
    }

    /**
     * Check if a thickness value is within standard range
     */
    public function isWithinStandardRange(float $thickness): bool
    {
        return $thickness >= $this->std_min && $thickness <= $this->std_max;
    }

    /**
     * Check if a thickness value is within PFC range
     */
    public function isWithinPfcRange(float $thickness): bool
    {
        return $thickness >= $this->pfc_min && $thickness <= $this->pfc_max;
    }

    /**
     * Get quality rating based on how close the thickness is to target
     */
    public function getQualityRating(float $thickness): string
    {
        $target = $this->target_thickness;
        $tolerance = $this->tolerance;

        // Calculate deviation from target as percentage of tolerance
        $deviation = abs($thickness - $target);
        $deviation_percentage = $tolerance > 0 ? ($deviation / ($tolerance / 2)) * 100 : 0;

        if ($deviation_percentage <= 25) {
            return 'Excellent';
        } elseif ($deviation_percentage <= 50) {
            return 'Good';
        } elseif ($deviation_percentage <= 75) {
            return 'Fair';
        } elseif ($this->isWithinStandardRange($thickness)) {
            return 'Acceptable';
        } else {
            return 'Poor';
        }
    }

    /**
     * Calculate Mean Absolute Error for a set of measurements
     */
    public function calculateMae(array $measurements): float
    {
        if (empty($measurements)) {
            return 0;
        }

        $target = $this->target_thickness;
        $errors = array_map(fn ($measurement) => abs($measurement - $target), $measurements);

        return round(array_sum($errors) / count($errors), 2);
    }

    /**
     * Get recent performance metrics for this recipe
     */
    public function getRecentPerformance(int $days = 7): array
    {
        $metrics = $this->ins_ctc_metrics()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        if ($metrics->isEmpty()) {
            return [
                'batch_count' => 0,
                'avg_mae' => null,
                'avg_ssd' => null,
                'avg_thickness' => null,
                'quality_score' => null,
            ];
        }

        return [
            'batch_count' => $metrics->count(),
            'avg_mae' => round($metrics->avg('t_mae'), 2),
            'avg_ssd' => round($metrics->avg('t_ssd'), 2),
            'avg_thickness' => round($metrics->avg('t_avg'), 2),
            'avg_balance' => round($metrics->avg('t_balance'), 2),
            'quality_score' => $this->calculateQualityScore($metrics),
        ];
    }

    /**
     * Calculate overall quality score for a collection of metrics
     */
    private function calculateQualityScore($metrics): float
    {
        if ($metrics->isEmpty()) {
            return 0;
        }

        $avg_mae = $metrics->avg('t_mae');
        $avg_ssd = $metrics->avg('t_ssd');
        $avg_balance = abs($metrics->avg('t_balance'));

        $tolerance = $this->tolerance;

        // Quality score calculation (0-100)
        $quality_score = 100;

        // Penalize high MAE relative to tolerance
        if ($avg_mae > ($tolerance * 0.1)) {
            $quality_score -= (($avg_mae - ($tolerance * 0.1)) / $tolerance) * 30;
        }

        // Penalize high SSD relative to tolerance
        if ($avg_ssd > ($tolerance * 0.05)) {
            $quality_score -= (($avg_ssd - ($tolerance * 0.05)) / $tolerance) * 40;
        }

        // Penalize imbalance
        if ($avg_balance > ($tolerance * 0.1)) {
            $quality_score -= (($avg_balance - ($tolerance * 0.1)) / $tolerance) * 30;
        }

        return round(max(0, min(100, $quality_score)), 1);
    }

    /**
     * Get machines currently using this recipe
     */
    public function activeMachines()
    {
        return InsCtcMachine::whereHas('ins_ctc_metrics', function ($query) {
            $query->where('ins_ctc_recipe_id', $this->id)
                ->where('created_at', '>=', now()->subHours(1));
        });
    }

    /**
     * Get summary statistics for this recipe
     */
    public function getSummaryStats(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'target_thickness' => $this->target_thickness,
            'tolerance' => $this->tolerance,
            'std_range' => "{$this->std_min} - {$this->std_max}",
            'pfc_range' => "{$this->pfc_min} - {$this->pfc_max}",
            'og_rs' => $this->og_rs,
            'scale' => $this->scale,
        ];
    }

    /**
     * Validation rules for recipe data
     */
    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:ins_ctc_recipes,name',
            'og_rs' => 'required|string|max:255',
            'std_min' => 'required|numeric|min:0|max:99.99',
            'std_max' => 'required|numeric|min:0|max:99.99|gte:std_min',
            'scale' => 'required|numeric|min:0|max:99.99',
            'pfc_min' => 'required|numeric|min:0|max:99.99',
            'pfc_max' => 'required|numeric|min:0|max:99.99|gte:pfc_min',
        ];
    }

    /**
     * Boot method to add model validation
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($recipe) {
            // Ensure std_min <= std_max
            if ($recipe->std_min > $recipe->std_max) {
                throw ValidationException::withMessages([
                    'std_max' => 'Standard maximum must be greater than or equal to standard minimum.',
                ]);
            }

            // Ensure pfc_min <= pfc_max
            if ($recipe->pfc_min > $recipe->pfc_max) {
                throw ValidationException::withMessages([
                    'pfc_max' => 'PFC maximum must be greater than or equal to PFC minimum.',
                ]);
            }
        });
    }

    /**
     * Scope for recipes within a thickness range
     */
    public function scopeForThicknessRange($query, float $min, float $max)
    {
        return $query->where(function ($q) use ($min, $max) {
            $q->whereBetween('std_min', [$min, $max])
                ->orWhereBetween('std_max', [$min, $max])
                ->orWhere(function ($qq) use ($min, $max) {
                    $qq->where('std_min', '<=', $min)
                        ->where('std_max', '>=', $max);
                });
        });
    }

    /**
     * Scope for recipes by OG_RS value
     */
    public function scopeByOgRs($query, string $og_rs)
    {
        return $query->where('og_rs', $og_rs);
    }

    /**
     * Scope for active recipes (used in recent metrics)
     */
    public function scopeActive($query, int $days = 30)
    {
        return $query->whereHas('ins_ctc_metrics', function ($subQuery) use ($days) {
            $subQuery->where('created_at', '>=', now()->subDays($days));
        });
    }
}
