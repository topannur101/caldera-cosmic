<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Models\User;
use App\Models\InvCeAuth;
use App\Traits\HasDateRangeFilter;


new #[Layout("layouts.app")] class extends Component {
    use HasDateRangeFilter;
    public string $start_at = "";
    public string $end_at = "";

    public function mount() {
        // set default date today
        if (!$this->start_at) {
            $this->start_at = now()->toDateString();
        }
        if (!$this->end_at) {
            $this->end_at = now()->toDateString();
        }
    }
} ?>

<x-slot name="title">{{ __("Cari") . " — " . __("Inventaris") }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-ce></x-nav-inventory-ce>
</x-slot>

<div>
    
    <div class="py-6 max-w-8xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 gap-4">
        <h1>{{ __("Sirkulasi") }}</h1>
        <p class="mb-4">{{ __("Fitur ini masih dalam pengembangan.") }}</p>

        <!-- filter section -->
        <div class="static lg:sticky top-0 z-10 py-2">
            <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2">
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 px-4 py-3 lg:py-0 lg:px-5">
                    <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-700">
                        <label for="start_at" class="whitespace-nowrap text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">{{ __("Dari") }}</label>
                        <input type="date" id="start_at" wire:model.live="start_at" class="border-0 bg-transparent p-0 text-sm text-neutral-700 outline-none focus:ring-0 dark:text-neutral-100">
                    </div>
                    <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-700">
                        <label for="end_at" class="whitespace-nowrap text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">{{ __("Sampai") }}</label>
                        <input type="date" id="end_at" wire:model.live="end_at" class="border-0 bg-transparent p-0 text-sm text-neutral-700 outline-none focus:ring-0 dark:text-neutral-100">
                    </div>
                </div>

                <div class="flex items-center px-4 py-3 lg:py-0 lg:px-4">
                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <x-text-button class="h-9 rounded-lg border border-neutral-200 px-3 text-xs font-semibold uppercase text-neutral-700 dark:border-neutral-700 dark:text-neutral-200">
                                {{ __("Rentang") }}
                                <i class="icon-chevron-down ms-1"></i>
                            </x-text-button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link href="#" wire:click.prevent="setToday">
                                {{ __("Hari ini") }}
                            </x-dropdown-link>
                            <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                {{ __("Kemarin") }}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" />
                            <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                {{ __("Minggu ini") }}
                            </x-dropdown-link>
                            <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                {{ __("Minggu lalu") }}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" />
                            <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                {{ __("Bulan ini") }}
                            </x-dropdown-link>
                            <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                {{ __("Bulan lalu") }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>

                <div class="flex items-center px-4 py-3 lg:py-0 lg:px-4">
                    <!-- Additional content can be added here -->
                     <h1>Halo halo</h1>
                </div>
            </div>
        </div>
    </div>
</div>