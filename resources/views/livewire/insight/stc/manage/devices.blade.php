<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\InsStcDevice;
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
        $devices = InsStcDevice::where(function (Builder $query) use ($q) {
            $query
                ->orWhere('code', 'LIKE', '%' . $q . '%')
                ->orWhere('name', 'LIKE', '%' . $q . '%');
            })
            ->orderBy('id')
            ->paginate($this->perPage);

        return [
            'devices' => $devices,
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
<x-slot name="title">{{ __('Alat ukur') . ' — ' . __('Kendali chamber IP') }}</x-slot>
<x-slot name="header">
    <x-nav-insights-stc-sub />
</x-slot>
<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Alat ukur') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'device-create')"><i class="fa fa-plus fa-fw"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="fa fa-search fa-fw"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="inv-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="device-create">
            <x-modal name="device-create">
                <livewire:insight.stc.manage.device-create />
            </x-modal>
        </div>
        <div wire:key="device-edit">   
            <x-modal name="device-edit">
                <livewire:insight.stc.manage.device-edit />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="devices-table" class="table">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Kode') }}</th>
                            <th>{{ __('Nama') }}</th>
                        </tr>
                        @foreach ($devices as $device)
                            <tr wire:key="device-tr-{{ $device->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'device-edit'); $dispatch('device-edit', { id: {{ $device->id }} })">
                                <td>
                                    {{ $device->id }}
                                </td>
                                <td>
                                    {{ $device->code }}
                                </td>
                                <td>
                                    {{ $device->name }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="devices-none">
                        @if (!$devices->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada alat ukur ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$devices->isEmpty())
                @if ($devices->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((devices) => {
                                devices.forEach(device => {
                                    if (device.isIntersecting) {
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
