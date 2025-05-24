<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component
{


};

?>

<x-slot name="title">{{ __('Tugas') . ' — ' . __('Proyek') }}</x-slot>

<x-slot name="header">
    <x-nav-projects></x-nav-projects>
</x-slot>

<div id="content" class="py-12 max-w-8xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <div wire:key="modals">
   </div>  

   <div class="flex justify-between px-8">
      <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Tugas') }}</h1>
      <div class="flex items-center gap-x-3">
         <x-primary-button type="button"><i class="icon-plus mr-2"></i>{{ __('Tugas') }}</x-primary-button>
         <x-dropdown align="right" width="48">
            <x-slot name="trigger">
               <x-text-button type="button"><i class="icon-ellipsis-vertical"></i></x-text-button>
            </x-slot>
            <x-slot name="content">
               <x-dropdown-link href="#" wire:click.prevent="setToday">
                  <i class="icon-plus mr-2"></i>{{ __('Buat proyek') }}
               </x-dropdown-link>
               <!-- <hr class="border-neutral-300 dark:border-neutral-600" /> -->
            </x-slot>
         </x-dropdown>
      </div>
   </div>
   
   <div class="p-4 my-8 bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
      Content goes here
   </div>
</div>
