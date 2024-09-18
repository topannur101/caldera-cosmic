<?php

use Livewire\Volt\Component;
use App\Models\ShMod;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {

    public int $number;
    public string $name = '';
    public bool $is_active = false;

    public function rules()
    {
        return [
            'name'      => ['required', 'string', 'min:1', 'max:50'],
            'is_active' => ['required', 'boolean']
        ];
    }

    public function save()
    {
        $mod = new ShMod;
        Gate::authorize('manage', $mod);

        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        $mod->fill([
            'name'      => $validated['name'],
            'is_active' => $validated['is_active']
        ]);

        $mod->save();

        $this->js('$dispatch("close")');
        $this->js('notyfSuccess("' . __('Model dibuat') . '")');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['name', 'is_active']);
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Model baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div> 
        <div class="mt-6">
            <label for="mod-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="mod-name" wire:model="name" type="text" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  

        <div class="mt-6 flex justify-between">
            <div x-data="{ is_active: @entangle('is_active') }">
                <x-toggle x-model="is_active"><span x-show="is_active">{{ __('Aktif') }}</span><span x-show="!is_active">{{ __('Nonaktif') }}</span></x-toggle>
            </div>
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>