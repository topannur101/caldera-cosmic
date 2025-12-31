<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\InsStcMachine;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Database\Eloquent\Builder;

new #[Layout("layouts.app")] class extends Component {
    //
}; ?>

<x-slot name="title">{{ __("Mesin") . " — " . __("Kendali chamber IP") }}</x-slot>
<x-slot name="header">
    <x-nav-insights-stc-sub />
</x-slot>
<div id="content" class="py-12 max-w-5xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __("Kelola Models") }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can("superuser")
                    <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'machine-create')"><i class="icon-plus"></i></x-secondary-button>
                @endcan

                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open">
                    <i class="icon-search"></i>
                </x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="inv-q" x-ref="search" placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
    </div>

    <!-- table -->
     <div class="mt-6 px-6">
        <div class="overflow-x-auto">
            <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                <table wire:key="machines-table" class="table">
                    <thead>
                        <tr>
                            <th>{{ __("Kode Model") }}</th>
                            <th>{{ __("Nama Model") }}</th>
                            <th>{{ __("Standard Temperature") }}</th>
                            <th>{{ __("Standard Duration") }}</th>
                            <th>{{ __("Deskripsi") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Model A</td>
                            <td>Mesin Chamber IP A</td>
                            <td>Deskripsi untuk Model A</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Model B</td>
                            <td>Mesin Chamber IP B</td>
                            <td>Deskripsi untuk Model B</td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
