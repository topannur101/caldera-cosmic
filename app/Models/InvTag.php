<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    public function inv_items()
    {
        return $this->hasManyThrough(InvItem::class, InvItemTag::class, 'inv_tag_id', 'id', 'id', 'inv_item_id');
    }
}
