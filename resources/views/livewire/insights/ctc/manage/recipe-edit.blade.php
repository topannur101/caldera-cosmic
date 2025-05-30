<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public int $id;
    public string $name;
    public string $og_rs;
    public float $std_min;
    public float $std_max;
    public float $std_mid;
    public float $scale;
    public float $pfc_min;
    public float $pfc_max;
    
    // New CTC-specific fields
    public array $recommended_for_models = [];
    public int $priority = 0;
    public bool $is_active = true;

    public function rules()
    {
        return [
            'name' => ['required', 'min:1', 'max:50'],
            'og_rs' => ['required', 'min:1', 'max:10'],
            'std_min' => ['required', 'numeric', 'gt:0', 'lt:10'],
            'std_max' => ['required', 'numeric', 'gt:0', 'lt:10'],
            'std_mid' => ['required', 'numeric', 'gt:0', 'lt:10'],
            'scale' => ['required', 'numeric', 'gt:0', 'lt:10'],
            'pfc_min' => ['required', 'numeric', 'gt:0', 'lt:10'],
            'pfc_max' => ['required', 'numeric', 'gt:0', 'lt:10'],
            'recommended_for_models' => ['array'],
            'recommended_for_models.*' => ['string', 'max:20'],
            'priority' => ['integer', 'min:0', 'max:10'],
            'is_active' => ['boolean'],
        ];
    }

    #[On('recipe-edit')]
    public function loadRecipe(int $id)
    {
        // TODO: Replace with actual InsCtcRecipe model when backend is ready
        // $recipe = InsCtcRecipe::find($id);
        
        // Mock data for development
        $mockRecipes = [
            1 => [
                'id' => 1,
                'name' => 'AF1 GS (ONE COLOR)',
                'og_rs' => '1',
                'std_min' => 3.0,
                'std_max' => 3.1,
                'std_mid' => 3.05,
                'scale' => 1.0,
                'pfc_min' => 3.4,
                'pfc_max' => 3.6,
                'recommended_for_models' => ['AF1'],
                'priority' => 5,
                'is_active' => true
            ],
            2 => [
                'id' => 2,
                'name' => 'AF1 WS (TWO COLOR)',
                'og_rs' => '7',
                'std_min' => 3.0,
                'std_max' => 3.1,
                'std_mid' => 3.05,
                'scale' => 1.0,
                'pfc_min' => 3.4,
                'pfc_max' => 3.6,
                'recommended_for_models' => ['AF1'],
                'priority' => 3,
                'is_active' => true
            ],
            3 => [
                'id' => 3,
                'name' => 'AM 270 (CENTER)',
                'og_rs' => '1',
                'std_min' => 2.7,
                'std_max' => 2.9,
                'std_mid' => 2.8,
                'scale' => 1.0,
                'pfc_min' => 2.7,
                'pfc_max' => 2.9,
                'recommended_for_models' => ['AM270'],
                'priority' => 8,
                'is_active' => true
            ]
        ];

        $recipe = $mockRecipes[$id] ?? null;
        
        if ($recipe) {
            $this->id = $recipe['id'];
            $this->name = $recipe['name'];
            $this->og_rs = $recipe['og_rs'];
            $this->std_min = $recipe['std_min'];
            $this->std_max = $recipe['std_max'];
            $this->std_mid = $recipe['std_mid'];
            $this->scale = $recipe['scale'];
            $this->pfc_min = $recipe['pfc_min'];
            $this->pfc_max = $recipe['pfc_max'];
            $this->recommended_for_models = $recipe['recommended_for_models'];
            $this->priority = $recipe['priority'];
            $this->is_active = $recipe['is_active'];
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        // TODO: Replace with actual InsCtcRecipe model when backend is ready
        // $recipe = InsCtcRecipe::find($this->id);
        // $validated = $this->validate();

        // if ($recipe) {
        //     Gate::authorize('manage', $recipe);
        //     $recipe->update($validated);
        //     // ... success handling
        // }

        $this->validate();
        
        // Mock successful update for development
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Resep diperbarui') . '", { type: "success" })');
        $this->dispatch('updated');
    }

    public function delete()
    {
        // TODO: Replace with actual InsCtcRecipe model when backend is ready
        // $recipe = InsCtcRecipe::find($this->id);

        // if ($recipe) {
        //     Gate::authorize('manage', $recipe);
        //     $recipe->delete();
        //     // ... success handling
        // }

        // Mock successful deletion for development
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Resep dihapus') . '", { type: "success" })');
        $this->dispatch('updated');
        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['id', 'name', 'og_rs', 'std_min', 'std_max', 'std_mid', 'scale', 'pfc_min', 'pfc_max', 'recommended_for_models', 'priority', 'is_active']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }

    public function with(): array
    {
        return [
            'availableModels' => ['AF1', 'AM270', 'AM95', 'ALPHA', 'CBR', 'DWS', 'PEG', 'QUEST'],
        ];
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Resep') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mb-6">
            <div class="grid grid-cols-3 gap-x-3">
                <div class="col-span-2 mt-6">
                    <label for="recipe-name"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                    <x-text-input id="recipe-name" wire:model="name" type="text" :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)" />
                    @error('name')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-og_rs"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('OG/RS') }}</label>
                    <x-text-input id="recipe-og_rs" wire:model="og_rs" type="text" :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)" />
                    @error('og_rs')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>
            <div class="grid grid-cols-3 gap-x-3">
                <div class="mt-6">
                    <label for="recipe-std_min"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Min') }}</label>
                    <x-text-input id="recipe-std_min" wire:model="std_min" type="number" step=".01" :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)" />
                    @error('std_min')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-std_max"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Maks') }}</label>
                    <x-text-input id="recipe-std_max" wire:model="std_max" type="number" step=".01" :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)" />
                    @error('std_max')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-std_mid"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Mid') }}</label>
                    <x-text-input id="recipe-std_mid" wire:model="std_mid" type="number" step=".01" :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)" />
                    @error('std_mid')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>
            <div class="grid grid-cols-3 gap-x-3">
                <div class="mt-6">
                    <label for="recipe-pfc_min"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('PFC Min') }}</label>
                    <x-text-input id="recipe-pfc_min" wire:model="pfc_min" type="number" step=".01" :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)" />
                    @error('pfc_min')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-pfc_max"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('PFC Max') }}</label>
                    <x-text-input id="recipe-pfc_max" wire:model="pfc_max" type="number" step=".01" :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)" />
                    @error('pfc_max')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-scale"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Scale') }}</label>
                    <x-text-input id="recipe-scale" wire:model="scale" type="number" step=".01" :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)" />
                    @error('scale')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>

            {{-- CTC-specific fields --}}
            <div class="mt-6 pt-6 border-t border-neutral-200 dark:border-neutral-700">
                <h3 class="text-md font-medium text-neutral-900 dark:text-neutral-100 mb-4">
                    {{ __('Pengaturan Rekomendasi') }}
                </h3>
                
                <div class="grid grid-cols-2 gap-x-3">
                    <div>
                        <label for="recipe-models"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Model yang Direkomendasikan') }}</label>
                        <div class="grid grid-cols-2 gap-2 p-3 border border-neutral-200 dark:border-neutral-700 rounded-md">
                            @foreach($availableModels as $model)
                                <x-checkbox 
                                    id="model-{{ $model }}" 
                                    wire:model="recommended_for_models" 
                                    value="{{ $model }}"
                                    :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)">
                                    {{ $model }}
                                </x-checkbox>
                            @endforeach
                        </div>
                        @error('recommended_for_models')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    
                    <div>
                        <div class="mb-4">
                            <label for="recipe-priority"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Prioritas') }}</label>
                            <x-select id="recipe-priority" wire:model="priority" :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)">
                                @for($i = 0; $i <= 10; $i++)
                                    <option value="{{ $i }}">{{ $i }} {{ $i === 0 ? '(Terendah)' : ($i === 10 ? '(Tertinggi)' : '') }}</option>
                                @endfor
                            </x-select>
                            @error('priority')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        
                        <div>
                            <x-checkbox 
                                id="recipe-is_active" 
                                wire:model="is_active"
                                :disabled="Gate::denies('manage', \App\Models\InsRtcRecipe::class)">
                                {{ __('Resep Aktif') }}
                            </x-checkbox>
                            @error('is_active')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        @can('manage', \App\Models\InsRtcRecipe::class)
        <div class="flex justify-between items-end">
            <div>
                <x-text-button type="button" class="uppercase text-xs text-red-500" wire:click="delete"
                    wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}">
                    {{ __('Hapus') }}
                </x-text-button>
            </div>
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>