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
    <x-nav-insights-rtc></x-nav-insights-rtc>
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
                            <div class="m-auto"><i class="fa fa-fw fa-calendar-day"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Ringkasan harian waktu nyata') }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Tampilkan ringkasan harian pada satu line produksi') }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insight.ss', ['id' => 5]) }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="fa fa-fw fa-toilet-paper"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Ringkasan gilingan waktu nyata') }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Tampilkan ringkasan gilingan pada satu line produksi') }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insight.ss', ['id' => 6]) }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="fa fa-fw fa-table"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Data mentah waktu nyata') }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Tampilkan riwayat data mentah pada satu line produksi') }}
                        </div>
                    </div>
                </div>
            </x-card-link>
            <x-card-link href="{{ route('insight.ss', ['id' => 2]) }}" wire:navigate>
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
