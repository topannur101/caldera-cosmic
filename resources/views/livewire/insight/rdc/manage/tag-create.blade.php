<?php

use Livewire\Volt\Component;
use App\Models\InsRdcTag;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {

    public string $name = '';

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:20', 'unique:ins_rdc_tags'],
        ];
    }

    public function save()
    {
        $tag = new InsRdcTag;
        Gate::authorize('manage', $tag);

        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        $tag->fill([
            'name' => $validated['name']
        ]);

        $tag->save();

        $this->js('$dispatch("close")');
        $this->js('notyfSuccess("' . __('Tag dibuat') . '")');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['name',]);
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Tag baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="tag-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="tag-name" wire:model="name" type="text" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  

        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>