<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvCirc extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'eval_status',
        'eval_user_id',
        'eval_remarks',
        'inv_stock_id',
        'qty_relative',
        'amout',
        'unit_price',
        'remarks'
    ];
}
