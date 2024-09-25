<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\InsRdcStd;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public $q = '';

    public $perPage = 20;

    #[On('updated')]
    public function with(): array
    {
        $q = trim($this->q);
        $stds = InsRdcStd::where(function (Builder $query) use ($q) {
            $query
                ->orWhere('machine', 'LIKE', '%' . $q . '%')
                ->orWhere('mcs', 'LIKE', '%' . $q . '%');
            })
            ->orderBy('machine')
            ->paginate($this->perPage);

        return [
            'stds' => $stds,
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
<x-slot name="title">{{ __('Standar') . ' — ' . __('Pendataan Rheometer') }}</x-slot>
<x-slot name="header">
    <x-nav-insights-rdc-sub />
</x-slot>
<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Standar') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'std-create')"><i class="fa fa-plus fa-fw"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="fa fa-search fa-fw"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="inv-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="std-create">
            <x-modal name="std-create">
                <livewire:insight.rdc.manage.std-create />
            </x-modal>
        </div>
        <div wire:key="std-edit">   
            <x-modal name="std-edit">
                <livewire:insight.rdc.manage.std-edit />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="stds-table" class="table">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Mesin') }}</th>
                            <th>{{ __('MCS') }}</th>
                            <th>{{ __('Tag') }}</th>
                            <th>{{ __('TC10') }}</th>
                            <th>{{ __('TC90') }}</th>
                        </tr>
                        @foreach ($stds as $std)
                            <tr wire:key="std-tr-{{ $std->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'std-edit'); $dispatch('std-edit', { id: {{ $std->id }} })">
                                <td>
                                    {{ $std->id }}
                                </td>
                                <td>
                                    {{ $std->machine }}
                                </td>
                                <td>
                                    {{ sprintf('%03d', $std->mcs) }}
                                </td>
                                <td>
                                    {{ $std->ins_rdc_tag->name ?? '' }}
                                </td>
                                <td>
                                    {{ $std->tc10 }}
                                </td>
                                <td>
                                    {{ $std->tc90 }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="stds-none">
                        @if (!$stds->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada standar ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$stds->isEmpty())
                @if ($stds->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((stds) => {
                                stds.forEach(std => {
                                    if (std.isIntersecting) {
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
