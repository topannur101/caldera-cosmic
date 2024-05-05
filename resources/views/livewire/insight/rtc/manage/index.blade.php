<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {};

?>
<x-slot name="title">{{ __('Kelola') . ' — ' . __('Rubber Thickness Control') }}</x-slot>
<x-slot name="header">
    <header class="bg-white dark:bg-neutral-800 shadow">
        <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div>
                <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                    <x-link href="{{ route('insight') }}" class="inline-block py-6" wire:navigate><i
                            class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Rubber Thickness Control') }}</span><span class="sm:hidden inline">{{ __('RTC') }}</span>
                </h2>
            </div>
            <div class="space-x-8 -my-px ml-10 flex">
                <x-nav-link href="{{ route('insight.rtc.index') }}" wire:navigate>
                    <i class="fa mx-2 fa-fw fa-chart-simple text-sm"></i>
                </x-nav-link>
                <x-nav-link href="{{ route('insight.rtc.slideshows') }}" wire:navigate>
                    <i class="fa mx-2 fa-fw fa-images text-sm"></i>
                </x-nav-link>
                <x-nav-link href="{{ route('insight.rtc.manage.index') }}" active="true" wire:navigate>
                    <i class="fa mx-2 fa-fw fa-ellipsis-h text-sm"></i>
                </x-nav-link>
            </div>
        </div>
    </header>
</x-slot>
<div class="py-12">
    <div class="max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-600 dark:text-neutral-400">
        <div class="grid grid-cols-1 gap-1 my-8 ">
            <x-card-link href="{{ route('insight.rtc.manage.authorizations') }}" wire:navigate>
                <div class="flex">
                    <div>
                        <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="fa fa-user-lock"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Kelola wewenang') }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Kelola wewenang pengguna RTC') }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insight.rtc.manage.devices') }}" wire:navigate>
                <div class="flex">
                    <div>
                        <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="fa fa-microchip"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Kelola perangkat') }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Kelola perangkat yang tersedia di line produksi') }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insight.rtc.manage.recipes') }}" wire:navigate>
                <div class="flex">
                    <div>
                        <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="fa fa-flask"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Kelola resep') }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Kelola resep untuk di ekspor ke HMI') }}
                        </div>
                    </div>
                </div>
            </x-card-link>
        </div>
    </div>
</div>
