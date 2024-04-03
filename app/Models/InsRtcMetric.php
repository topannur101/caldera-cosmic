<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsRtcMetric extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ins_rtc_recipe_id',
        'thick_act_left',
        'thick_act_right',
        'dt_client',
    ];
}
