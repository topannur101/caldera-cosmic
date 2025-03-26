<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {
};

?>

<x-slot name="title">{{ __('Inventaris') }}</x-slot>

@if (Auth::user()->id ?? false)
   <x-slot name="header">
      <x-nav-projects></x-nav-projects>
   </x-slot>
@endif

<div class="relative">
   
      <section class="px-2 pt-16 md:px-0">
            <div class="container items-center max-w-6xl py-16 px-8 mx-auto xl:px-5">
               <div class="flex flex-wrap sm:-mx-3">
                  <div class="w-full md:w-1/2 md:px-3">
                        <div
                           class="w-full pb-6 space-y-6 sm:max-w-md lg:max-w-lg md:space-y-4 lg:space-y-8 xl:space-y-9 sm:pr-5 lg:pr-0 md:pb-0">
                           <h1
                              class="text-4xl font-bold tracking-tight text-neutral-900 dark:text-neutral-300 sm:text-5xl md:text-4xl lg:text-5xl xl:text-6xl">
                                    <span class="block xl:inline">{{ __('Kelola proyek dengan terorganisir') }}</span>
                           </h1>
                           <p class="mx-auto text-base text-neutral-500 sm:max-w-md lg:text-xl md:max-w-3xl">
                              {{ __('Satu platform untuk mengatur jadwal, mengawasi tugas, dan memastikan setiap proyek selesai tepat waktu.') }}
                           </p>
                        </div>
                        @if (Auth::user()->id ?? false)

                        @else
                           <div class="relative flex flex-col sm:flex-row sm:space-x-4">
                              <a href="{{ route('login', ['redirect' => url()->current()]) }}" wire:navigate
                                    class="flex items-center w-full px-6 py-3 my-6 text-lg text-white bg-caldy-600 rounded-md sm:mb-0 hover:bg-caldy-700 sm:w-auto">
                                    {{ __('Masuk') }}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-1" viewBox="0 0 24 24"
                                       fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                       stroke-linejoin="round">
                                       <line x1="5" y1="12" x2="19" y2="12">
                                       </line>
                                       <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                              </a>
                           </div>
                           <div class="pr-0 md:pr-8"><hr class="border-neutral-300 dark:border-neutral-800 my-10" /></div>
                           <div class="text-neutral-500 text-sm mb-10">
                              <div class="flex flex-wrap gap-2 items-center">
                                 <div class="mb-1">{{ __('Telah digunakan di departemen:') }}</div>
                                 <x-pill color="neutral-lighter">MM</x-pill>
                              </div>
                           </div>
                        @endif
                  </div>
                  <div class="w-full md:w-1/2">
                     <div class="w-full h-auto overflow-hidden rounded-md shadow-xl sm:rounded-xl">
                        <img src="/projects.jpg" class="dark:invert">
                     </div>
                  </div>
               </div>
            </div>
      </section>
</div>