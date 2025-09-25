<?php

use Livewire\Volt\Component;
use App\Models\InsCtcMachine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    public int $id;
    public string $line;
    public string $ip_address;

    // Simple status only
    public bool $isOnline = false;

    public function rules()
    {
        return [
            "line" => ["required", "integer", "min:1", "max:99", Rule::unique("ins_ctc_machines", "line")->ignore($this->id ?? null)],
            "ip_address" => ["required", "ipv4", Rule::unique("ins_ctc_machines", "ip_address")->ignore($this->id ?? null)],
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

    #[On("machine-edit")]
    public function loadMachine(int $id)
    {
        $machine = InsCtcMachine::find($id);

        if ($machine) {
            $this->id = $machine->id;
            $this->line = (string) $machine->line;
            $this->ip_address = $machine->ip_address;

            // Hanya load status online
            $this->isOnline = $machine->is_online();

            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        Gate::authorize("superuser");

        $machine = InsCtcMachine::find($this->id);

        if ($machine) {
            $validated = $this->validate();

            $machine->update([
                "line" => (int) $validated["line"],
                "ip_address" => $validated["ip_address"],
            ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __("Mesin berhasil diperbarui") . '", { type: "success" })');
            $this->dispatch("updated");
        } else {
            $this->handleNotFound();
        }
    }

    public function delete()
    {
        Gate::authorize("superuser");

        $machine = InsCtcMachine::find($this->id);

        if ($machine) {
            // Check if machine has metrics before deleting
            if ($machine->ins_ctc_metrics()->exists()) {
                $this->js('toast("' . __("Tidak dapat menghapus mesin yang memiliki data metrics") . '", { type: "danger" })');
                return;
            }

            $machine->delete();

            $this->js('$dispatch("close")');
            $this->js('toast("' . __("Mesin berhasil dihapus") . '", { type: "success" })');
            $this->dispatch("updated");
            $this->customReset();
        } else {
            $this->handleNotFound();
        }
    }

    public function customReset()
    {
        $this->reset(["id", "line", "ip_address", "isOnline"]);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Mesin tidak ditemukan") . '", { type: "danger" })');
        $this->dispatch("updated");
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Edit Mesin") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>

        <!-- Machine Info -->
        <div class="mt-6 grid grid-cols-2 gap-4">
            <div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("ID") }}</label>
                <div class="px-3 py-2 bg-neutral-50 dark:bg-neutral-700 rounded">{{ $id ?? "?" }}</div>
            </div>
            <div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama") }}</label>
                <div class="px-3 py-2 bg-neutral-50 dark:bg-neutral-700 rounded">{{ $line ? "Line " . $line : "-" }}</div>
            </div>
        </div>

        <!-- Simple Status -->
        <div class="mt-6">
            <h3 class="font-medium text-neutral-900 dark:text-neutral-100 mb-3">{{ __("Status") }}</h3>
            <div class="px-3 py-2 bg-neutral-50 dark:bg-neutral-700 rounded w-fit">
                <div class="flex items-center">
                    @if ($isOnline)
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                        <span class="text-green-600">Online</span>
                    @else
                        <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                        <span class="text-red-600">Offline</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Editable Fields -->
        <div class="mt-6">
            <h3 class="font-medium text-neutral-900 dark:text-neutral-100 mb-3">{{ __("Pengaturan") }}</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="device-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
                    <x-text-input id="device-line" wire:model="line" :disabled="Gate::denies('superuser')" type="number" min="1" max="99" />
                    @error("line")
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div>
                    <label for="device-ip-address" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Alamat IP") }}</label>
                    <x-text-input id="device-ip-address" wire:model="ip_address" :disabled="Gate::denies('superuser')" type="text" />
                    @error("ip_address")
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>
        </div>

        @can("superuser")
            <div class="flex justify-between items-end mt-6">
                <div>
                    <x-text-button
                        type="button"
                        class="uppercase text-xs text-red-500"
                        wire:click="delete"
                        wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}"
                    >
                        {{ __("Hapus") }}
                    </x-text-button>
                </div>
                <x-primary-button type="submit">
                    {{ __("Simpan") }}
                </x-primary-button>
            </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
