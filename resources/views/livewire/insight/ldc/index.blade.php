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
    <div class="flex flex-col gap-x-2 sm:flex-row">
        <div class="p-1">
            <livewire:insight.ldc.index-hides />
        </div>
        <div class="w-full overflow-hidden p-1">
            <div class="relative bg-white dark:bg-neutral-800 shadow rounded-lg overflow-hidden">
                <div id="ldc-index-groups" class="border-b border-neutral-100 dark:border-neutral-700 overflow-x-auto">
                    <livewire:insight.ldc.index-groups />
                </div>
                <div class="flex w-full">
                    <livewire:insight.ldc.index-form />
                </div>
            </div>
        </div>
    </div>
</div>
