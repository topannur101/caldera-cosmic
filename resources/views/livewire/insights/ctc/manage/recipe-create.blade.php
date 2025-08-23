<?php

use Livewire\Volt\Component;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public string $name = "";
    public string $og_rs = "";
    public float $std_min = 0;
    public float $std_max = 0;
    public float $std_mid = 0;
    public float $scale = 1;
    public float $pfc_min = 0;
    public float $pfc_max = 0;
    public int $priority = 1;
    public array $recommended_for_models = [];
    public bool $is_active = true;

    public function rules()
    {
        return [
            "name" => ["required", "min:1", "max:50"],
            "og_rs" => ["required", "min:1", "max:10"],
            "std_min" => ["required", "numeric", "gt:0", "lt:10"],
            "std_max" => ["required", "numeric", "gt:0", "lt:10", "gt:std_min"],
            "std_mid" => ["required", "numeric", "gt:0", "lt:10", "gte:std_min", "lte:std_max"],
            "scale" => ["required", "numeric", "gt:0", "lt:10"],
            "pfc_min" => ["required", "numeric", "gt:0", "lt:10"],
            "pfc_max" => ["required", "numeric", "gt:0", "lt:10", "gt:pfc_min"],
            "priority" => ["required", "integer", "min:1", "max:10"],
            "recommended_for_models" => ["array"],
            "recommended_for_models.*" => ["string", "max:20"],
            "is_active" => ["boolean"],
        ];
    }

    public function with(): array
    {
        return [
            "available_models" => ["AF1", "AM270", "AM95", "ALPHA", "CBR", "DWS", "PEG"],
        ];
    }

    public function save()
    {
        Gate::authorize("superuser");

        $validated = $this->validate();

        // TODO: Replace with actual InsCtcRecipe model when backend is ready
        // $recipe = new InsCtcRecipe;
        // $recipe->fill($validated);
        // $recipe->recommended_for_models = json_encode($this->recommended_for_models);
        // $recipe->save();

        // Mock successful creation for development
        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Resep dibuat") . '", { type: "success" })');
        $this->dispatch("updated");

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(["name", "og_rs", "std_min", "std_max", "std_mid", "scale", "pfc_min", "pfc_max", "priority", "recommended_for_models", "is_active"]);
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
        <div class="grid grid-cols-1 gap-x-3 mt-6">
            <div>
                <label for="recipe-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama") }}</label>
                <x-text-input id="recipe-name" wire:model="name" type="text" />
                @error("name")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-3 gap-x-3 mt-4">
            <div>
                <label for="recipe-og_rs" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("OG/RS") }}</label>
                <x-text-input id="recipe-og_rs" wire:model="og_rs" type="text" />
                @error("og_rs")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="recipe-priority" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Prioritas") }}</label>
                <x-text-input id="recipe-priority" wire:model="priority" type="number" min="1" max="10" />
                @error("priority")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="recipe-scale" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Scale") }}</label>
                <x-text-input id="recipe-scale" wire:model="scale" type="number" step=".01" />
                @error("scale")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>

        {{-- Standard Values --}}
        <div class="grid grid-cols-3 gap-x-3 mt-4">
            <div>
                <label for="recipe-std_min" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Std Min") }}</label>
                <x-text-input id="recipe-std_min" wire:model="std_min" type="number" step=".01" />
                @error("std_min")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="recipe-std_max" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Std Maks") }}</label>
                <x-text-input id="recipe-std_max" wire:model="std_max" type="number" step=".01" />
                @error("std_max")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="recipe-std_mid" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Std Mid") }}</label>
                <x-text-input id="recipe-std_mid" wire:model="std_mid" type="number" step=".01" />
                @error("std_mid")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>

        {{-- PFC Values --}}
        <div class="grid grid-cols-2 gap-x-3 mt-4">
            <div>
                <label for="recipe-pfc_min" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("PFC Min") }}</label>
                <x-text-input id="recipe-pfc_min" wire:model="pfc_min" type="number" step=".01" />
                @error("pfc_min")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="recipe-pfc_max" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("PFC Max") }}</label>
                <x-text-input id="recipe-pfc_max" wire:model="pfc_max" type="number" step=".01" />
                @error("pfc_max")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>

        {{-- Model Recommendations --}}
        <div class="mt-4">
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Direkomendasikan untuk Model") }}</label>
            <div class="grid grid-cols-3 gap-2 px-3">
                @foreach ($available_models as $model)
                    <x-checkbox id="model-{{ $model }}" wire:model="recommended_for_models" value="{{ $model }}">
                        {{ $model }}
                    </x-checkbox>
                @endforeach
            </div>
            @error("recommended_for_models")
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>

        {{-- Status --}}
        <div class="mt-4">
            <div class="px-3">
                <x-checkbox id="recipe-is_active" wire:model="is_active">
                    {{ __("Resep aktif") }}
                </x-checkbox>
            </div>
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
