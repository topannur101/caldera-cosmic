<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    #[Url]
    public int $id = 0;


};

?>

<x-slot name="title">{{ __('Barang') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Barang') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-5xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <livewire:inventory.items.form :$id />
    <hr class="border-neutral-200 dark:border-neutral-800 my-8" />
    <div class="max-w-lg mx-auto px-6 md:px-0">
        <livewire:comments.index />
    </div>
</div>
