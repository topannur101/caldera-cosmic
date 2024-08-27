<?php

use Livewire\Volt\Component;

use App\Models\InsRdcMachine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {

    public $name;
    public $data;

    public function rules()
    {
        return [
            'name'          => ['required', 'integer', 'min:1', 'max:99', 'unique:ins_rtc_devices'],
            'data'    => ['required', 'ipv4', 'unique:ins_rtc_devices']
        ];
    }


    public function save()
    {
        $device = new InsRdcMachine;
        Gate::authorize('manage', $device);

        $this->validate();
        $device->name           = $this->name;
        $device->data     = $this->data;
        $device->save();

        $this->js('$dispatch("close")');
        $this->js('notyfSuccess("' . __('Wewenang dibuat') . '")');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['name', 'data']);
    }

};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Mesin baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="device-name"
                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="device-name" wire:model="name" type="text" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <label for="device-ip-address"
                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Data') }}</label>
            <x-text-input id="device-ip-address" wire:model="data" type="text" />
            @error('data')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6 flex justify-end items-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
