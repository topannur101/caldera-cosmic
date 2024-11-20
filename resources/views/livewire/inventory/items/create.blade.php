<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {

};

?>

<x-slot name="title">{{ __('Barang baru') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory></x-nav-inventory>
</x-slot>

<div id="content" class="py-12 max-w-4xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <livewire:inventory.items.form />
</div>