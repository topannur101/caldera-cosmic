<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'inv_item_id',
        'inv_curr_id',
        'qty',
        'uom',
        'unit_price',
        'is_active',
        'inv_item_id',
        'inv_curr_id'
    ];

    public function inv_item()
    {
        return $this->belongsTo(InvItem::class);
    }

    public function inv_curr()
    {
        return $this->belongsTo(InvCurr::class);
    }
}
