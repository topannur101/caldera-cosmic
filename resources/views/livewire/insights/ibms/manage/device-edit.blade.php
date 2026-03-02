<?php

use Livewire\Volt\Component;
use App\Models\InsIbmsDevice;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;

new class extends Component {
    public int $id = 0;
    public string $name = "";
    public string $plant = "";
    public string $ip_address = "";
    public bool $is_active = true;

    public function rules()
    {
        return [
            "name" => ["required", "string", "min:1", "max:50"],
            "plant" => ["nullable", "string", "max:50"],
            "ip_address" => ["required", "ip", Rule::unique("ins_ip_blend_devices", "ip_address")->ignore($this->id)],
            "is_active" => ["boolean"],
        ];
    }

    #[On("device-edit")]
    public function loadDevice(int $id)
    {
        $device = InsIbmsDevice::find($id);

        if (! $device) {
            $this->handleNotFound();
            return;
        }

        $this->id = $device->id;
        $this->name = $device->name;
        $this->plant = (string) data_get($device->config, "plant", "");
        $this->ip_address = $device->ip_address;
        $this->is_active = $device->is_active;
        $this->resetValidation();
    }

    public function save()
    {
        $device = InsIbmsDevice::find($this->id);

        if (! $device) {
            $this->handleNotFound();
            $this->customReset();
            return;
        }

        Gate::authorize("manage", $device);

        $this->name = trim($this->name);
        $this->plant = trim($this->plant);
        $validated = $this->validate();

        $device->update([
            "name" => $validated["name"],
            "ip_address" => $validated["ip_address"],
            "config" => [
                "plant" => $validated["plant"] ?: null,
            ],
            "is_active" => $validated["is_active"],
        ]);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Perangkat diperbarui") . '", { type: "success" })');
        $this->dispatch("updated");
    }

    public function delete()
    {
        $device = InsIbmsDevice::find($this->id);

        if (! $device) {
            $this->handleNotFound();
            return;
        }

        Gate::authorize("manage", $device);
        $device->delete();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Perangkat dihapus") . '", { type: "success" })');
        $this->dispatch("updated");
        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(["id", "name", "plant", "ip_address", "is_active"]);
        $this->id = 0;
        $this->is_active = true;
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Tidak ditemukan") . '", { type: "danger" })');
        $this->dispatch("updated");
    }
}; ?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Edit Perangkat") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="ibms-device-name-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama") }}</label>
            <x-text-input id="ibms-device-name-edit" wire:model="name" type="text" />
            @error("name")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <label for="ibms-device-plant-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Plant") }}</label>
            <x-text-input id="ibms-device-plant-edit" wire:model="plant" type="text" placeholder="E" />
            @error("plant")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <label for="ibms-device-ip-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("IP Address") }}</label>
            <x-text-input id="ibms-device-ip-edit" wire:model="ip_address" type="text" placeholder="172.70.88.199" />
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
        <div class="mt-6 flex justify-between">
            <x-danger-button type="button" wire:click="delete" wire:confirm="{{ __('Apakah Anda yakin ingin menghapus perangkat ini?') }}">
                {{ __("Hapus") }}
            </x-danger-button>
            <x-primary-button type="submit">
                {{ __("Perbarui") }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
