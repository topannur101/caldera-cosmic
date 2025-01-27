<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    #[Url]
    public string $view;
}

?>

<x-slot name="title">{{ __('Kelola') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-invlegacy></x-nav-invlegacy>
</x-slot>

<div id="content" class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <h1 class="text-2xl text-neutral-900 dark:text-neutral-100 px-8">{{ __('Inventaris') }}</h1>  
    <div class="grid grid-cols-1 gap-1 my-8 ">
        <x-card-button type="button" x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'inv-first')">
            <div class="flex">
                <div>
                    <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                        <div class="m-auto"><i class="fa fa-plus"></i></div>
                    </div>
                </div>
                <div class="grow text-left truncate py-4">
                    <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{__('Buat barang baru')}}
                    </div>                        
                    <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                        {{__('Buat barang menggunakan kode')}}
                    </div>
                </div>
            </div>
        </x-card-button>
        <x-modal name="inv-first">
            @if(count(Auth::user()->invAreaIdsItemCreate()) ? true : false)
                <livewire:layout.inv-first lazy />
            @else
            <div class="p-6 ">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-3">
                    {{ __('Buat barang') }}
                </h2>
                {{ __('Kamu tidak memiliki wewenang untuk membuat barang di area inventaris manapun.') }}
                <div class="flex justify-end mt-6">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">{{ __('Paham') }}</x-primary-button>
                </div>
            </div>
            @endif
        </x-modal>
        <x-card-link href="{{ route('invlegacy.manage.circs-create')}}" wire:navigate>
            <div class="flex">
                <div>
                    <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                        <div class="m-auto"><i class="fa fa-pen"></i></div>
                    </div>
                </div>
                <div class="grow truncate py-4">
                    <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{__('Edit massal barang')}}
                    </div>                        
                    <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                        {{__('Lakukan pengeditan barang secara massal')}}
                    </div>
                </div>
            </div>
        </x-card-link>
        <x-card-link href="{{ route('invlegacy.manage.items-update')}}" wire:navigate>
            <div class="flex">
                <div>
                    <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                        <div class="m-auto"><i class="fa fa-arrow-right-arrow-left"></i></div>
                    </div>
                </div>
                <div class="grow truncate py-4">
                    <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{__('Sirkulasi massal')}}
                    </div>                        
                    <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                        {{__('Lakukan penambahan atau pengambilan secara massal')}}
                    </div>
                </div>
            </div>
        </x-card-link>
        <x-card-link href="{{ route('invlegacy.manage.locs')}}" wire:navigate>
            <div class="flex">
                <div>
                    <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                        <div class="m-auto"><i class="fa fa-map-marker-alt"></i></div>
                    </div>
                </div>
                <div class="grow truncate py-4">
                    <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{__('Kelola lokasi')}}
                    </div>                        
                    <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                        {{__('Kelola semua lokasi di satu area inventaris')}}
                    </div>
                </div>
            </div>
        </x-card-link>
        <x-card-link href="{{ route('invlegacy.manage.tags') }}" wire:navigate>
            <div class="flex">
                <div>
                    <div class="flex w-16 h-full text-neutral-600 dark:text-neutral-400">
                        <div class="m-auto"><i class="fa fa-tag"></i></div>
                    </div>
                </div>
                <div class="grow truncate py-4">
                    <div class="truncate text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{__('Kelola tag')}}
                    </div>                        
                    <div class="truncate text-sm text-neutral-600 dark:text-neutral-400">
                        {{__('Kelola semua tag di satu area inventaris')}}
                    </div>
                </div>
            </div>
        </x-card-link>
    </div>
</div>
