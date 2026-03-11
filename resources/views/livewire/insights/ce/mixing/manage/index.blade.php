<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout("layouts.app")] class extends Component {};

?>

<x-slot name="title">{{ __("Kelola") . " — " . __("Ce Mixing Room") }}</x-slot>

<x-slot name="header">
    <x-nav-insights-ce-mix></x-nav-insights-ce-mix>
</x-slot>

<div class="py-12">
    <div class="max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-600 dark:text-neutral-400">
        <h1 class="text-2xl text-neutral-900 dark:text-neutral-100 px-8">{{ __("Kelola Mixing") }}</h1>
        <div class="grid grid-cols-1 gap-1 my-8">
            <x-card-link href="{{ route('insights.ce.mixing.manage.recipes') }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="icon-user-lock"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __("Kelola resep mixing") }}
                        </div>
                    </div>
                </div>
            </x-card-link>
        </div>
    </div>
</div>