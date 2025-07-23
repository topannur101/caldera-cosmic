<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

use App\Models\TskType;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public $q = '';
    public $perPage = 10;

    public function with(): array
    {
        $q = trim($this->q);
        $types = TskType::query();

        if ($q) {
            $types->where('name', 'LIKE', '%' . $q . '%');
        }

        return [
            'types' => $types->withCount('tsk_items')->orderBy('name')->paginate($this->perPage),
        ];
    }

    #[On('updated')]
    public function refresh()
    {
        // Refresh component
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};
?>
<x-slot name="title">{{ __('Tipe') . ' — ' . __('Tugas') }}</x-slot>
<x-slot name="header">
    <x-nav-task-sub>{{ __('Kelola') }}</x-nav-task-sub>
</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Tipe') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'type-create')"><i class="icon-plus"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="icon-search"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="type-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>

        <div wire:key="type-create">
            <x-modal name="type-create">
                <livewire:tasks.manage.type-create />
            </x-modal>
        </div>
        
        <div wire:key="type-edit">
            <x-modal name="type-edit">
                <livewire:tasks.manage.type-edit />
            </x-modal>
        </div>

        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="types-table" class="table">
                        <tr>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Tugas') }}</th>
                            <th>{{ __('Status') }}</th>
                            @can('superuser')
                            <th>{{ __('Tindakan') }}</th>
                            @endcan
                        </tr>
                        @foreach ($types as $type)
                            <tr wire:key="type-tr-{{ $type->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'type-edit'); $dispatch('type-edit', { id: {{ $type->id }} })">
                                <td>
                                    <div class="font-medium">{{ $type->name }}</div>
                                </td>
                                <td>
                                    {{ $type->tsk_items_count . ' ' . __('tugas') }}
                                </td>
                                <td>
                                    @if($type->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                            {{ __('Aktif') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                            {{ __('Nonaktif') }}
                                        </span>
                                    @endif
                                </td>
                                @can('superuser')
                                <td>
                                    <x-text-button x-on:click.stop="$dispatch('open-modal', 'type-edit'); $dispatch('type-edit', { id: {{ $type->id }} })">
                                        <i class="icon-edit"></i>
                                    </x-text-button>
                                </td>
                                @endcan
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="types-none">
                        @if (!$types->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada tipe ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$types->isEmpty())
                @if ($types->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((types) => {
                                types.forEach(type => {
                                    if (type.isIntersecting) {
                                        @this.loadMore()
                                    }
                                })
                            })
                            observer.observe(this.$el)
                        }
                    }" x-init="observe"></div>
                    <x-spinner class="sm" />
                @else
                    <div class="mx-auto">{{ __('Tidak ada lagi') }}</div>
                @endif
            @endif
        </div>
    </div>
</div>