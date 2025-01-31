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

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
<div class="block sm:flex gap-x-6">
    <div wire:key="photo">
        <div class="sticky top-5 left-0">
            <livewire:inventory.items.photo />
            <div class="flex px-4 py-8 sm:py-5 text-sm text-neutral-600 dark:text-neutral-400">
                <div class="grow">{{ __('Diperbarui') . ': ' . '' }}</div>
                <x-link class="uppercase" href="{{ route('inventory.items.edit', ['id' => 0]) }}"><i
                        class="fa fa-pen"></i></x-link>
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
                <div class="flex mb-3">
                    <div>{{ 'Code' ? 'Code' : __('Tak ada kode') }}</div>
                    <div class="mx-3">•</div>
                    <div>
                        {{ '40' ? 'KG' . ' ' . '30.40' . ' / ' . 'KG' : __('Tak ada harga') }}
                    </div>
                </div>
                <div>
                    <x-text-button type="button" x-on:click="$dispatch('open-modal', 'edit-loc')" class="mr-4"><i
                            class="fa fa-map-marker-alt mr-2"></i>{{ 'A5' ? 'A5' : __('Tak ada lokasi') }}</x-text-button>
                    <x-modal :name="'edit-loc'">
                        @can('updateLoc', $inv_item)
                        <livewire:inventory.items.loc lazy />
                        @else
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-3">
                                {{ __('Perbarui lokasi') }}
                            </h2>
                            {{ __('Kamu tidak memiliki wewenang untuk perbarui langsung lokasi') }}
                            <div class="flex justify-end mt-6">
                                <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Paham') }}</x-secondary-button>
                            </div>
                        </div>
                        @endcan
                    </x-modal>
                    <x-text-button type="button" x-on:click="$dispatch('open-modal', 'edit-tags')"><i
                            class="fa fa-tag mr-2"></i>{{ 'Tags' ? 'Tags' : __('Tak ada tag') }}</x-text-button>
                    <x-modal :name="'edit-tags'">
                        @can('updateTag', $inv_item)
                            <livewire:inventory.items.tags lazy />
                            @else
                            <div class="p-6">
                                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-3">
                                    {{ __('Perbarui tag') }}
                                </h2>
                                {{ __('Kamu tidak memiliki wewenang untuk perbarui langsung tag') }}
                                <div class="flex justify-end mt-6">
                                    <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Paham') }}</x-secondary-button>
                                </div>
                            </div>
                            @endcan
                    </x-modal>

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
