<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

use Carbon\Carbon;
use League\Csv\Writer;
use App\Models\InsRtcMetric;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Response;

new #[Layout('layouts.app')] class extends Component {};

?>

<x-slot name="title">{{ __('Tayangan slide') . ' — ' . __('Rubber Thickness Control') }}</x-slot>
<x-slot name="header">
    <header class="bg-white dark:bg-neutral-800 shadow">
        <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div>
                <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                    <x-link href="{{ route('insight') }}" class="inline-block py-6" wire:navigate><i
                            class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span
                            class="hidden sm:inline">{{ __('Rubber Thickness Control') }}</span><span
                            class="sm:hidden inline">{{ __('RTC') }}</span>
                </h2>
            </div>
            <div class="space-x-8 -my-px ml-10 flex">
                <x-nav-link href="{{ route('insight.rtc.index') }}" wire:navigate>
                    <i class="fa mx-2 fa-fw fa-heart-pulse text-sm"></i>
                </x-nav-link>
                <x-nav-link href="{{ route('insight.rtc.slideshows') }}" wire:navigate active="true">
                    <i class="fa mx-2 fa-fw fa-tv text-sm"></i>
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
        <h1 class="text-2xl mb-6 text-neutral-900 dark:text-neutral-100 px-8">
            {{ __('Tayangan slide') }}</h1>
        <div class="grid grid-cols-1 gap-1 my-8 ">
            <x-card-link href="{{ route('insight.ss', ['id' => 3]) }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="fa fa-fw fa-chart-area"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Bagan garis waktu nyata') }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Tampilkan bagan garis pada satu line produksi') }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insight.ss', ['id' => 4]) }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="fa fa-fw fa-image"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Info model waktu nyata') }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Tampilkan info model pada satu line produksi') }}
                        </div>
                    </div>
                </div>
            </x-card-link>
        </div>
    </div>
</div>
