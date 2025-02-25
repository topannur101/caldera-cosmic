<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    public bool $is_editing = true;

    #[Url]
    public string $code = '';
 
    #[Url]
    public int $area_id = 0;

    public array $items = [
        0 => [
           'id'              => 0,
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
           'last_withdrawal' => '',
        ]
     ];

     public function mount()
     {
        $this->items[0]['code']     = $this->code;
        $this->items[0]['area_id']  = $this->area_id;
     }

};

?>

<x-slot name="title">{{ __('Barang baru') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Barang baru') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-4xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <livewire:inventory.items.form :$is_editing :$items />
</div>
