<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component {};

?>



<x-slot name="title">{{ __('Inventaris') }}</x-slot>

@if (Auth::user()->id ?? false)
   <x-slot name="header">
      <x-nav-inventory></x-nav-inventory>
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
                           class="text-4xl font-extrabold tracking-tight text-neutral-900 dark:text-neutral-300 sm:text-5xl md:text-4xl lg:text-5xl xl:text-6xl">
                                 <span class="block xl:inline">{{ __('Kelola barang dengan mudah') }}</span>
                        </h1>
                        <p class="mx-auto text-base text-neutral-500 sm:max-w-md lg:text-xl md:max-w-3xl">
                           {{ __('Satu tempat untuk segala keperluan inventaris, mulai dari manajemen barang, pencatatan keluar masuk, hingga kebutuhan analisis.') }}
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
                              <x-pill color="neutral-lighter">CE</x-pill>
                              <x-pill color="neutral-lighter">MAINTENANCE</x-pill>
                              {{-- <x-link href="#">Terapkan di departemenmu</x-link> --}}
                           </div>
                        </div>
                     @endif
               </div>
               <div class="w-full md:w-1/2">
                  <div class="w-full h-auto overflow-hidden rounded-md shadow-xl sm:rounded-xl">
                     <img src="/inventory.jpg" class="dark:invert">
                  </div>
               </div>
            </div>
         </div>
   </section>

   @auth
      <!-- Modal controlled with Alpine.js -->
      <div 
      x-data="{ open: false }" 
      x-show="open" 
      x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0"
      x-init="setTimeout(() => { open = true }, 300)" 
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-10"
      >
         <!-- Modal Container -->
         <div 
            x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform -translate-y-4"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-4"
            @click.away="open = false"
            class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl w-11/12 max-w-3xl overflow-hidden"
         >
            <!-- Modal Header with gradient background using user's accent color -->
            <div class="bg-gradient-to-r from-caldy-900 to-caldy-500 p-6 text-white">
               <div class="flex items-center space-x-2">
               <h2 class="text-3xl font-bold">{{ __('Selamat datang di Inventaris 2.0') }}</h2>
               </div>
            </div>

            <!-- Modal Body -->
            <div class="p-8 dark:text-white">
               <div class="space-y-6">
                  <!-- Beta Testing Message -->
                  <div class="flex items-start space-x-4">
                     <i class="fas fa-flask-vial text-caldy-500 text-2xl mt-1"></i>
                     <div>
                        <h3 class="text-xl font-semibold mb-2">{{ __('Periode Beta Testing') }}</h3>
                        <p class="text-neutral-600 dark:text-neutral-300">
                           {{ __('Saat ini Inventaris 2.0 sedang dalam tahap beta testing. Masukan kamu akan sangat berharga untuk penyempurnaan sistem.') }}
                        </p>
                     </div>
                  </div>

                  <!-- Features Highlight -->
                  <div class="flex items-start space-x-4">
                     <i class="fas fa-star text-caldy-500 text-2xl mt-1"></i>
                     <div>
                        <h3 class="text-xl font-semibold mb-2">{{ __('Fitur Terbaru') }}</h3>
                        <p class="text-neutral-600 dark:text-neutral-300">
                           {{ __('Nikmati antarmuka yang lebih intuitif dan performa yang lebih cepat. Pencatatan inventaris kini lebih efisien dan mudah digunakan.') }}
                        </p>
                     </div>
                  </div>

                  <!-- Feedback Request -->
                  <div class="flex items-start space-x-4">
                     <i class="fas fa-comment-dots text-caldy-500 text-2xl mt-1"></i>
                     <div>
                        <h3 class="text-xl font-semibold mb-2">{{ __('Berikan Masukan') }}</h3>
                        <p class="text-neutral-600 dark:text-neutral-300">
                           {{ __('Ada masalah atau ide untuk penyempurnaan sistem? Sampaikan langsung atau lewat email kepada Bintang Rizky Lazuardy, perwakilan teknis kami.') }}
                        </p>
                     </div>
                  </div>
               </div>

               <!-- Modal Footer -->
               <div class="mt-10 flex justify-center">
                  <button 
                     @click="open = false" 
                     class="bg-caldy-500 hover:bg-caldy-600 text-white font-bold py-3 px-10 rounded-full transition duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-caldy-400 focus:ring-opacity-50"
                  >
                     {{ __('Paham') }}
                  </button>
               </div>
            </div>
         </div>
      </div>
   @endauth
</div>