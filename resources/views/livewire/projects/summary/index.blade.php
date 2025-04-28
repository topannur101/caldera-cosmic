<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component
{


};

?>

<x-slot name="title">{{ __('Ringkasan') . ' — ' . __('Proyek') }}</x-slot>

<x-slot name="header">
    <x-nav-projects></x-nav-projects>
</x-slot>

<div id="content" class="py-6 max-w-8xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <div wire:key="modals">
   </div>    
   <div class="p-4 bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
      Content goes here
   </div>
</div>
