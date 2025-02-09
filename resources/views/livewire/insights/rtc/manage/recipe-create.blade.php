<?php

use Livewire\Volt\Component;

use App\Models\InsRtcRecipe;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {

    public string $name;
    public string $og_rs;
    public float $std_min;
    public float $std_max;
    public float $std_mid;
    public float $scale;
    public float $pfc_min;
    public float $pfc_max;

    public function rules()
    {
        return [
            'name'      => ['required', 'min:1', 'max:50'],
            'og_rs'     => ['required', 'min:1', 'max:10'],
            'std_min'   => ['required', 'numeric', 'gt:0', 'lt:10'],
            'std_max'   => ['required', 'numeric', 'gt:0', 'lt:10'],
            'std_mid'   => ['required', 'numeric', 'gt:0', 'lt:10'],
            'scale'     => ['required', 'numeric', 'gt:0', 'lt:10'],
            'pfc_min'   => ['required', 'numeric', 'gt:0', 'lt:10'],
            'pfc_max'   => ['required', 'numeric', 'gt:0', 'lt:10'],
        ];
    }


    public function save()
    {
        $recipe = new InsRtcRecipe;
        Gate::authorize('manage', $recipe);

        $validated = $this->validate();
        $recipe->fill($validated);
        $recipe->save();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Resep dibuat') . '", { type: "success" })');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['name', 'og_rs', 'std_min', 'std_max', 'std_mid', 'scale', 'pfc_min', 'pfc_max']);
    }

};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Resep baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="grid grid-cols-3 gap-x-3">
            <div class="col-span-2 mt-6">
                <label for="recipe-name"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                <x-text-input id="recipe-name" wire:model="name" type="text" />
                @error('name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="recipe-og_rs"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('OG/RS') }}</label>
                <x-text-input id="recipe-og_rs" wire:model="og_rs" type="text" />
                @error('og_rs')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>
        <div class="grid grid-cols-3 gap-x-3">
            <div class="mt-6">
                <label for="recipe-std_min"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Min') }}</label>
                <x-text-input id="recipe-std_min" wire:model="std_min" type="number" step=".01" />
                @error('std_min')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="recipe-std_max"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Maks') }}</label>
                <x-text-input id="recipe-std_max" wire:model="std_max" type="number" step=".01" />
                @error('std_max')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="recipe-std_mid"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Mid') }}</label>
                <x-text-input id="recipe-std_mid" wire:model="std_mid" type="number" step=".01" />
                @error('std_mid')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>
        <div class="grid grid-cols-3 gap-x-3">
            <div class="mt-6">
                <label for="recipe-pfc_min"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('PFC Min') }}</label>
                <x-text-input id="recipe-pfc_min" wire:model="pfc_min" type="number" step=".01" />
                @error('pfc_min')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="recipe-pfc_max"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('PFC Max') }}</label>
                <x-text-input id="recipe-pfc_max" wire:model="pfc_max" type="number" step=".01" />
                @error('pfc_max')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="recipe-scale"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Scale') }}</label>
                <x-text-input id="recipe-scale" wire:model="scale" type="number" step=".01" />
                @error('scale')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
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
