<?php

use Livewire\Volt\Component;

use App\Models\InsLdcMachine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    public int $id;

    public string $code;

    public function rules()
    {
        return [
            "code" => ["size:2", Rule::unique("ins_ldc_machines", "code")->ignore($this->id ?? null)],
        ];
    }

    #[On("machine-edit")]
    public function loadMachine(int $id)
    {
        $machine = InsLdcMachine::find($id);
        if ($machine) {
            $this->id = $machine->id;
            $this->code = $machine->code;

            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        $machine = InsLdcMachine::find($this->id);
        $this->code = strtoupper(trim($this->code));
        $validated = $this->validate();

        if ($machine) {
            Gate::authorize("manage", $machine);

            $machine->update([
                "code" => $validated["code"],
            ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __("Mesin diperbarui") . '", { type: "success" })');
            $this->dispatch("updated");
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset(["code"]);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Tidak ditemukan") . '", { type: "danger" })');
        $this->dispatch("updated");
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Mesin ") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="machine-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Kode") }}</label>
            <x-text-input id="machine-code" wire:model="code" type="text" :disabled="Gate::denies('manage', InsLdcMachine::class)" />
            @error("code")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        @can("manage", InsLdcMachine::class)
            <div class="mt-6 flex justify-end">
                <x-primary-button type="submit">
                    {{ __("Simpan") }}
                </x-primary-button>
            </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
