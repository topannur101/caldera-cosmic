<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsCtcMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'line',
        'ip_address',
        'is_active',
    ];

    protected $casts = [
        'line' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get all metrics for this machine
     */
    public function ins_ctc_metrics(): HasMany
    {
        return $this->hasMany(InsCtcMetric::class);
    }

    /**
     * Get all recipes used by this machine (through metrics)
     */
    public function ins_ctc_recipes(): HasMany
    {
        return $this->hasMany(InsCtcRecipe::class, 'id', 'ins_ctc_recipe_id')
            ->join('ins_ctc_metrics', 'ins_ctc_recipes.id', '=', 'ins_ctc_metrics.ins_ctc_recipe_id')
            ->where('ins_ctc_metrics.ins_ctc_machine_id', $this->id)
            ->distinct();
    }

    /**
     * Get rubber batches processed by this machine
     */
    public function ins_rubber_batches(): HasMany
    {
        return $this->hasMany(InsRubberBatch::class, 'id', 'ins_rubber_batch_id')
            ->join('ins_ctc_metrics', 'ins_rubber_batches.id', '=', 'ins_ctc_metrics.ins_rubber_batch_id')
            ->where('ins_ctc_metrics.ins_ctc_machine_id', $this->id)
            ->distinct();
    }

    /**
     * Check if machine is online based on recent activity
     *
     * @param  int  $minutes  Minutes to consider for "recent activity" (default: 60)
     */
    public function is_online(int $minutes = 60): bool
    {
        $latestMetric = $this->ins_ctc_metrics()
            ->latest('created_at')
            ->first();

        if ($latestMetric) {
            $now = Carbon::now();
            $lastActivity = Carbon::parse($latestMetric->created_at);

            return $lastActivity->greaterThanOrEqualTo($now->subMinutes($minutes));
        }

        return false;
    }

    /**
     * Get the latest batch metric for this machine
     */
    public function latest_metric(): ?InsCtcMetric
    {
        return $this->ins_ctc_metrics()
            ->latest('created_at')
            ->first();
    }

    /**
     * Get metrics for a specific time period
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function metrics_between(Carbon $from, Carbon $to)
    {
        return $this->ins_ctc_metrics()
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get metrics for today
     */
    public function today_metrics()
    {
        return $this->metrics_between(
            Carbon::today(),
            Carbon::tomorrow()
        );
    }

    /**
     * Get average thickness metrics for a time period
     */
    public function average_metrics_between(Carbon $from, Carbon $to): array
    {
        $metrics = $this->ins_ctc_metrics()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('
                AVG(t_avg_left) as avg_left,
                AVG(t_avg_right) as avg_right,
                AVG(t_avg) as avg_combined,
                AVG(t_mae_left) as mae_left,
                AVG(t_mae_right) as mae_right,
                AVG(t_mae) as mae_combined,
                AVG(t_ssd_left) as ssd_left,
                AVG(t_ssd_right) as ssd_right,
                AVG(t_ssd) as ssd_combined,
                AVG(t_balance) as balance,
                COUNT(*) as batch_count
            ')
            ->first();

        return [
            'avg_left' => round($metrics->avg_left ?? 0, 2),
            'avg_right' => round($metrics->avg_right ?? 0, 2),
            'avg_combined' => round($metrics->avg_combined ?? 0, 2),
            'mae_left' => round($metrics->mae_left ?? 0, 2),
            'mae_right' => round($metrics->mae_right ?? 0, 2),
            'mae_combined' => round($metrics->mae_combined ?? 0, 2),
            'ssd_left' => round($metrics->ssd_left ?? 0, 2),
            'ssd_right' => round($metrics->ssd_right ?? 0, 2),
            'ssd_combined' => round($metrics->ssd_combined ?? 0, 2),
            'balance' => round($metrics->balance ?? 0, 2),
            'batch_count' => $metrics->batch_count ?? 0,
        ];
    }

    /**
     * Get quality statistics for a time period
     */
    public function quality_stats_between(Carbon $from, Carbon $to): array
    {
        $metrics = $this->average_metrics_between($from, $to);

        // Calculate quality indicators
        $total_batches = $metrics['batch_count'];
        $avg_mae = $metrics['mae_combined'];
        $avg_ssd = $metrics['ssd_combined'];
        $avg_balance = abs($metrics['balance']);

        // Quality score calculation (example - adjust based on your requirements)
        $quality_score = 100;

        // Penalize high MAE (worse accuracy)
        if ($avg_mae > 0.1) {
            $quality_score -= ($avg_mae - 0.1) * 100;
        }

        // Penalize high SSD (less consistency)
        if ($avg_ssd > 0.05) {
            $quality_score -= ($avg_ssd - 0.05) * 200;
        }

        // Penalize imbalance
        if ($avg_balance > 0.1) {
            $quality_score -= ($avg_balance - 0.1) * 150;
        }

        $quality_score = max(0, min(100, $quality_score));

        return array_merge($metrics, [
            'quality_score' => round($quality_score, 1),
            'avg_mae_rating' => $avg_mae <= 0.1 ? 'Good' : ($avg_mae <= 0.2 ? 'Fair' : 'Poor'),
            'consistency_rating' => $avg_ssd <= 0.05 ? 'Good' : ($avg_ssd <= 0.1 ? 'Fair' : 'Poor'),
            'balance_rating' => $avg_balance <= 0.1 ? 'Good' : ($avg_balance <= 0.2 ? 'Fair' : 'Poor'),
        ]);
    }

    /**
     * Get the machine's display name
     */
    public function getNameAttribute(): string
    {
        return "Line {$this->line}";
    }

    /**
     * Get the last measurement count from the latest batch
     */
    public function last_batch_size(): int
    {
        $latestMetric = $this->latest_metric();

        if ($latestMetric && $latestMetric->data) {
            return count($latestMetric->data);
        }

        return 0;
    }

    /**
     * Get current recipe being used
     */
    public function current_recipe(): ?InsCtcRecipe
    {
        $latestMetric = $this->latest_metric();

        if ($latestMetric && $latestMetric->ins_ctc_recipe_id) {
            return InsCtcRecipe::find($latestMetric->ins_ctc_recipe_id);
        }

        return null;
    }

    /**
     * Get machine status summary
     */
    public function status_summary(): array
    {
        $latestMetric = $this->latest_metric();
        $isOnline = $this->is_online();
        $currentRecipe = $this->current_recipe();

        return [
            'is_online' => $isOnline,
            'line' => $this->line,
            'ip_address' => $this->ip_address,
            'last_activity' => $latestMetric?->created_at,
            'last_batch_size' => $this->last_batch_size(),
            'current_recipe' => $currentRecipe?->name ?? 'Unknown',
            'current_recipe_id' => $currentRecipe?->id,
            'latest_avg_thickness' => $latestMetric?->t_avg,
            'latest_balance' => $latestMetric?->t_balance,
            'status' => $isOnline ? 'Online' : 'Offline',
        ];
    }

    /**
     * Scope for online machines
     */
    public function scopeOnline($query, int $minutes = 60)
    {
        return $query->whereHas('ins_ctc_metrics', function ($subQuery) use ($minutes) {
            $subQuery->where('created_at', '>=', Carbon::now()->subMinutes($minutes));
        });
    }

    /**
     * Scope for offline machines
     */
    public function scopeOffline($query, int $minutes = 60)
    {
        return $query->whereDoesntHave('ins_ctc_metrics', function ($subQuery) use ($minutes) {
            $subQuery->where('created_at', '>=', Carbon::now()->subMinutes($minutes));
        });
    }

    /**
     * Scope for machines by line number
     */
    public function scopeByLine($query, int $line)
    {
        return $query->where('line', $line);
    }
}
