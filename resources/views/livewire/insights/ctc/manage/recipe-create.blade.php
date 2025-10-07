<?php

use Livewire\Volt\Component;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public string $name = "";
    public string $component_model = "";
    public string $og_rs = "";
    public float $std_min = 0;
    public float $std_max = 0;
    public float $std_mid = 0;
    public float $scale = 1;
    public float $pfc_min = 0;
    public float $pfc_max = 0;
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
            "name.required" => "Nama resep harus diisi",
            "component_model.required" => "Model komponen harus diisi",
            "og_rs.required" => "OG/RS harus diisi",
        ];
    }

    public function save()
    {
        Gate::authorize("superuser");

        $validated = $this->validate();

        // Transform to uppercase for consistency
        $validated['component_model'] = strtoupper(trim($validated['component_model']));
        $validated['name'] = strtoupper(trim($validated['name']));
        $validated['og_rs'] = strtoupper(trim($validated['og_rs']));

        // Check for duplicate combination
        $exists = \App\Models\InsCtcRecipe::where('name', $validated['name'])
            ->where('component_model', $validated['component_model'])
            ->where('og_rs', $validated['og_rs'])
            ->exists();

        if ($exists) {
            $this->addError('name', 'Kombinasi Nama, Model Komponen, dan OG/RS sudah ada. Silakan ubah salah satunya.');
            return;
        }

        // Simpan ke database
        $recipe = new \App\Models\InsCtcRecipe();
        $recipe->fill($validated);
        $recipe->save();

        // Feedback
        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Resep dibuat") . '", { type: "success" })');
        $this->dispatch("updated");
        $this->customReset();
    }

    public function customReset()
    {
        $this->reset([
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
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Resep baru") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>

        {{-- Basic Information --}}
        <div class="mt-6">
            <label for="recipe-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama Resep") }}</label>
            <x-text-input id="recipe-name" wire:model="name" type="text" placeholder="Contoh: AF1 GS" />
            @error("name")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>

        {{-- Component Model - TEXT INPUT --}}
        <div class="mt-4">
            <label for="recipe-component-model" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Model Komponen") }}</label>
            <x-text-input id="recipe-component-model" wire:model="component_model" type="text" placeholder="BOTTOM, CENTER dll" />
            <p class="text-xs text-neutral-500 px-3 mt-1">{{ __("Contoh: BOTTOM, HEEL, MEDIAL") }}</p>
            @error("component_model")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>

        <div class="grid grid-cols-2 gap-x-3 mt-4">
            <div>
                <label for="recipe-og_rs" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("OG/RS") }}</label>
                <x-text-input id="recipe-og_rs" wire:model="og_rs" type="text" placeholder="011" />
                @error("og_rs")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="recipe-scale" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Scale") }}</label>
                <x-text-input id="recipe-scale" wire:model="scale" type="number" step=".01" placeholder="1.00" />
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
                    <x-text-input id="recipe-std_min" wire:model="std_min" type="number" step=".01" placeholder="0.00" />
                    @error("std_min")
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div>
                    <label for="recipe-std_max" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Maks") }}</label>
                    <x-text-input id="recipe-std_max" wire:model="std_max" type="number" step=".01" placeholder="0.00" />
                    @error("std_max")
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div>
                    <label for="recipe-std_mid" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Tengah") }}</label>
                    <x-text-input id="recipe-std_mid" wire:model="std_mid" type="number" step=".01" placeholder="0.00" />
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
                    <x-text-input id="recipe-pfc_min" wire:model="pfc_min" type="number" step=".01" placeholder="0.00" />
                    @error("pfc_min")
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div>
                    <label for="recipe-pfc_max" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Maks") }}</label>
                    <x-text-input id="recipe-pfc_max" wire:model="pfc_max" type="number" step=".01" placeholder="0.00" />
                    @error("pfc_max")
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>
        </div>

        {{-- Status --}}
        <div class="mt-4 px-3">
            <x-checkbox id="recipe-is_active" wire:model="is_active">
                {{ __("Resep aktif") }}
            </x-checkbox>
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