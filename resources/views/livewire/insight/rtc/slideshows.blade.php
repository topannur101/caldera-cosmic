<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

use Carbon\Carbon;
use League\Csv\Writer;
use App\Models\InsRtcMetric;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Response;

new #[Layout('layouts.app')] class extends Component {

};

?>

<x-slot name="title">{{ __('Peragaan') . ' — ' . __('Rubber thickness control') }}</x-slot>
<x-slot name="header">
    <header class="bg-white dark:bg-neutral-800 shadow">
        <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div>  
                <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                    <x-link href="{{ route('insight') }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Rubber thickness control') }}</span><span class="sm:hidden inline">{{ __('RTC') }}</span>
                </h2>
            </div>
            <div class="space-x-8 -my-px ml-10 flex">
                <x-nav-link href="{{ route('insight.rtc.index') }}" wire:navigate>
                    <i class="fa mx-2 fa-fw fa-chart-simple text-sm"></i>
                </x-nav-link>
                <x-nav-link href="{{ route('insight.rtc.slideshows') }}" wire:navigate active="true">
                    <i class="fa mx-2 fa-fw fa-images text-sm"></i>
                </x-nav-link>
                <x-nav-link href="{{ route('insight.rtc.manage.index') }}" :active="request()->routeIs('inventory/manage*')" wire:navigate>
                    <i class="fa mx-2 fa-fw fa-ellipsis-h text-sm"></i>
                </x-nav-link>
            </div>
        </div>
    </header>
</x-slot>
<div class="py-12">
   <div class="max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-600 dark:text-neutral-400">
       <div class="grid grid-cols-1 gap-1 my-8 ">
           <x-card-link href="{{ route('insight.ss', ['id' => 1]) }}" wire:navigate>
               <div class="grow truncate px-8 py-4">
                  <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                     {{ __('Peragaan') . ' #1' }}
                  </div>
                  <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                     {{ __('Tampilkan bagan garis berdasarkan line') }}
                  </div>
               </div>
           </x-card-link>
       </div>
   </div>
</div>

