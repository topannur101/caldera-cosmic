<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public int $id;
    public string $name;
    public string $component_model;
    public string $og_rs;
    public float $std_min;
    public float $std_max;
    public float $std_mid;
    public float $scale;
    public float $pfc_min;
    public float $pfc_max;
    public bool $is_active = true;

    public function rules()
    {
        return [
            "name" => ["required", "min:1", "max:100"],
            "component_model" => ["required", "string", "max:100"],
            "og_rs" => ["required", "min:1", "max:10"],
            "std_min" => ["required", "numeric", "gt:0", "lt:10"],
            "std_max" => ["required", "numeric", "gt:0", "lt:10", "gt:std_min"],
            "std_mid" => ["required", "numeric", "gt:0", "lt:10", "gte:std_min", "lte:std_max"],
            "scale" => ["required", "numeric", "gt:0", "lt:10"],
            "pfc_min" => ["required", "numeric", "gt:0", "lt:10"],
            "pfc_max" => ["required", "numeric", "gt:0", "lt:10", "gt:pfc_min"],
            "is_active" => ["boolean"],
        ];
    }

    public function messages()
    {
        return [
            "name.required" => "Nama model harus diisi",
            "component_model.required" => "Model komponen harus diisi",
            "og_rs.required" => "OG/RS harus diisi",
        ];
    }

    #[On("recipe-edit")]
    public function loadRecipe(int $id)
    {
        $recipe = \App\Models\InsCtcRecipe::find($id);

        if ($recipe) {
            $this->id = $recipe->id;
            $this->name = $recipe->name;
            $this->component_model = $recipe->component_model ?? "";
            $this->og_rs = $recipe->og_rs;
            $this->std_min = $recipe->std_min;
            $this->std_max = $recipe->std_max;
            $this->std_mid = $recipe->std_mid;
            $this->scale = $recipe->scale;
            $this->pfc_min = $recipe->pfc_min;
            $this->pfc_max = $recipe->pfc_max;
            $this->is_active = $recipe->is_active;
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        Gate::authorize('superuser');

        $validated = $this->validate();
        
        // Transform to uppercase for consistency
        $validated['component_model'] = strtoupper(trim($validated['component_model']));
        $validated['name'] = strtoupper(trim($validated['name']));
        $validated['og_rs'] = strtoupper(trim($validated['og_rs']));

        // Check for duplicate combination (exclude current record)
        $exists = \App\Models\InsCtcRecipe::where('name', $validated['name'])
            ->where('component_model', $validated['component_model'])
            ->where('og_rs', $validated['og_rs'])
            ->where('id', '!=', $this->id)
            ->exists();

        if ($exists) {
            $this->addError('name', 'Kombinasi Nama, Model Komponen, dan OG/RS sudah ada. Silakan ubah salah satunya.');
            return;
        }

        $recipe = \App\Models\InsCtcRecipe::findOrFail($this->id);
        $recipe->update($validated);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Resep diperbarui") . '", { type: "success" })');
        $this->dispatch("updated");
    }

    public function delete()
    {
        Gate::authorize('superuser');

        $recipe = \App\Models\InsCtcRecipe::findOrFail($this->id);
        $recipe->delete();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Resep dihapus") . '", { type: "success" })');
        $this->dispatch("updated");
        $this->customReset();
    }

    public function customReset()
    {
        $this->reset([
            "id", 
            "name", 
            "component_model",
            "og_rs", 
            "std_min", 
            "std_max", 
            "std_mid", 
            "scale", 
            "pfc_min", 
            "pfc_max", 
            "is_active"
        ]);
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
                {{ __("Resep") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        
        <div class="mb-6">
            <div class="mt-6">
                <label for="recipe-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama Model") }}</label>
                <x-text-input id="recipe-name" wire:model="name" type="text" :disabled="Gate::denies('superuser')" />
                @error("name")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>

            {{-- Component Model - TEXT INPUT --}}
            <div class="mt-4">
                <label for="recipe-component-model" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Model Komponen") }}</label>
                <x-text-input id="recipe-component-model" wire:model="component_model" type="text" :disabled="Gate::denies('superuser')" placeholder="BOTTOM, CENTER dll" />
                <p class="text-xs text-neutral-500 px-3 mt-1">{{ __("Contoh: BOTTOM, HEEL, MEDIAL") }}</p>
                @error("component_model")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-x-3 mt-4">
                <div>
                    <label for="recipe-og_rs" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("OG/RS") }}</label>
                    <x-text-input id="recipe-og_rs" wire:model="og_rs" type="text" :disabled="Gate::denies('superuser')" />
                    @error("og_rs")
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div>
                    <label for="recipe-scale" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Scale") }}</label>
                    <x-text-input id="recipe-scale" wire:model="scale" type="number" step=".01" :disabled="Gate::denies('superuser')" />
                    @error("scale")
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>

            {{-- Standard Values --}}
            <div class="mt-4">
                <h3 class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2 px-3">{{ __("Standar Ketebalan") }}</h3>
                <div class="grid grid-cols-3 gap-x-3">
                    <div>
                        <label for="recipe-std_min" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Min") }}</label>
                        <x-text-input id="recipe-std_min" wire:model="std_min" type="number" step=".01" :disabled="Gate::denies('superuser')" />
                        @error("std_min")
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div>
                        <label for="recipe-std_max" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Maks") }}</label>
                        <x-text-input id="recipe-std_max" wire:model="std_max" type="number" step=".01" :disabled="Gate::denies('superuser')" />
                        @error("std_max")
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div>
                        <label for="recipe-std_mid" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Tengah") }}</label>
                        <x-text-input id="recipe-std_mid" wire:model="std_mid" type="number" step=".01" :disabled="Gate::denies('superuser')" />
                        @error("std_mid")
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                </div>
            </div>

            {{-- PFC Values --}}
            <div class="mt-4">
                <h3 class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2 px-3">{{ __("PFC (Pre-Final Check)") }}</h3>
                <div class="grid grid-cols-2 gap-x-3">
                    <div>
                        <label for="recipe-pfc_min" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Min") }}</label>
                        <x-text-input id="recipe-pfc_min" wire:model="pfc_min" type="number" step=".01" :disabled="Gate::denies('superuser')" />
                        @error("pfc_min")
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div>
                        <label for="recipe-pfc_max" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Maks") }}</label>
                        <x-text-input id="recipe-pfc_max" wire:model="pfc_max" type="number" step=".01" :disabled="Gate::denies('superuser')" />
                        @error("pfc_max")
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Status --}}
            <div class="mt-4 px-3">
                <x-checkbox id="recipe-is_active" wire:model="is_active" :disabled="Gate::denies('superuser')">
                    {{ __("Resep Aktif") }}
                </x-checkbox>
                @error("is_active")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>

        @can("superuser")
            <div class="flex justify-between items-end">
                <div>
                    <x-text-button
                        type="button"
                        class="uppercase text-xs text-red-500"
                        wire:click="delete"
                        wire:confirm="{{ __('Apakah yakin resep akan dihapus. Lanjutkan?') }}"
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