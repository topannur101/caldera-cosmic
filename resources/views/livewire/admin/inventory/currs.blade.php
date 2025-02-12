<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\InvCurr;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public $q = '';

    public $perPage = 20;

    #[On('updated')]
    public function with(): array
    {
        $q = trim($this->q);
        $currs = InvCurr::where('name', 'LIKE', '%' . $q . '%')
            ->orderBy('id')
            ->paginate($this->perPage);

        return [
            'currs' => $currs,
        ];
    }

    public function updating($property)
    {
        if ($property == 'q') {
            $this->reset('perPage');
        }
    }

    public function loadMore()
    {
        $this->perPage += 20;
    }
};
?>
<x-slot name="title">{{ __('Inventaris') . ' — ' . __('Admin') }}</x-slot>

<x-slot name="header">
    <x-nav-admin>{{ __('Inventaris') }}</x-nav-admin>
</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Kelola mata uang') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'curr-create')"><i class="fa fa-plus fa-fw"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="fa fa-search fa-fw"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="inv-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="curr-create">
            <x-modal name="curr-create">
                <livewire:admin.inventory.curr-create />
            </x-modal>
        </div>
        <div wire:key="curr-edit">   
            <x-modal name="curr-edit">
                <livewire:admin.inventory.curr-edit />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="currs-table" class="table">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Nilai tukar') }}</th>
                            <th>{{ __('Aktif?') }}</th>
                        </tr>
                        @foreach ($currs as $curr)
                            <tr wire:key="curr-tr-{{ $curr->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'curr-edit'); $dispatch('curr-edit', { id: {{ $curr->id }} })">
                                <td>
                                    {{ $curr->id }}
                                </td>
                                <td>
                                    {{ $curr->name }}
                                </td>
                                <td>
                                    {{ $curr->rate }}
                                </td>
                                <td>
                                    {{ $curr->is_active ? __('Aktif') : __('Nonaktif') }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="currs-none">
                        @if (!$currs->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada mata uang ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$currs->isEmpty())
                @if ($currs->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((currs) => {
                                currs.forEach(curr => {
                                    if (curr.isIntersecting) {
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
