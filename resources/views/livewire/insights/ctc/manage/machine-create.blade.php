<?php

use Livewire\Volt\Component;
use App\Models\InsCtcMachine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public string $line = "";
    public string $ip_address = "";

    public function rules()
    {
        return [
            "line" => ["required", "integer", "min:1", "max:99", "unique:ins_ctc_machines"],
            "ip_address" => ["required", "ipv4", "unique:ins_ctc_machines"],
        ];
    }

    public function messages()
    {
        return [
            "line.unique" => "Line sudah digunakan oleh mesin lain.",
            "ip_address.unique" => "Alamat IP sudah digunakan oleh mesin lain.",
            "line.required" => "Line harus diisi.",
            "ip_address.required" => "Alamat IP harus diisi.",
            "ip_address.ipv4" => "Format alamat IP tidak valid.",
        ];
    }

    public function save()
    {
        Gate::authorize("superuser");

        $validated = $this->validate();

        $machine = new InsCtcMachine();
        $machine->fill([
            "line" => (int) $validated["line"],
            "ip_address" => $validated["ip_address"],
        ]);

        $machine->save();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Mesin CTC berhasil dibuat") . '", { type: "success" })');
        $this->dispatch("updated");

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(["line", "ip_address"]);
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Mesin baru") }}
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
            <label for="device-ip-address" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Alamat IP") }}</label>
            <x-text-input id="device-ip-address" wire:model="ip_address" type="text" placeholder="192.168.1.100" />
            @error("ip_address")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                <strong>{{ __("Nama mesin") }}:</strong>
                {{ $line ? "Line " . $line : "-" }}
            </p>
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
