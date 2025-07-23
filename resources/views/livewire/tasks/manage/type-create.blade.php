<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Gate;
use App\Models\TskType;

new class extends Component {

    public string $name = '';
    public bool $is_active = true;

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:tsk_types'],
            'is_active' => ['boolean'],
        ];
    }

    public function save()
    {
        Gate::authorize('superuser');

        $this->validate();

        TskType::create([
            'name' => $this->name,
            'is_active' => $this->is_active,
        ]);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tipe dibuat') . '", { type: "success" })');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['name', 'is_active']);
        $this->is_active = true;
    }

};

?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Tipe baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="grid grid-cols-1 gap-y-3 mt-6">
            <div>
                <label for="type-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                <x-text-input id="type-name" wire:model="name" type="text" />
                @error('name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 gap-y-3 mt-6">
            <x-checkbox id="new-is-active" wire:model="is_active">{{ __('Aktif') }}</x-checkbox>
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