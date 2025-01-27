<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    #[Url]
    public string $view;
}

?>

<x-slot name="title">{{ __('Kelola') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-admin></x-nav-admin>
</x-slot>

<div id="content" class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <h1 class="text-2xl text-neutral-900 dark:text-neutral-100 px-8">{{ __('Inventaris') }}</h1>  
    <div class="grid grid-cols-1 gap-1 my-8">
      <x-card-link href="{{ route('admin.inventory.auths') }}" wire:navigate>
          <div class="flex">
              <div>
                  <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                      <div class="m-auto"><i class="fa fa-user-lock"></i></div>
                  </div>
              </div>
              <div class="grow truncate py-4">
                  <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                      {{__('Kelola wewenang')}}
                  </div>                        
                  <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                      {{__('Kelola wewenang pengguna inventaris')}}
                  </div>
              </div>
          </div>
      </x-card-link>
      <x-card-link href="{{ route('admin.inventory.areas') }}" wire:navigate>
          <div class="flex">
              <div>
                  <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                      <div class="m-auto"><i class="fa fa-building"></i></div>
                  </div>
              </div>
              <div class="grow truncate py-4">
                  <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                      {{__('Kelola area')}}
                  </div>                        
                  <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                      {{__('Kelola area inventaris')}}
                  </div>
              </div>
          </div>
      </x-card-link>
    </div>
</div>
