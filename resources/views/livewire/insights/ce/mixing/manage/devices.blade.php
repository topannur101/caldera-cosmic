<?php

use App\Models\InvCeMixingDevice;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new #[Layout("layouts.app")] class extends Component {
    public $devices = [];

    public function mount(): void
    {
        $this->loadDevices();
    }

    #[On('updated')]
    public function loadDevices(): void
    {
        $this->devices = InvCeMixingDevice::query()
            ->orderBy('name')
            ->orderBy('node_id')
            ->get();
    }
};
?>

<x-slot name="title">{{ __('Perangkat') . ' — ' . __('Ce Mixing') }}</x-slot>
<x-slot name="header">
    <x-nav-insights-ce-mix />
</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Perangkat Mixing') }}</h1>
            <div class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'device-create')">
                        <i class="icon-plus"></i>
                    </x-secondary-button>
                @endcan
            </div>
        </div>

        <div wire:key="device-create-modal">
            <x-modal name="device-create" maxWidth="xl">
                <livewire:insights.ce.mixing.manage.device-create wire:key="ce-device-create-modal" lazy />
            </x-modal>
        </div>
        <div wire:key="device-edit-modal">
            <x-modal name="device-edit" maxWidth="xl">
                <livewire:insights.ce.mixing.manage.device-edit wire:key="ce-device-edit-modal" lazy />
            </x-modal>
        </div>

        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="devices-table" class="table">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Node ID') }}</th>
                            <th>{{ __('Konfigurasi') }}</th>
                            <th>{{ __('Diperbarui') }}</th>
                        </tr>
                        @foreach (($devices ?? []) as $device)
                            @php
                                $configPreview = $device->config
                                    ? json_encode($device->config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                    : '—';
                            @endphp
                            <tr
                                wire:key="device-tr-{{ $device->id . $loop->index }}"
                                tabindex="0"
                                x-on:click="
                                    $dispatch('open-modal', 'device-edit')
                                    $dispatch('device-edit', { id: {{ $device->id }} })
                                "
                            >
                                <td>{{ $device->id }}</td>
                                <td>{{ $device->name ?: '—' }}</td>
                                <td class="font-mono">{{ $device->node_id }}</td>
                                <td>
                                    <div class="max-w-[18rem] truncate text-xs font-mono text-neutral-500 dark:text-neutral-400" title="{{ $configPreview }}">
                                        {{ $configPreview }}
                                    </div>
                                </td>
                                <td>{{ $device->updated_at?->format('d M Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="devices-none">
                        @if (collect($devices ?? [])->isEmpty())
                            <div class="text-center py-12">
                                {{ __('Tak ada perangkat ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
