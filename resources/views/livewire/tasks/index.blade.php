<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component {};

?>

<x-slot name="title">{{ __('Tugas') }}</x-slot>

@if (Auth::user()->id ?? false)
   <x-slot name="header">
      <x-nav-task></x-nav-task>
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
                                 <span class="block xl:inline">{{ __('Kelola tugas dengan mudah') }}</span>
                        </h1>
                        <p class="mx-auto text-base text-neutral-500 sm:max-w-md lg:text-xl md:max-w-3xl">
                           {{ __('Satu tempat untuk segala keperluan manajemen tugas, mulai dari pembuatan proyek, penugasan, hingga pelacakan progres tim.') }}
                        </p>
                     </div>
                     <div class="relative flex flex-col sm:flex-row sm:space-x-4">
                           @guest
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
                           @else
                           <a href="{{ route('tasks.dashboard.index') }}" wire:navigate
                                 class="flex items-center w-full px-6 py-3 my-6 text-lg text-white bg-caldy-600 rounded-md sm:mb-0 hover:bg-caldy-700 sm:w-auto">
                                 {{ __('Dasbor') }}
                                 <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-1" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <line x1="5" y1="12" x2="19" y2="12">
                                    </line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                 </svg>
                           </a>
                           @endguest
                           <a href="{{ route('tasks.projects.index') }}" wire:navigate
                                 class="flex items-center w-full px-6 py-3 my-6 text-lg bg-neutral-100 rounded-md sm:mb-0 hover:bg-neutral-200 sm:w-auto">
                                 {{ __('Lihat Proyek') }}
                                 <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-1" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <line x1="5" y1="12" x2="19" y2="12">
                                    </line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                 </svg>
                           </a>
                     </div>
               </div>
               <div class="w-full mt-12 md:w-1/2 md:px-3 md:mt-0">
                     <div class="w-full h-auto overflow-hidden rounded-md shadow-xl sm:rounded-xl">
                           <img src="/projects.jpg" class="dark:invert" alt="Task management illustration" />
                     </div>
               </div>
            </div>
         </div>
   </section>

   @auth
   <section class="bg-white dark:bg-neutral-800">
         <div class="max-w-6xl mx-auto px-8 py-16">
            <div class="text-center">
                  <h2 class="text-3xl font-bold text-neutral-900 dark:text-neutral-100 mb-4">
                     {{ __('Fitur Utama') }}
                  </h2>
                  <p class="text-neutral-600 dark:text-neutral-400 mb-12">
                     {{ __('Semua yang Anda butuhkan untuk mengelola tugas dan proyek tim') }}
                  </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                  <div class="text-center">
                     <div class="bg-caldy-100 dark:bg-caldy-900/20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                           <i class="icon-drafting-compass text-2xl text-caldy-600 dark:text-caldy-400"></i>
                     </div>
                     <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
                           {{ __('Manajemen Proyek') }}
                     </h3>
                     <p class="text-neutral-600 dark:text-neutral-400">
                           {{ __('Organisir tugas dalam proyek yang terstruktur dengan tim yang tepat') }}
                     </p>
                  </div>
                  
                  <div class="text-center">
                     <div class="bg-caldy-100 dark:bg-caldy-900/20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                           <i class="icon-ticket text-2xl text-caldy-600 dark:text-caldy-400"></i>
                     </div>
                     <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
                           {{ __('Pelacakan Tugas') }}
                     </h3>
                     <p class="text-neutral-600 dark:text-neutral-400">
                           {{ __('Pantau progres tugas dari pembuatan hingga penyelesaian') }}
                     </p>
                  </div>
                  
                  <div class="text-center">
                     <div class="bg-caldy-100 dark:bg-caldy-900/20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                           <i class="icon-users text-2xl text-caldy-600 dark:text-caldy-400"></i>
                     </div>
                     <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
                           {{ __('Kolaborasi Tim') }}
                     </h3>
                     <p class="text-neutral-600 dark:text-neutral-400">
                           {{ __('Bekerja sama dengan tim melalui komentar dan berbagi file') }}
                     </p>
                  </div>
            </div>
         </div>
   </section>
   @endauth
</div>