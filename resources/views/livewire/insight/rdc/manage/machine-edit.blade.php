<?php

use Livewire\Volt\Component;

use App\Models\InsRdcMachine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {

    public int $id;
    public $line;
    public $ip_address;

    public function rules()
    {
        return [
            'line'          => ['required', 'integer', 'min:1', 'max:99', Rule::unique('ins_rtc_devices', 'line')->ignore($this->id ?? null)],
            'ip_address'    => ['required', 'ipv4', Rule::unique('ins_rtc_devices', 'ip_address')->ignore($this->id ?? null)]
        ];
    }

        #[On('device-edit')]
    public function loadDevice(int $id)
    {
        $device = InsRdcMachine::find($id);
        if ($device) {
            $this->id           = $device->id;
            $this->line         = $device->line;
            $this->ip_address   = $device->ip_address;
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        $device = InsRdcMachine::find($this->id);
        $validated = $this->validate();

        if($device) {
            Gate::authorize('manage', $device);
            $device->update($validated);
            $this->js('$dispatch("close")');
            $this->js('notyfSuccess("' . __('Perangkat diperbarui') . '")');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function delete()
    {
        $device = InsRdcMachine::find($this->id);
        
        if($device) {
            Gate::authorize('manage', $device);
            $device->delete();

            $this->js('$dispatch("close")');
            $this->js('notyfSuccess("' . __('Perangkat dihapus') . '")');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
        }
        $this->customReset(); 
    }

    public function customReset()
    {
        $this->reset(['id', 'line', 'ip_address']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('notyfError("' . __('Tidak ditemukan') . '")');
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
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mb-6">
            <div class="mt-6">
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('ID') }}</label>
                <div class="px-3">{{ $id ?? '?' }}</div>
            </div>
            <div class="mt-6">
                <label for="device-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                <x-text-input id="device-line" wire:model="line" :disabled="Gate::denies('manage', InsRdcMachine::class)" type="number" min="1" max="99" />
                @error('line')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="device-ip-address"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Alamat IP') }}</label>
                <x-text-input id="device-ip-address" wire:model="ip_address" :disabled="Gate::denies('manage', InsRdcMachine::class)" type="text" />
                @error('ip_address')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>
        @can('manage', InsRdcMachine::class)
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
