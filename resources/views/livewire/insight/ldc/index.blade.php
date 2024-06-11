<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {};

?>

<x-slot name="title">{{ __('Pendataan Kulit') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-ldc></x-nav-insights-ldc>
</x-slot>

<div id="content" class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @if (Auth::user())
        <div class="flex flex-col gap-x-4 md:gap-x-4 sm:flex-row">
            <div class="py-2">
                <livewire:insight.ldc.index-hides />
            </div>
            <div class="w-full overflow-hidden">
                <div class="overflow-x-auto mb-4 px-1 pt-1 pb-2">
                    <livewire:insight.ldc.index-groups />
                </div>
                <div class="flex w-full p-1">
                    <livewire:insight.ldc.index-form />
                </div>
            </div>
        </div>
    @else
    <div wire:key="no-match" class="py-20 text-neutral-400 dark:text-neutral-700">
        <div class="text-center text-5xl mb-8">
            <i class="fa fa-arrow-right-to-bracket"></i>
        </div>
        <div class="text-center mb-8">
            {{ __('Kamu harus masuk untuk mencatat data kulit') }}
        </div>
        <div class="flex justify-center">
            <x-link-secondary-button wire:navigate href="{{ route('insight.ldc.hides') }}">{{ __('Lihat data saja') }}</x-link-secondary-button>
        </div>
    </div>
    @endif
</div>
