<?php

namespace App\Models;

use App\Models\ComItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KpiScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kpi_item_id',
        'month',
        'target',
        'actual',
        'is_submitted'
    ];

    public function kpi_item(): BelongsTo
    {
        return $this->belongsTo(KpiItem::class);
    }

    public function com_items(): Collection
    {
        return ComItem::where('mod', class_basename($this))->where('mod_id', $this->id)->get();
    }

    public function com_items_count(): int
    {
        return $this->com_items()->count();
    }

    public function com_files(): Collection
    {
        return ComFile::join('com_items', 'com_items.id', '=', 'com_files.com_item_id')
        ->where('com_items.mod', class_basename($this))->where('com_items.mod_id', $this->id)
        ->select('com_files.*', 'com_items.id as com_item_id')
        ->get();
    }

    public function com_files_count(): int
    {
        return $this->com_files()->count();
    }
}
