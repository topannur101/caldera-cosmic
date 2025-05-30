<?php

use Livewire\Volt\Component;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {

    public int $id;
    public string $line;
    public string $ip_address;
    public string $name;
    public bool $is_active;

    public function rules()
    {
        return [
            'line'          => ['required', 'integer', 'min:1', 'max:99'],
            'ip_address'    => ['required', 'ipv4'],
            'name'          => ['required', 'string', 'min:1', 'max:50'],
            'is_active'     => ['boolean']
        ];
        // TODO: Add uniqueness validation when database is ready
        // Rule::unique('ins_ctc_devices', 'line')->ignore($this->id ?? null)
        // Rule::unique('ins_ctc_devices', 'ip_address')->ignore($this->id ?? null)
    }

    #[On('device-edit')]
    public function loadDevice(int $id)
    {
        // TODO: Replace with actual InsCtcDevice model when backend is ready
        // $device = InsCtcDevice::find($id);
        
        // Mock data for development
        $mockDevices = [
            1 => [
                'id' => 1,
                'line' => 3,
                'ip_address' => '172.70.86.13',
                'name' => 'CTC Line 3',
                'is_active' => true
            ],
            2 => [
                'id' => 2,
                'line' => 4,
                'ip_address' => '172.70.86.14',
                'name' => 'CTC Line 4',
                'is_active' => true
            ],
            3 => [
                'id' => 3,
                'line' => 5,
                'ip_address' => '172.70.86.15',
                'name' => 'CTC Line 5',
                'is_active' => false
            ]
        ];

        $device = $mockDevices[$id] ?? null;
        
        if ($device) {
            $this->id           = $device['id'];
            $this->line         = $device['line'];
            $this->ip_address   = $device['ip_address'];
            $this->name         = $device['name'];
            $this->is_active    = $device['is_active'];
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        // TODO: Replace with actual InsCtcDevice model when backend is ready
        // $device = InsCtcDevice::find($this->id);
        // Gate::authorize('manage', $device);
        
        Gate::authorize('superuser');
        $validated = $this->validate();

        // Mock device update
        // if($device) {
        //     $device->update($validated);
        //     // ... success handling
        // }

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Perangkat diperbarui') . '", { type: "success" })');
        $this->dispatch('updated');
    }

    public function delete()
    {
        // TODO: Replace with actual InsCtcDevice model when backend is ready
        // $device = InsCtcDevice::find($this->id);
        // Gate::authorize('manage', $device);
        
        Gate::authorize('superuser');

        // Mock device deletion
        // if($device) {
        //     $device->delete();
        //     // ... success handling
        // }

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Perangkat dihapus') . '", { type: "success" })');
        $this->dispatch('updated');
        $this->customReset(); 
    }

    public function customReset()
    {
        $this->reset(['id', 'line', 'ip_address', 'name', 'is_active']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }

};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Perangkat ') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mb-6">
            <div class="mt-6">
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('ID') }}</label>
                <div class="px-3">{{ $id ?? '?' }}</div>
            </div>
            <div class="mt-6">
                <label for="device-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                <x-text-input id="device-line" wire:model="line" :disabled="Gate::denies('superuser')" type="number" min="1" max="99" />
                @error('line')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="device-name"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                <x-text-input id="device-name" wire:model="name" :disabled="Gate::denies('superuser')" type="text" />
                @error('name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="device-ip-address"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Alamat IP') }}</label>
                <x-text-input id="device-ip-address" wire:model="ip_address" :disabled="Gate::denies('superuser')" type="text" />
                @error('ip_address')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <x-checkbox id="device-is-active" wire:model="is_active" :disabled="Gate::denies('superuser')">{{ __('Aktif') }}</x-checkbox>
            </div>
        </div>
        @can('superuser')
        <div class="flex justify-between items-end">
            <div>
                <x-text-button type="button" class="uppercase text-xs text-red-500" wire:click="delete" wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}">
                    {{ __('Hapus') }}
                </x-text-button>
            </div>
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>