<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {};

?>
<x-slot name="title">{{ __('Wewenang') . ' — ' . __('Administrasi') }}</x-slot>

<x-slot name="header">
    <x-nav-admin></x-nav-admin>
</x-slot>

<div class="py-12">
    <div class="max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-600 dark:text-neutral-400">
        <ul>
            <li>Masukkan pengguna...</li>
            <li>Inventaris</li>
            <li>Kendali chamber IP</li>
            <li>Pemantauan open mill</li>
            <li>Sistem data rheometer</li>
            <li>Kendali tebal calendar</li>
            <li>Sistem data kulit</li>
        </ul>
        {{-- <div class="grid grid-cols-1 gap-1 my-8 ">
            <x-card-link href="{{ route('administration.manage.shmods') }}" wire:navigate>
                <div class="flex px-8">
                    <div>
                        <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                            <div class="m-auto"><i class="fa fa-fw fa-socks"></i></div>
                        </div>
                    </div>
                    <div class="grow truncate py-4">
                        <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Kelola model') }}
                        </div>
                        <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Kelola model sepatu') }}
                        </div>
                    </div>
                </div>
            </x-card-link>
        </div> --}}
    </div>
</div>
