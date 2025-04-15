<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;


new #[Layout('layouts.app')]
class extends Component
{


};

?>

<x-slot name="title">{{ __('Operasi massal sirkulasi') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Operasi massal sirkulasi') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-700 dark:text-neutral-200">
   <div class="flex flex-col gap-y-6" >
      <h1 class="uppercase text-sm text-neutral-500 px-8">{{ __('Buat sirkulasi') }}</h1>
      <div class="bg-white dark:bg-neutral-800 shadow overflow-hidden sm:rounded-lg divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
         <a href="{{ route('inventory.circs.bulk-operation.circ-only') }}" class="block hover:bg-caldy-500 hover:bg-opacity-10">
            <div class="flex items-center">
               <div class="px-6 py-3">
                  <i class="fa fa-fw fa-arrow-right-arrow-left"></i>
               </div>
               <div class="py-3 grow">
                  <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Sirkulasi saja') }}</div>
                  <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                     <div class="flex items-center gap-x-2 text-sm text-neutral-500">
                        {{ __('Buat sirkulasi tanpa membuat barang baru') }}
                     </div>
                  </div>
               </div>
               <div class="px-6 py-3 text-lg">
                  <i class="fa fa-chevron-right"></i>
               </div>
            </div>
         </a>
         <a href="{{ route('inventory.circs.bulk-operation.with-item') }}" class="block hover:bg-caldy-500 hover:bg-opacity-10">
            <div class="flex items-center">
               <div class="px-6 py-3">
                  <i class="fa fa-fw fa-cube"></i>
               </div>
               <div class="py-3 grow">
                  <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Dengan barang baru') }}</div>
                  <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                     <div class="flex items-center gap-x-2 text-sm text-neutral-500">
                        {{ __('Barang akan dibuat jika tidak ditemukan') }}
                     </div>
                  </div>
               </div>
               <div class="px-6 py-3 text-lg">
                  <i class="fa fa-chevron-right"></i>
               </div>
            </div>
         </a>
      </div>
   </div>
</div>