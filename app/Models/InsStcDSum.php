<?php

namespace App\Models;

use App\InsStc;
use App\InsStcTempControl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InsStcDSum extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_stc_device_id',
        'ins_stc_machine_id',
        'user_1_id',
        'user_2_id',
        'start_time',
        'end_time',
        'preheat_temp',
        'z_1_temp',
        'z_2_temp',
        'z_3_temp',
        'z_4_temp',
        'postheat_temp',
        'speed',
        'sequence',
        'position',
        'set_temps',
    ];
    
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'preheat_temp' => 'float',
        'z_1_temp' => 'float',
        'z_2_temp' => 'float',
        'z_3_temp' => 'float',
        'z_4_temp' => 'float',
        'postheat_temp' => 'float',
        'speed' => 'float',
    ];

    public static function logsCountEvalHuman($logsCountEval): string
    {
        switch ($logsCountEval) {
            case 'optimal':
                return __('Optimal');
                break;
            
            case 'too_many':
                return __('Terlalu banyak');
                break;
            case 'too_few':
                return __('Terlalu sedikit');
                break;
            default:
                return __('Tak diketahui');
        }
    }

    public function duration(): string
    {
        return InsStc::duration($this->start_time, $this->end_time);
    }

    public function uploadLatency(): string
    {
        return InsStc::duration($this->end_time, $this->updated_at);
    }

    public function ins_stc_d_logs(): HasMany
    {
        return $this->hasMany(InsStcDlog::class);
    }

    public function user_1(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function user_2(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ins_stc_machine(): BelongsTo
    {
        return $this->belongsTo(InsStcMachine::class);
    }

    public function ins_stc_device(): BelongsTo
    {
        return $this->belongsTo(InsStcDevice::class);
    }

    public function logTemps(): array
    {
        $dlogs = $this->ins_stc_d_logs->sortBy('taken_at');
        
        // Skip preheat (first 5)
        // Each section has 6 logs, starting from index 5
        $medians = [];
        
        for ($section = 0; $section < 8; $section++) {
            $startIndex = 5 + ($section * 6);
            $sectionLogs = $dlogs->slice($startIndex, 6);
            
            // If section has no logs, return 0
            if ($sectionLogs->isEmpty()) {
                $medians[] = 0;
                continue;
            }
            
            // Get valid temperatures
            $temps = $sectionLogs->pluck('temp')
                ->filter()  // Remove null/empty values
                ->map(function($temp) {
                    return floatval($temp);
                })
                ->values()  // Re-index array
                ->all();
                
            // Calculate median
            if (empty($temps)) {
                $medians[] = 0;
            } else {
                sort($temps);
                $count = count($temps);
                $middle = floor(($count - 1) / 2);
                
                if ($count % 2) {
                    // Odd number of temperatures
                    $medians[] = number_format($temps[$middle], 0);
                } else {
                    // Even number of temperatures
                    $medians[] = number_format((($temps[$middle] + $temps[$middle + 1]) / 2), 0);
                }
            }
        }
        return $medians;
    }

    public function corTemps(): array
    {
        $set_temps = json_decode($this->set_temps, true);
        $x = new InsStcTempControl;
        return $x->calculateNewSetValues($set_temps, $this->logTemps());
    }

}
