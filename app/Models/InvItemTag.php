<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvItemTag extends Model
{
    use HasFactory;
    protected $fillable = [
        'inv_item_id',
        'inv_tag_id'
    ];

    public function inv_item()
    {
        return $this->belongsTo(InvItem::class);
    }
}
