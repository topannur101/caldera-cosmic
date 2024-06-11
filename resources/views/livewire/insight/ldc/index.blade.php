<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {};

?>

<x-slot name="title">{{ __('Leather Data Collection') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-ldc></x-nav-insights-ldc>
</x-slot>

<div id="content" class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
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
</div>
