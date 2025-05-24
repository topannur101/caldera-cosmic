<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public $pua;

}; ?>

   <div class="py-12">
       <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
           <div class="flex flex-col items-center text-neutral-600 dark:text-neutral-400">
               <div class="w-24 h-24 mb-4 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                   @if(Auth::user()->photo)
                       <img class="w-full h-full object-cover dark:brightness-80" src="/storage/users/{{ Auth::user()->photo }}" />
                   @else
                       <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                   @endif
               </div>
               <div class="text-xl">{{ Auth::user()->name }}</div>
               <div class="text-sm">{{ Auth::user()->emp_id }}</div>
           </div>
           <div class="grid grid-cols-1 gap-1 my-8">
               <x-card-link wire:navigate href="{{ route('account.edit')}}">
                   <div class="flex px-8">
                       <div>
                           <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                               <div class="m-auto"><i class="icon-circle-user"></i></div>
                           </div>
                       </div>
                       <div class="grow truncate py-4">
                           <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                               {{__('Info akun')}}
                           </div>                        
                           <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                               {{__('Ubah nama atau foto akun')}}
                           </div>
                       </div>
                   </div>
               </x-card-link>
               <x-card-button type="button" x-data=""
               x-on:click.prevent="$dispatch('open-modal', 'change-password')">
                   <div class="flex px-8">
                       <div>
                           <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                               <div class="m-auto"><i class="icon-key"></i></div>
                           </div>
                       </div>
                       <div class="grow text-left truncate py-4">
                           <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                               {{__('Kata sandi')}}
                           </div>                        
                           <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                               {{ $pua ? __('Terakhir diperbarui:') .' '. Carbon\Carbon::parse($pua)->diffForHumans() : __('Ubah kata sandi') }}
                           </div>
                       </div>
                   </div>
               </x-card-button>
               <x-modal name="change-password" maxWidth="sm" focusable>
                   <div class="p-6">
                       <livewire:account.update-password />
                   </div>
               </x-modal>
               <x-card-button type="button" x-data=""
               x-on:click.prevent="$dispatch('open-modal', 'change-language')">
                   <div class="flex px-8">
                       <div>
                           <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                               <div class="m-auto"><i class="icon-languages"></i></div>
                           </div>
                       </div>
                       <div class="grow text-left truncate py-4">
                           <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                               {{__('Bahasa')}}
                           </div>                        
                           <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                               {{__('Ubah bahasa tampilan')}}
                           </div>
                       </div>
                   </div>
               </x-card-button>
               <x-modal name="change-language" maxWidth="sm">
                <div class="p-6">
                    <livewire:account.update-lang />
                </div>
               </x-modal>
               <x-card-link wire:navigate href="{{ route('account.theme') }}">
                   <div class="flex px-8">
                       <div>
                           <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                               <div class="m-auto"><i class="icon-swatchbook"></i></div>
                           </div>
                       </div>
                       <div class="grow truncate py-4">
                           <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                               {{__('Tema')}}
                           </div>                        
                           <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                               {{__('Ubah latar dan warna aksen')}}
                           </div>
                       </div>
                   </div>
               </x-card-link>
           </div>
       </div>
   </div>   