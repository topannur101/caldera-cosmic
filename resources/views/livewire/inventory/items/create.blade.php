<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
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
    <div class="flex items-center gap-x-6 text-neutral-500 p-4 sm:rounded-lg overflow-hidden mb-8 border border-dashed border-neutral-300 dark:border-neutral-500">
        <div class="grow">
            <span class="uppercase font-bold mb-2"><i class="fa fa-fw fa-pen mr-2"></i>{{ __('Mode edit') . ': ' }}</span>
            <span>{{ __('Klik bidang yang hendak di edit, bila sudah selesai klik tombol simpan.') }}</span>
        </div>
        <div>
            <x-primary-button type="button" wire:click="save">{{ __('Simpan') }}</x-primary-button>
        </div>
    </div>
    <div class="block sm:flex gap-x-6">
        <div wire:key="photo">
            <div class="sticky top-5 left-0">
                <livewire:inventory.items.photo />
                <div class="grid grid-cols-1 divide-y divide-neutral-200 dark:divide-neutral-800 text-neutral-500 px-4 my-6 text-sm">
                    <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-tent me-2"></i>TT MM</div>
                    <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-check-circle me-2"></i>Aktif</div>
                    <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-pen me-2"></i>Edit barang</div>
                    <div class="py-3">Terakhir diperbarui: 2024-01-27</div>
                    <div class="py-3">Pengambilan terakhir: 2024-01-27</div>
                </div>
            </div>
        </div>
        <div class="grow">
            <div class="bg-white dark:bg-neutral-800 shadow rounded-lg px-6 divide-y divide-neutral-200 dark:divide-neutral-700">
                <div class="py-6">
                    <h1 class="text-2xl font-medium text-neutral-900 dark:text-neutral-100 mb-3">{{ __('Nama barang') }}</h1>
                    <p>{{ __('Deskripsi barang akan muncul disini') }}</p>
                </div>
                <div class="py-6 flex flex-col lg:flex-row gap-x-6 gap-y-3 text-neutral-500 text-sm">                    
                    <div>{{ 'Code' ? __('Tak ada kode') : __('TAK ADA KODE') }}</div>
                    <div>
                        <x-text-button type="button"><i class="fa fa-inbox me-2"></i>{{ false ? 'A5-01-23' : __('Tak ada lokasi bin') }}</x-text-button>
                    </div>
                    <div>
                        <x-text-button type="button" x-on:click="$dispatch('open-modal', 'edit-tags')"><i class="fa fa-tag me-2"></i>{{ false ? 'OKC' : __('Tak ada tag') }}</x-text-button>
                    </div>
                </div>
            </div>
            <div class="mt-6 bg-white dark:bg-neutral-800 shadow rounded-lg px-6 divide-y divide-neutral-200 dark:divide-neutral-700">
                <div class="py-6">
                    <div class="text-sm text-center text-neutral-500 border-b border-neutral-200 dark:border-neutral-700">
                        <ul class="flex flex-wrap gap-x-4 uppercase">
                            <li class="me-2">
                                <a href="#" class="inline-block pb-3 text-neutral-800 font-bold dark:text-neutral-200 border-b-2 border-caldy-500 rounded-t-lg active dark:border-caldy-500" aria-current="page">321 USD / PCK</a>
                            </li>
                            <li class="me-2">
                                <a href="#" class="inline-block pb-3 border-b-2 border-transparent hover:text-neutral-600 hover:border-neutral-300 dark:hover:text-neutral-300">0.5 USD / EA</a>
                            </li>
                            <li class="me-2">
                                <a href="#" class="inline-block pb-3 border-b-2 border-transparent hover:text-neutral-600 hover:border-neutral-300 dark:hover:text-neutral-300"><i class="fa fa-plus"></i></a>
                            </li>
                        </ul>
                    </div> 
                    <div class="mt-4">
                        <div class="flex flex-col md:flex-row gap-y-4 justify-between">
                            <div class="flex gap-x-2 items-baseline">
                                <div class="text-4xl">90</div>
                                <div class="text-sm font-bold">PCK</div>
                            </div>
                            <div class="spinner-group my-auto">
                                <x-secondary-button type="button"><i class="fa fa-fw fa-minus"></i></x-secondary-button>
                                <x-text-input-spinner id="inv-circ-qty" class="w-full md:w-24 p-2 text-center" name="qty"
                                    type="number" value="" placeholder="Qty"></x-text-input-spinner>
                                <x-secondary-button type="button"><i class="fa fa-fw fa-plus"></i></x-secondary-button>
                            </div>
                        </div>
                        <div class="mt-6 grid grid-cols-1 gap-y-6">
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Keterangan') }}</label>
                                <x-text-input placeholder="{{ __('Ketik disini...') }}"></x-text-input-t>
                            </div>
                            <div >
                                <label for="omv-user"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengguna') }}</label>
                                <x-text-input-icon icon="fa fa-fw fa-user" id="omv-user" type="text" autocomplete="off"
                                    placeholder="{{ __('Pengguna') }}" />
                            </div>
                            <div class="flex flex-col md:flex-row items-end md:items-center gap-y-4 justify-end">
                                <div class="text-neutral-500 uppercase text-xs font-bold px-3">Amount: 1240 USD</div>
                                <div>
                                    <x-secondary-button type="button"><span class="text-red-500"><i class="fa fa-fw fa-minus mr-2"></i>{{ __('Ambil') . ' ' }}34 PCK</span></x-secondary-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>  
                <div class="py-6">
                    <div class="flex justify-between items-center mb-4">
                        <div class="uppercase text-neutral-500 text-sm">
                            {{ __('Sirkulasi') }}
                        </div>
                        <div class="btn-group">
                            <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'inv-item-circs-chart')"><i
                                class="fa fa-chart-line"></i></x-secondary-button>
                            <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'inv-item-circs-download')"><i
                                class="fa fa-download"></i></x-secondary-button>
                        </div>
                    </div>
                    <div class="truncate">
                        <div wire:key="circ-button-xx" class="py-3 text-sm truncate rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800"
                            x-on:click.prevent="$dispatch('open-modal', 'circ-edit-xx')">
                            <div class="flex items-center">
                                <div>
                                    <div class="w-24 truncate text-base">
                                        <i class="fa fa-fw fa-minus mr-1 "></i>23 PCK
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="w-8 h-8 mr-2 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                            <path
                                                d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="truncate">
                                    <div class="truncate">
                                        <div class="text-xs truncate text-neutral-400 dark:text-neutral-600">
                                            Andi 
                                            <span title="{{ __('Didelegasikan oleh:') . ' ' . 'Edwin' . ' (' . 'TT17110594' . ')' }}"><i class="fa fa-handshake-angle"></i></span>
                                            <span
                                                class="mx-1">•</span>3 bulan yang lalu</div>
                                        <div class="text-base truncate">
                                            Untuk pemakaian di mesin
                                        </div>
                                    </div>
                                </div>
                                <div class="ml-auto pl-4 text-sm">
                                    <i class="fa fa-fw fa-hourglass"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
