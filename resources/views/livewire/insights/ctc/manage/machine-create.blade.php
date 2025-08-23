<?php

use Livewire\Volt\Component;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public string $line = "";
    public string $ip_address = "";
    public string $name = "";
    public bool $is_active = true;

    public function rules()
    {
        return [
            "line" => ["required", "integer", "min:1", "max:99"],
            "ip_address" => ["required", "ipv4"],
            "name" => ["required", "string", "min:1", "max:50"],
            "is_active" => ["boolean"],
        ];
    }

    public function save()
    {
        // TODO: Replace with actual InsCtcMachine model when backend is ready
        // $device = new InsCtcMachine;
        // Gate::authorize('manage', $device);

        Gate::authorize("superuser");

        $this->validate();

        // TODO: Add uniqueness validation when database is ready
        // 'line' => ['required', 'integer', 'min:1', 'max:99', 'unique:ins_ctc_devices'],
        // 'ip_address' => ['required', 'ipv4', 'unique:ins_ctc_devices']

        // Mock device creation
        // $device->line           = $this->line;
        // $device->ip_address     = $this->ip_address;
        // $device->name           = $this->name;
        // $device->is_active      = $this->is_active;
        // $device->save();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Perangkat dibuat") . '", { type: "success" })');
        $this->dispatch("updated");

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(["line", "ip_address", "name", "is_active"]);
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Perangkat baru") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="device-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
            <x-text-input id="device-line" wire:model="line" type="number" min="1" max="99" />
            @error("line")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <label for="device-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama") }}</label>
            <x-text-input id="device-name" wire:model="name" type="text" placeholder="CTC Line X" />
            @error("name")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <label for="device-ip-address" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Alamat IP") }}</label>
            <x-text-input id="device-ip-address" wire:model="ip_address" type="text" placeholder="192.168.1.100" />
            @error("ip_address")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <x-checkbox id="device-is-active" wire:model="is_active">{{ __("Aktif") }}</x-checkbox>
        </div>
        <div class="mt-6 flex justify-end items-end">
            <x-primary-button type="submit">
                {{ __("Simpan") }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
