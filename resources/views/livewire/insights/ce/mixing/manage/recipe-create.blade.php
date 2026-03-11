<?php

use App\Models\InvCeRecipe;
use App\Models\InvCeChemical;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public string $line = '';
    public string $model = '';
    public string $area = '';
    public int $chemical_id = 0;
    public int $hardener_id = 0;
    public string $hardener_ratio = '';
    public string $output_code = '';
    public string $potlife = '';
    public bool $is_active = true;

    // Additional settings
    public string $up_dev = '';
    public string $low_dev = '';
    public string $target_weight = '';

    public function rules(): array
    {
        return [
            'line'           => ['required', 'string', 'max:50'],
            'model'          => ['required', 'string', 'max:100'],
            'area'           => ['required', 'string', 'max:50'],
            'chemical_id'    => ['required', 'integer', 'gt:0', 'exists:inv_ce_chemicals,id'],
            'hardener_id'    => ['required', 'integer', 'gt:0', 'exists:inv_ce_chemicals,id'],
            'hardener_ratio' => ['required', 'numeric', 'min:0'],
            'output_code'    => ['required', 'string', 'max:100'],
            'potlife'        => ['required', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
            'up_dev'         => ['nullable', 'numeric', 'min:0'],
            'low_dev'        => ['nullable', 'numeric', 'min:0'],
            'target_weight'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function with(): array
    {
        return [
            'chemicals' => InvCeChemical::orderBy('name')->get(['id', 'name', 'item_code']),
        ];
    }

    public function save(): void
    {
        Gate::authorize('superuser');
        $validated = $this->validate();

        $validated['additional_settings'] = array_filter([
            'up_dev'        => $this->up_dev !== '' ? (float) $this->up_dev : null,
            'low_dev'       => $this->low_dev !== '' ? (float) $this->low_dev : null,
            'target_weight' => $this->target_weight !== '' ? (float) $this->target_weight : null,
        ], fn($v) => $v !== null) ?: null;

        unset($validated['up_dev'], $validated['low_dev'], $validated['target_weight']);

        InvCeRecipe::create($validated);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Resep dibuat') . '", { type: "success" })');
        $this->dispatch('updated');
        $this->customReset();
    }

    public function customReset(): void
    {
        $this->reset(['line', 'model', 'area', 'chemical_id', 'hardener_id', 'hardener_ratio', 'output_code', 'potlife', 'up_dev', 'low_dev', 'target_weight']);
        $this->is_active = true;
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Resep baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
            <div>
                <label for="recipe-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                <x-text-input id="recipe-line" wire:model="line" type="text" placeholder="L1" />
                @error('line') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
            </div>
            <div>
                <label for="recipe-model" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Model') }}</label>
                <x-text-input id="recipe-model" wire:model="model" type="text" placeholder="ABC-123" />
                @error('model') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
            </div>
            <div>
                <label for="recipe-area" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Area') }}</label>
                <x-text-input id="recipe-area" wire:model="area" type="text" placeholder="E" />
                @error('area') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
            </div>
            <div>
                <label for="recipe-output-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Output Code') }}</label>
                <x-text-input id="recipe-output-code" wire:model="output_code" type="text" placeholder="MIX-001" />
                @error('output_code') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
            </div>
        </div>

        <div class="mt-4">
            <label for="recipe-chemical" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Chemical Base (A)') }}</label>
            <select id="recipe-chemical" wire:model="chemical_id" class="w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                <option value="0">-- {{ __('Pilih chemical') }} --</option>
                @foreach($chemicals as $c)
                    <option value="{{ $c->id }}">{{ $c->item_code }} — {{ $c->name }}</option>
                @endforeach
            </select>
            @error('chemical_id') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
        </div>

        <div class="mt-4">
            <label for="recipe-hardener" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Hardener (B)') }}</label>
            <select id="recipe-hardener" wire:model="hardener_id" class="w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                <option value="0">-- {{ __('Pilih hardener') }} --</option>
                @foreach($chemicals as $c)
                    <option value="{{ $c->id }}">{{ $c->item_code }} — {{ $c->name }}</option>
                @endforeach
            </select>
            @error('hardener_id') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4 mt-4">
            <div>
                <label for="recipe-ratio" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Hardener Ratio (%)') }}</label>
                <x-text-input id="recipe-ratio" wire:model="hardener_ratio" type="number" step="0.01" placeholder="10.5" />
                @error('hardener_ratio') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
            </div>
            <div>
                <label for="recipe-potlife" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Potlife (hours)') }}</label>
                <x-text-input id="recipe-potlife" wire:model="potlife" type="number" step="0.1" placeholder="2.5" />
                @error('potlife') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
            </div>
        </div>

        <!-- Additional Settings -->
        <div class="mt-6">
            <h3 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-3">{{ __('Additional Settings') }}</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="recipe-up-dev" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Up Dev') }}</label>
                    <x-text-input id="recipe-up-dev" wire:model="up_dev" type="number" step="0.01" placeholder="0.5" />
                    @error('up_dev') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
                </div>
                <div>
                    <label for="recipe-low-dev" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Low Dev') }}</label>
                    <x-text-input id="recipe-low-dev" wire:model="low_dev" type="number" step="0.01" placeholder="0.5" />
                    @error('low_dev') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
                </div>
                <div>
                    <label for="recipe-target-weight" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Target Weight') }}</label>
                    <x-text-input id="recipe-target-weight" wire:model="target_weight" type="number" step="0.01" placeholder="3" />
                    @error('target_weight') <x-input-error messages="{{ $message }}" class="px-3 mt-2" /> @enderror
                </div>
            </div>
        </div>

        <div class="mt-4">
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_active" class="rounded border-neutral-300 dark:border-neutral-700 text-caldy-600 shadow-sm focus:ring-caldy-500">
                <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Aktif') }}</span>
            </label>
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">{{ __('Buat') }}</x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
