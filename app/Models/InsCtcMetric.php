<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InsCtcMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'ins_ctc_machine_id',
        'ins_rubber_batch_id',
        'ins_ctc_recipe_id',
        'is_auto',
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
        'data'
    ];

    protected $casts = [
        'data' => 'array',  // This is crucial for JSON handling
        'is_auto' => 'boolean',
        't_mae_left' => 'decimal:2',
        't_mae_right' => 'decimal:2',
        't_mae' => 'decimal:2',
        't_ssd_left' => 'decimal:2',
        't_ssd_right' => 'decimal:2',
        't_ssd' => 'decimal:2',
        't_avg_left' => 'decimal:2',
        't_avg_right' => 'decimal:2',
        't_avg' => 'decimal:2',
        't_balance' => 'decimal:2'
    ];
}
