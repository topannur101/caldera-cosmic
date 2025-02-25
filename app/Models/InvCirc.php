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
        'amount',
        'unit_price',
        'remarks',
        'is_delegated'
    ];

    public function type_color(): string
    {
        $color = '';
        switch ($this->type) {
            case 'deposit':
                $color = 'text-green-500';
                break;
            case 'capture':
                $color = 'text-yellow-600';
                break;
            case 'withdrawal':
                $color = 'text-red-500';
                break;
        }
        return $color;
    }

    public function type_icon(): string
    {
        $icon = '';
        switch ($this->type) {
            case 'deposit':
                $icon = 'fa-plus';
                break;
            case 'capture':
                $icon = 'fa-code-commit';
                break;
            case 'withdrawal':
                $icon = 'fa-minus';
                break;
        }
        return $icon;
    }

    public function inv_stock()
    {
        return $this->belongsTo(InvStock::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function eval_user()
    {
        return $this->belongsTo(User::class);
    }

    public function eval_icon(): string
    {
        $icon = '';
        switch ($this->eval_status) {
            case 'pending':
                $icon = 'fa-hourglass';
                break;
            case 'approved':
                $icon = 'fa-thumbs-up';
                break;
            case 'rejected':
                $icon = 'fa-thumbs-down';
                break;
        }
        return $icon;
    }
}
