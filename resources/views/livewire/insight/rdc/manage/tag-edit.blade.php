<?php

use Livewire\Volt\Component;

use App\Models\InsRdcTag;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    
    public int $id;

    public string $name = '';

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:20', Rule::unique('ins_rdc_tags', 'name')->ignore($this->id ?? null)]
        ];
    }

    #[On('tag-edit')]
    public function loadTag(int $id)
    {
        $tag = InsRdcTag::find($id);
        if ($tag) {
            $this->id               = $tag->id;
            $this->name             = $tag->name;
        
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        $tag = InsRdcTag::find($this->id);

        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        if($tag) {
            Gate::authorize('manage', $tag);

            $tag->update([
                'name' => $validated['name'],
            ]);

            $this->js('$dispatch("close")');
            $this->js('notyfSuccess("' . __('Tag diperbarui') . '")');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset(['name']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('notyfError("' . __('Tidak ditemukan') . '")');
        $this->dispatch('updated');
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Tag ') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div> 
        <div class="mt-6">
            <label for="tag-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="tag-name" wire:model="name" type="text" :disabled="Gate::denies('manage', InsRdcTag::class)" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>    
        
        @can('manage', InsRdcTag::class)
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
