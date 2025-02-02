<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\InvItem;

new #[Layout('layouts.app')] 
class extends Component {

    public $inv_item;

    public $url;

    public $freqMsg;
    public $circMsg;
  
};

?>

<x-slot name="title">{{ __('Barang baru') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Barang baru') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-5xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
<div class="block sm:flex gap-x-6">
    <div wire:key="photo">
        <div class="sticky top-5 left-0">
            <livewire:inventory.items.photo />
            <div class="grid grid-cols-1 divide-y divide-neutral-200 dark:divide-neutral-700 text-neutral-500 p-4 text-sm">
                <div class="py-2"><i class="text-neutral-400 dark:text-neutral-700 fa fa-fw fa-tent me-2"></i>TT MM</div>
                <div class="py-2"><i class="text-neutral-400 dark:text-neutral-700 fa fa-fw fa-check-circle me-2"></i>Aktif</div>
                <div class="py-2"><i class="text-neutral-400 dark:text-neutral-700 fa fa-fw fa-pen me-2"></i>Edit barang</div>
                <div class="py-2">Terakhir diperbarui: 2024-01-27</div>
                <div class="py-2">Pengambilan terakhir: 2024-01-27</div>
            </div>
        </div>
    </div>
    <div class="w-full overflow-hidden">
        <div class="px-4">
            <h1 class="text-2xl mb-3 text-neutral-900 dark:text-neutral-100">{{ 'Name' }}</h1>
            <p class="mb-4">{{ 'Desc' }}</p>
        </div>
        <div class="text-neutral-600 dark:text-neutral-400">
            <hr class="border-neutral-200 dark:border-neutral-800" />
            <div class="px-4 py-8 sm:py-5">
                <div class="flex">
                    <div>{{ 'Code' ? 'Code' : __('Tak ada kode') }}</div>
                    <div class="mx-3">•</div>
                    <x-text-button type="button" x-on:click="$dispatch('open-modal', 'edit-loc')"><i
                            class="fa fa-inbox me-2"></i>{{ 'A5' ? 'A5' : __('Tak ada lokasi') }}</x-text-button>
                    <div class="mx-3">•</div>
                    <x-text-button type="button" x-on:click="$dispatch('open-modal', 'edit-tags')"><i
                            class="fa fa-tag me-2"></i>{{ 'Tags' ? 'Tags' : __('Tak ada tag') }}</x-text-button>
                </div>
            </div>
            <hr class="border-neutral-200 dark:border-neutral-800" />
            <div class="flex px-4 py-8 sm:py-5 text-sm">
                @if (true)
                    <div>{{ __('Aktif') }}</div>
                @else
                    <div class="text-red-500">{{ __('Nonaktif') }}</div>
                @endif
                <div class="mx-2">•</div>
                <div>{{ 'TT MM' }}</div>
            </div>
        </div>
        <div wire:key="inv-item-circ-container">
        </div>
        <div wire:key="inv-item-circs-container" x-data="{ circs: false }">
            <div class="flex justify-between px-4 py-8 sm:py-5 text-neutral-600 dark:text-neutral-400 text-sm">
                <div>{{ $freqMsg }}</div>
                <div><x-text-button @click="circs = !circs" type="button">{{ $circMsg }}<i x-show="!circs"
                            class="fa fa-chevron-down ml-2"></i><i x-show="circs" x-cloak
                            class="fa fa-chevron-up ml-2"></i></x-text-button></div>
            </div>
            <div x-show="circs" x-cloak class="text-neutral-600 dark:text-neutral-400 mb-4">
                <hr class="border-neutral-200 dark:border-neutral-800" />
                <livewire:inventory.items.circs wire:key="inv-item-circs" lazy />
            </div>
        </div>
    </div>
    <div x-data x-init="document.addEventListener('keydown', function(event) {
        if (event.key === '#') {
        const code = prompt();

        if (code !== null) {
        $wire.qu(code)
        }
                }
    });"></div>
</div>
</div>
