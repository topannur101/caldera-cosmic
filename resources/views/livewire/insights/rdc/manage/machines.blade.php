<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\InsRdcMachine;
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
        $machines = InsRdcMachine::where(function (Builder $query) use ($q) {
            $query
                ->orWhere('number', 'LIKE', '%' . $q . '%')
                ->orWhere('name', 'LIKE', '%' . $q . '%')
                ->orWhere('type', 'LIKE', '%' . $q . '%');
            })
            ->orderBy('id')
            ->paginate($this->perPage);

        return [
            'machines' => $machines,
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
<x-slot name="title">{{ __('Mesin') . ' — ' . __('Sistem data rheometer') }}</x-slot>
<x-slot name="header">
    <x-nav-insights-rdc-sub />
</x-slot>
<div id="content" class="py-12 max-w-4xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Mesin') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'machine-create')"><i class="icon-plus"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="icon-search"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="inv-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="machine-create">
            <x-modal name="machine-create" maxWidth="4xl">
                <livewire:insights.rdc.manage.machine-create />
            </x-modal>
        </div>
        <div wire:key="machine-edit">   
            <x-modal name="machine-edit" maxWidth="4xl">
                <livewire:insights.rdc.manage.machine-edit />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="machines-table" class="table">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Nomor') }}</th>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Tipe') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Konfigurasi') }}</th>
                        </tr>
                        @foreach ($machines as $machine)
                            <tr wire:key="machine-tr-{{ $machine->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'machine-edit'); $dispatch('machine-edit', { id: {{ $machine->id }} })"
                                class="{{ !($machine->is_active ?? true) ? 'opacity-60' : '' }}">
                                <td>
                                    {{ $machine->id }}
                                </td>
                                <td>
                                    {{ $machine->number }}
                                </td>
                                <td>
                                    {{ $machine->name }}
                                </td>
                                <td>
                                    <x-pill class="uppercase" color="{{ $machine->type === 'excel' ? 'green' : 'blue' }}">
                                        {{ $machine->type === 'excel' ? 'Excel' : 'TXT' }}
                                    </x-pill>
                                </td>
                                <td>
                                    <x-pill color="{{ ($machine->is_active ?? true) ? 'green' : 'red' }}">
                                        {{ ($machine->is_active ?? true) ? __('Aktif') : __('Nonaktif') }}
                                    </x-pill>
                                </td>
                                <td>
                                    @php
                                        $config = json_decode($machine->cells, true) ?? [];
                                        $configCount = count($config);
                                    @endphp
                                    {{ $configCount }} {{ __('field') }}{{ $configCount !== 1 ? 's' : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="machines-none">
                        @if (!$machines->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada mesin ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$machines->isEmpty())
                @if ($machines->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((machines) => {
                                machines.forEach(machine => {
                                    if (machine.isIntersecting) {
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