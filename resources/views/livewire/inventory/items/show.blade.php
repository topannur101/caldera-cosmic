<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use App\Models\InvItem;

new #[Layout('layouts.app')]
class extends Component
{
    #[Url]
    public int $id = 0;

    public array $items = [
        0 => [
           'id'              => '',
           'name'            => '',
           'desc'            => '',
           'code'            => '',
           'loc_id'          => 0,
           'loc_name'        => '',
           'tags_list'       => '',
           'photo'           => '',
           'area_id'         => 0,
           'area_name'       => '',
           'is_active'       => false,
           'updated_at'      => '',
           'last_deposit'    => '',
           'last_withdrawal' => '',
        ]
     ];

     #[Url]
     public bool $is_updated = false;

     public bool $can_store = false;
     public bool $can_view = false;

    public function mount()
    {
        $item = InvItem::findOrFail($this->id);
        if ($item) {
            $this->items[0]['id'] = $item->id;
            $this->items[0]['name'] = $item->name;
            $this->items[0]['desc'] = $item->desc;
            $this->items[0]['code'] = $item->code;
            $this->items[0]['loc_name'] = $item->inv_loc_id ? $item->inv_loc->parent . '-' . $item->inv_loc->bin : '';
            $this->items[0]['tags_list'] = $item->inv_tags->pluck('name')->implode(', ');
            $this->items[0]['photo'] = $item->photo;
            $this->items[0]['area_id'] = $item->inv_area_id;
            $this->items[0]['area_name'] = $item->inv_area->name;
            $this->items[0]['is_active'] = $item->is_active;
            $this->items[0]['updated_at'] = $item->updated_at->diffForHumans();
            $this->items[0]['last_withdrawal'] = $item->last_withdrawal;
            $this->items[0]['last_deposit'] = $item->last_deposit;
            $this->items[0]['comments_count'] = $item->comments->count();

            $store = Gate::inspect('store', $item);
            $this->can_store = $store->allowed();
            $view = Gate::inspect('view', $item);
            $this->can_view = $view->allowed();
        }
    }

};

?>

<x-slot name="title">{{ $items[0]['name'] . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Rincian barang') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-5xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200" x-init="$wire.is_updated ? toast('{{ __('Barang disimpan') }}', { type: 'success' }) : null">
    @if($can_view)
        <livewire:inventory.items.form :$items :$can_store />
        <hr class="border-neutral-200 dark:border-neutral-800 my-8" />
        <div id="comments" class="max-w-lg mx-auto px-6 md:px-0">
            <livewire:comments.index model_name="InvItem" :model_id="$items[0]['id']" />
        </div>
    @else    
        <div class="text-center w-72 py-20 mx-auto">
            <i class="fa fa-hand text-5xl mb-8 text-neutral-400 dark:text-neutral-600"></i>
            <div class="text-neutral-500">{{ __('Kamu tidak memiliki wewenang untuk melihat barang di area ini.') }}</div>
        </div>
    @endif
</div>
