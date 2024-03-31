<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KpiItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'kpi_area_id',
        'name',
        'year',
        'unit',
        'group',
        'order'
    ];

    public function kpi_area(): BelongsTo
    {
        return $this->belongsTo(KpiArea::class);
    }

    public function kpi_score($month): KpiScore
    {
        return KpiScore::firstorCreate([
            'kpi_item_id'   => $this->id,
            'month'         => $month
        ],
        [
            'user_id'       => 1
        ]);

    }
}
