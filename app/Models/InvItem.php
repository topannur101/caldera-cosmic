<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'last_withdrawal',
        'last_deposit',
        'name',
        'desc',
        'code',
        'inv_area_id',
        'photo',
        'is_active',
        'inv_loc_id',
        'inv_area_id',
        'legacy_id'
    ];

    public function inv_loc()
    {
        return $this->belongsTo(InvLoc::class);
    }

    public function inv_area()
    {
        return $this->belongsTo(InvArea::class);
    }

    public function inv_tags()
    {
        return $this->hasManyThrough(InvTag::class, InvItemTag::class, 'inv_item_id', 'id', 'id', 'inv_tag_id');
    }

    public function tags_facade(): string
    {
        $tags = $this->inv_tags;
    
        if ($tags->isEmpty()) {
            return ''; // or return '';
        }
    
        $tagNames = $tags->pluck('name');
    
        if ($tagNames->count() <= 3) {
            return $tagNames->implode(', ');
        }
    
        $firstThreeTags = $tagNames->take(3)->implode(', ');
        $additionalCount = $tagNames->count() - 3;
    
        return $firstThreeTags . ' +' . $additionalCount;
    }

    public function inv_stocks()
    {
        return $this->hasMany(InvStock::class)->where('is_active', true);
    }

}
