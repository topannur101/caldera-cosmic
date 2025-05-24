<?php

use Livewire\Volt\Component;

use App\Models\InsStcDevice;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    
    public int $id;

    public string $code;
    public string $name = '';

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:20', Rule::unique('ins_stc_devices', 'code')->ignore($this->id ?? null)],
            'name' => ['required', 'string', 'min:1', 'max:20'],        ];
    }

    #[On('device-edit')]
    public function loadDevice(int $id)
    {
        $device = InsStcDevice::find($id);
        if ($device) {
            $this->id       = $device->id;
            $this->code     = $device->code;
            $this->name     = $device->name;
        
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        $device = InsStcDevice::find($this->id);
        $this->code = strtoupper(trim($this->code));
        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        if($device) {
            Gate::authorize('manage', $device);

            $device->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
            ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Alat ukur diperbarui') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset(['code', 'name']);
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
                {{ __('Alat ukur ') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="device-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nomor') }}</label>
            <x-text-input id="device-code" wire:model="code" type="text" :disabled="Gate::denies('manage', InsStcDevice::class)" />
            @error('code')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  
        <div class="mt-6">
            <label for="device-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="device-name" wire:model="name" type="text" :disabled="Gate::denies('manage', InsStcDevice::class)" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>    
        
        @can('manage', InsStcDevice::class)
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
