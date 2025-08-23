<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout("layouts.app")] class extends Component {};

?>

<x-slot name="title">{{ __("Operasi massal barang") . " — " . __("Inventaris") }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __("Operasi massal barang") }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-700 dark:text-neutral-200">
    <div class="flex flex-col gap-y-6">
        <h1 class="uppercase text-sm text-neutral-500 px-8">{{ __("Buat") }}</h1>
        <div class="bg-white dark:bg-neutral-800 shadow overflow-hidden sm:rounded-lg divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
            <a href="{{ route("inventory.items.bulk-operation.create-new") }}" class="block hover:bg-caldy-500 hover:bg-opacity-10">
                <div class="flex items-center">
                    <div class="px-6 py-3">
                        <i class="icon-asterisk"></i>
                    </div>
                    <div class="py-3 grow">
                        <div class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Buat barang baru") }}</div>
                        <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-center gap-x-2 text-sm text-neutral-500">
                                {{ __("Registrasi barang baru") }}
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-3 text-lg">
                        <i class="icon-chevron-right"></i>
                    </div>
                </div>
            </a>
        </div>
        <h1 class="uppercase text-sm text-neutral-500 px-8">{{ __("Perbarui") }}</h1>
        <div class="bg-white dark:bg-neutral-800 shadow overflow-hidden sm:rounded-lg divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
            <a href="{{ route("inventory.items.bulk-operation.update-basic") }}" class="block hover:bg-caldy-500 hover:bg-opacity-10">
                <div class="flex items-center">
                    <div class="px-6 py-3">
                        <i class="icon-box"></i>
                    </div>
                    <div class="py-3 grow">
                        <div class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Perbarui info dasar") }}</div>
                        <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-center gap-x-2 text-sm text-neutral-500">
                                {{ __("Perbarui nama, deskripsi, dan tag barang") }}
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-3 text-lg">
                        <i class="icon-chevron-right"></i>
                    </div>
                </div>
            </a>
            <a href="{{ route("inventory.items.bulk-operation.update-location") }}" class="block hover:bg-caldy-500 hover:bg-opacity-10">
                <div class="flex items-center">
                    <div class="px-6 py-3">
                        <i class="icon-map-pin"></i>
                    </div>
                    <div class="py-3 grow">
                        <div class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Perbarui lokasi") }}</div>
                        <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-center gap-x-2 text-sm text-neutral-500">
                                {{ __("Perbarui lokasi induk dan bin barang") }}
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-3 text-lg">
                        <i class="icon-chevron-right"></i>
                    </div>
                </div>
            </a>
            <a href="{{ route("inventory.items.bulk-operation.update-stock") }}" class="block hover:bg-caldy-500 hover:bg-opacity-10">
                <div class="flex items-center">
                    <div class="px-6 py-3">
                        <i class="icon-banknote"></i>
                    </div>
                    <div class="py-3 grow">
                        <div class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Perbarui unit stok") }}</div>
                        <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-center gap-x-2 text-sm text-neutral-500">
                                {{ __("Perbarui mata uang, harga, dan satuan barang") }}
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-3 text-lg">
                        <i class="icon-chevron-right"></i>
                    </div>
                </div>
            </a>
            <a href="{{ route("inventory.items.bulk-operation.update-limit") }}" class="block hover:bg-caldy-500 hover:bg-opacity-10">
                <div class="flex items-center">
                    <div class="px-6 py-3">
                        <i class="icon-chevrons-down-up"></i>
                    </div>
                    <div class="py-3 grow">
                        <div class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Perbarui batas qty") }}</div>
                        <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-center gap-x-2 text-sm text-neutral-500">
                                {{ __("Perbarui batas minimum dan maksimum barang") }}
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-3 text-lg">
                        <i class="icon-chevron-right"></i>
                    </div>
                </div>
            </a>
            <a href="{{ route("inventory.items.bulk-operation.update-status") }}" class="block hover:bg-caldy-500 hover:bg-opacity-10">
                <div class="flex items-center">
                    <div class="px-6 py-3">
                        <i class="icon-toggle-right"></i>
                    </div>
                    <div class="py-3 grow">
                        <div class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Perbarui status") }}</div>
                        <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-center gap-x-2 text-sm text-neutral-500">
                                {{ __("Perbarui status barang menjadi aktif/nonaktif") }}
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-3 text-lg">
                        <i class="icon-chevron-right"></i>
                    </div>
                </div>
            </a>
        </div>
        <h1 class="uppercase text-sm text-neutral-500 px-8">{{ __("Tarik") }}</h1>
        <div class="bg-white dark:bg-neutral-800 shadow overflow-hidden sm:rounded-lg divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
            <a href="{{ route("inventory.items.bulk-operation.pull-photos") }}" class="block hover:bg-caldy-500 hover:bg-opacity-10">
                <div class="flex items-center">
                    <div class="px-6 py-3">
                        <i class="icon-images"></i>
                    </div>
                    <div class="py-3 grow">
                        <div class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __("Tarik foto") }}</div>
                        <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-center gap-x-2 text-sm text-neutral-500">
                                {{ __("Tarik foto dari sistem TTConsumable") }}
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-3 text-lg">
                        <i class="icon-chevron-right"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
