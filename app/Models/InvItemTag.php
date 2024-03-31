<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvItemTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'inv_item_id',
        'inv_tag_id',
    ];

    public function inv_tag() : BelongsTo
    {
        return $this->belongsTo(InvTag::class);
    }
}
