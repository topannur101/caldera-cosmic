<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout("layouts.app")] class extends Component {};

?>

<x-slot name="title">{{ __("Tayangan slide") . " — " . __("Kendali tebal calendar") }}</x-slot>

<x-slot name="header">
    <x-nav-insights-ctc></x-nav-insights-ctc>
</x-slot>

<div class="py-12">
    <div class="max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-600 dark:text-neutral-400">
        <h1 class="text-2xl mb-6 text-neutral-900 dark:text-neutral-100 px-8">{{ __("Tayangan slide") }}</h1>
        <div class="grid grid-cols-1 gap-1 my-8">
                <x-card-link href="{{ route('insights.ctc.slides.realtime') }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="icon-chart-area"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __("Bagan garis waktu nyata") }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __("Tampilkan bagan garis pada satu line produksi") }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insights.ss', ['id' => 8]) }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="icon-calendar-day"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __("Ringkasan harian waktu nyata") }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __("Tampilkan ringkasan harian pada satu line produksi") }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insights.ss', ['id' => 9]) }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="icon-shell"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __("Ringkasan gilingan waktu nyata") }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __("Tampilkan ringkasan gilingan pada satu line produksi") }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insights.ss', ['id' => 10]) }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="icon-table"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __("Data mentah waktu nyata") }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __("Tampilkan riwayat data mentah pada satu line produksi") }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insights.ss', ['id' => 11]) }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="icon-image"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __("Info model waktu nyata") }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __("Tampilkan info model pada satu line produksi") }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insights.ss', ['id' => 12]) }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="icon-cpu"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __("Status rekomendasi resep") }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __("Tampilkan status rekomendasi dan override operator") }}
                        </div>
                    </div>
                </div>
            </x-card-link>
        </div>
    </div>
</div>
