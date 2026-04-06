<?php

use App\Models\InvCeMixingDevice;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

new class extends Component {
    public string $name = '';
    public string $node_id = '';
    public string $ws_host_rfid = '';
    public string $ws_port_rfid = '';
    public string $ws_host_micon = '';
    public string $ws_port_micon = '';

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'node_id' => ['required', 'string', 'max:100', Rule::unique('inv_ce_mixing_devices', 'node_id')],
            'ws_host_rfid' => ['nullable', 'string', 'max:255'],
            'ws_port_rfid' => ['nullable', 'integer', 'between:1,65535'],
            'ws_host_micon' => ['nullable', 'string', 'max:255'],
            'ws_port_micon' => ['nullable', 'integer', 'between:1,65535'],
        ];
    }

    public function save(): void
    {
        Gate::authorize('superuser');

        $this->name = trim($this->name);
        $this->node_id = trim($this->node_id);
        $this->ws_host_rfid = trim($this->ws_host_rfid);
        $this->ws_port_rfid = trim($this->ws_port_rfid);
        $this->ws_host_micon = trim($this->ws_host_micon);
        $this->ws_port_micon = trim($this->ws_port_micon);

        $validated = $this->validate();

        InvCeMixingDevice::create([
            'name' => $validated['name'] !== '' ? $validated['name'] : null,
            'node_id' => $validated['node_id'],
            'config' => $this->buildConfig(),
        ]);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Perangkat dibuat') . '", { type: "success" })');
        $this->dispatch('updated');
        $this->customReset();
    }

    protected function buildConfig(): ?array
    {
        $config = array_filter([
            'ws_host_rfid' => $this->ws_host_rfid !== '' ? $this->ws_host_rfid : null,
            'ws_port_rfid' => $this->ws_port_rfid !== '' ? $this->ws_port_rfid : null,
            'ws_host_micon' => $this->ws_host_micon !== '' ? $this->ws_host_micon : null,
            'ws_port_micon' => $this->ws_port_micon !== '' ? $this->ws_port_micon : null,
        ], fn ($value) => $value !== null);

        return $config ?: null;
    }

    public function customReset(): void
    {
        $this->reset(['name', 'node_id', 'ws_host_rfid', 'ws_port_rfid', 'ws_host_micon', 'ws_port_micon']);
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Perangkat baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
            <div>
                <label for="device-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                <x-text-input id="device-name" wire:model="name" type="text" placeholder="Mixing Device A" />
                @error('name') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
            </div>
            <div>
                <label for="device-node-id" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Node ID') }}</label>
                <x-text-input id="device-node-id" wire:model="node_id" type="text" placeholder="NODE-001" />
                @error('node_id') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-3">{{ __('Konfigurasi WebSocket') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="ws-host-rfid" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('WS Host RFID') }}</label>
                    <x-text-input id="ws-host-rfid" wire:model="ws_host_rfid" type="text" placeholder="172.70.66.131" />
                    @error('ws_host_rfid') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
                </div>
                <div>
                    <label for="ws-port-rfid" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('WS Port RFID') }}</label>
                    <x-text-input id="ws-port-rfid" wire:model="ws_port_rfid" type="number" min="1" max="65535" placeholder="8765" />
                    @error('ws_port_rfid') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
                </div>
                <div>
                    <label for="ws-host-micon" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('WS Host Micon') }}</label>
                    <x-text-input id="ws-host-micon" wire:model="ws_host_micon" type="text" placeholder="172.70.66.131" />
                    @error('ws_host_micon') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
                </div>
                <div>
                    <label for="ws-port-micon" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('WS Port Micon') }}</label>
                    <x-text-input id="ws-port-micon" wire:model="ws_port_micon" type="number" min="1" max="65535" placeholder="8767" />
                    @error('ws_port_micon') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">{{ __('Buat') }}</x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
