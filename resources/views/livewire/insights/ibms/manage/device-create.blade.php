<?php

use Livewire\Volt\Component;
use App\Models\InsIbmsDevice;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public string $name = "";
    public string $plant = "";
    public string $ip_address = "";
    public bool $is_active = true;

    public function rules()
    {
        return [
            "name" => ["required", "string", "min:1", "max:50"],
            "plant" => ["nullable", "string", "max:50"],
            "ip_address" => ["required", "ip", Rule::unique("ins_ip_blend_devices", "ip_address")],
            "is_active" => ["boolean"],
        ];
    }

    public function save()
    {
        Gate::authorize("manage", InsIbmsDevice::class);

        $this->name = trim($this->name);
        $this->plant = trim($this->plant);
        $validated = $this->validate();

        InsIbmsDevice::create([
            "name" => $validated["name"],
            "ip_address" => $validated["ip_address"],
            "config" => [
                "plant" => $validated["plant"] ?: null,
            ],
            "is_active" => $validated["is_active"],
        ]);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Perangkat dibuat") . '", { type: "success" })');
        $this->dispatch("updated");

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(["name", "plant", "ip_address", "is_active"]);
        $this->is_active = true;
    }
}; ?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Perangkat baru") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="ibms-device-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama") }}</label>
            <x-text-input id="ibms-device-name" wire:model="name" type="text" />
            @error("name")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <label for="ibms-device-plant" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Plant") }}</label>
            <x-text-input id="ibms-device-plant" wire:model="plant" type="text" placeholder="E" />
            @error("plant")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <label for="ibms-device-ip" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("IP Address") }}</label>
            <x-text-input id="ibms-device-ip" wire:model="ip_address" type="text" placeholder="172.70.88.199" />
            @error("ip_address")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <label class="flex items-center">
                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-blue-600 dark:focus:ring-offset-gray-800">
                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ __("Aktif") }}</span>
            </label>
        </div>
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __("Simpan") }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
