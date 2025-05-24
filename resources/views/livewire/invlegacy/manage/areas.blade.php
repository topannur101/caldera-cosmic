<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

use App\Models\InvArea;

new #[Layout('layouts.app')] class extends Component {
    #[On('updated')]
    public function with(): array
    {
        return [
            'areas' => InvArea::all(),
        ];
    }
};

?>

<x-slot name="title">{{ __('Area') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-invlegacy-sub view="administration" />
</x-slot>

<div id="content" class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Area') }}</h1>
            <div class="flex gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" x-data=""
                        x-on:click.prevent="$dispatch('open-modal', 'area-create')"><i class="icon-plus"></i></x-secondary-button>
                @endcan
            </div>
        </div>
        <div wire:key="area-create">
            <x-modal name="area-create">
                <livewire:invlegacy.manage.area-create />
            </x-modal>
        </div>
        <div wire:key="area-edit">
            <x-modal name="area-edit">
                <livewire:invlegacy.manage.area-edit  />                    
            </x-modal> 
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="areas-table" class="table">
                        <tr class="uppercase text-xs">
                            <th>
                                {{ __('Nama') }}
                            </th>
                        </tr>
                        @foreach ($areas as $area)
                            <tr wire:key="area-tr-{{ $area->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'area-edit'); $dispatch('area-edit', { id: '{{ $area->id }}'})">
                                <td>
                                    {{ $area->name }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="areas-none">
                        @if (!$areas->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada area terdaftar') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
