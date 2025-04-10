<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{ 

};

?>

<x-slot name="title">{{ __('Panduan') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Panduan') }}</x-nav-inventory-sub>
</x-slot>

<div 
   x-data="{ 
      activeAccordion: '', 
      setActiveAccordion(id) { 
            this.activeAccordion = (this.activeAccordion == id) ? '' : id 
      } 
   }"   
   class="py-12 max-w-2xl mx-auto sm:px-3 flex flex-col gap-y-4 text-neutral-800 dark:text-neutral-200">
    <div class="p-4 bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
      <h1 class="px-5 py-3 font-semibold text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Mencari barang') }}</h1>
      <div  class="relative w-full">

         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
                  <span>{{ __('Dengan kata kunci') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
               <video controls>
                    <source src="{{ asset('inv-help-videos/001.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Dengan lokasi') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/002.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Dengan tag') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/003.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Dengan filter lain') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/004.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Dengan banyak filter') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/005.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Penyortiran') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/006.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Di area tertentu') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/007.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
      </div>

    </div>
    <div class="p-4 bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
      <h1 class="px-5 py-3 font-semibold text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Membuat barang') }}</h1>
      <div  class="relative w-full">

         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
                  <span>{{ __('Secara tunggal') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/008.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Secara massal') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/009.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>      
      </div>

    </div>
    <div class="p-4 bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
      <h1 class="px-5 py-3 font-semibold text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Membuat sirkulasi') }}</h1>
      <div  class="relative w-full">

         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
                  <span>{{ __('Pengambilan barang') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/010.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Penambahan barang') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/011.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Pencatatan barang') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/012.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Secara massal') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/013.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>         
      </div>

    </div>
    <div class="p-4 bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
      <h1 class="px-5 py-3 font-semibold text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Menggunakan halaman sirkulasi') }}</h1>
      <div  class="relative w-full">

         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
                  <span>{{ __('Dengan filter') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/014.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Dengan penyortiran') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/015.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Dengan fitur cetak') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/016.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
      </div>

    </div>
    <div class="p-4 bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
      <h1 class="px-5 py-3 font-semibold text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Mengevaluasi (setujui/tolak) sirkulasi') }}</h1>
      <div  class="relative w-full">

         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
                  <span>{{ __('Pada halaman barang') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/017.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('Pada halaman sirkulasi') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/018.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
      </div>

    </div>
    <div class="p-4 bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
      <h1 class="px-5 py-3 font-semibold text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Mengunduh data') }}</h1>
      <div  class="relative w-full">

         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
                  <span>{{ __('CSV daftar sirkulasi') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/019.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('CSV daftar sirkulasi dari satu barang') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/020.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('CSV daftar barang') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/021.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
         <div x-data="{ id: $id('accordion') }" :class="{ 'border-caldy-500' : activeAccordion==id, 'border-transparent' : activeAccordion!=id }" class="duration-200 ease-out border rounded-md cursor-pointer group overflow-hidden" x-cloak>
            <button @click="setActiveAccordion(id)" class="flex items-center justify-between w-full px-5 py-4 text-left select-none">
            <span>{{ __('CSV untuk keperluan backup/cadangan') }}</span>
                  <div :class="{ 'rotate-90': activeAccordion==id }" class="relative flex items-center justify-center w-2.5 h-2.5 duration-300 ease-out">
                     <div class="absolute w-0.5 h-full bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                     <div :class="{ 'rotate-90': activeAccordion==id }" class="absolute w-full h-0.5 ease duration-500 bg-neutral-500 group-hover:bg-neutral-800 dark:group-hover:bg-neutral-300 rounded-full"></div>
                  </div>
            </button>
            <div x-show="activeAccordion==id" x-collapse x-cloak>
            <video controls>
                    <source src="{{ asset('inv-help-videos/022.mp4') }}" type="video/mp4">
                    {{ __('Perambanmu tidak mendukung pemutaran video') }}
               </video>
            </div>
         </div>
      </div>

    </div>
</div>

