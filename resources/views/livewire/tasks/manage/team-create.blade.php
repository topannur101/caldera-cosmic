<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Gate;
use App\Models\TskTeam;

new class extends Component {

    public string $name = '';
    public string $short_name = '';
    public string $desc = '';
    public bool $is_active = true;

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:10', 'unique:tsk_teams'],
            'desc' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    public function save()
    {
        Gate::authorize('superuser');

        $this->validate();

        TskTeam::create([
            'name' => $this->name,
            'short_name' => $this->short_name,
            'desc' => $this->desc,
            'is_active' => $this->is_active,
        ]);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tim dibuat') . '", { type: "success" })');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['name', 'short_name', 'desc', 'is_active']);
        $this->is_active = true;
    }

};

?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Tim baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="grid grid-cols-1 gap-y-3 mt-6">
            <div>
                <label for="team-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama Tim') }}</label>
                <x-text-input id="team-name" wire:model="name" type="text" />
                @error('name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="team-short-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama Singkat') }}</label>
                <x-text-input id="team-short-name" wire:model="short_name" type="text" maxlength="10" placeholder="DGT" />
                @error('short_name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="team-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Deskripsi') }}</label>
                <textarea id="team-desc" wire:model="desc" rows="3" class="block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm" placeholder="{{ __('Deskripsi tim dan tugasnya...') }}"></textarea>
                @error('desc')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 gap-y-3 mt-6">
            <x-checkbox id="new-is-active" wire:model="is_active">{{ __('Tim Aktif') }}</x-checkbox>
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