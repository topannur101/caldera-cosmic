<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsRdcStd extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine',
        'mcs',
        'ins_rdc_tag_id',
        'tc10',
        'tc90',
    ];

    public function ins_rdc_tag(): BelongsTo
    {
        return $this->belongsTo(InsRdcTag::class);
    }
}
