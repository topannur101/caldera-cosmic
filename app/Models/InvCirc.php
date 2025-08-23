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
        'is_delegated',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i',
        'updated_at' => 'datetime:Y-m-d H:i',
    ];

    public function getCreatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->setTimezone('Asia/Jakarta');
    }

    public function getUpdatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->setTimezone('Asia/Jakarta');
    }

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

    public function type_friendly(): string
    {
        $text = '';
        switch ($this->type) {
            case 'deposit':
                $text = __('Tambah');
                break;
            case 'capture':
                $text = __('Catat');
                break;
            case 'withdrawal':
                $text = __('Ambil');
                break;
        }

        return $text;
    }

    public function type_icon(): string
    {
        $icon = '';
        switch ($this->type) {
            case 'deposit':
                $icon = 'icon-plus';
                break;
            case 'capture':
                $icon = 'icon-git-commit-horizontal';
                break;
            case 'withdrawal':
                $icon = 'icon-minus';
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
                $icon = 'icon-hourglass text-neutral-500 opacity-50';
                break;
            case 'approved':
                $icon = 'icon-thumbs-up text-green-500 opacity-50';
                break;
            case 'rejected':
                $icon = 'icon-thumbs-down text-red-500 opacity-50';
                break;
        }

        return $icon;
    }

    public function eval_friendly(): string
    {
        $eval = '';
        switch ($this->eval_status) {
            case 'pending':
                $eval = __('Tertunda');
                break;
            case 'approved':
                $eval = __('Disetujui');
                break;
            case 'rejected':
                $eval = __('Ditolak');
                break;
        }

        return $eval;
    }

    public function inv_curr()
    {
        return $this->hasOneThrough(
            InvCurr::class,
            InvStock::class,
            'id',
            'id',
            'inv_stock_id',
            'inv_curr_id'
        );
    }

    public function inv_item()
    {
        return $this->hasOneThrough(
            InvItem::class,
            InvStock::class,
            'id',
            'id',
            'inv_stock_id',
            'inv_item_id'
        );
    }
}
