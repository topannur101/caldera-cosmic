<?php

use Livewire\Volt\Component;

use App\Models\InsRtcDevice;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {

    public InsRtcDevice $device;

    public $id;
    public $line;
    public $ip_address;

    public function placeholder()
    {
        return view('livewire.layout.modal-placeholder');
    }

    public function rules()
    {
        return [
            'line'          => ['required', 'integer', 'min:1', 'max:99', Rule::unique('ins_rtc_devices', 'line')->ignore($this->device->id ?? null)],
            'ip_address'    => ['required', 'ipv4', Rule::unique('ins_rtc_devices', 'ip_address')->ignore($this->device->id ?? null)]
        ];
    }

    public function mount(InsRtcDevice $device)
    {
        // edit mode
        if ($device->id) 
        {
            $this->fill(
                $device->only('line', 'ip_address')
            );
        } 
            else 
        {
            $this->device = new InsRtcDevice();
        }
        
    }

    public function save()
    {
        Gate::authorize('manage', $this->device);
        $validated = $this->validate();
        if ($this->device->id ?? false) {
            $this->device->update($validated);
            $msg = __('Perangkat diperbarui');
        } else {
            InsRtcDevice::create($validated);
            $msg = __('Perangkat didaftarkan');
        }
        $this->js('notyf.success("'.$msg.'")'); 
        $this->dispatch('updated');
        $this->js('window.dispatchEvent(escKey)'); 
    }

    public function delete()
    {
        Gate::authorize('manage', $this->device);
        $this->device->delete();
        $this->js('notyf.success("'. __('Perangkat dihapus') .'")'); 
        $this->dispatch('updated');
        $this->js('window.dispatchEvent(escKey)'); 
    }

};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Perangkat') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        @if ($device->id ?? false)
            <div class="mt-6">
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('ID') }}</label>
                <div class="px-3">{{ $device->id }}</div>
            </div>
        @endif
        <div class="mt-6">
            <label for="device-line"
                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
            <x-text-input id="device-line" wire:model="line" type="number" min="1" max="99" />
            @error('line')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <label for="device-ip-address"
                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Alamat IP') }}</label>
            <x-text-input id="device-ip-address" wire:model="ip_address" type="text" />
            @error('ip_address')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6 flex justify-between items-end">
            <x-secondary-button type="submit">
                <i class="fa fa-save mr-2"></i>
                {{ __('Simpan') }}
            </x-secondary-button>
            <div>
                @if ($device->id ?? false)
                <x-text-button type="button" class="uppercase text-xs text-red-500" wire:click="delete" wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}">
                    {{ __('Hapus') }}
                </x-text-button>
            @endif
            </div>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="delete"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target="delete" class="hidden"></x-spinner>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="save"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target="save" class="hidden"></x-spinner>
</div>
