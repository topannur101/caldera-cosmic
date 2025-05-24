<?php

use Livewire\Volt\Component;
use App\Models\InvArea;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    
    public int $id;

    public string $name = '';

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:20'],        ];
    }

    #[On('area-edit')]
    public function loadArea(int $id)
    {
        $area = InvArea::find($id);
        if ($area) {
            $this->id       = $area->id;
            $this->name     = $area->name;
        
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        $area = InvArea::find($this->id);
        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        if($area) {
            Gate::authorize('manage', $area);

            $area->update([
                'name' => $validated['name'],
            ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Area diperbarui') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset(['code', 'name']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Area ') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="area-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="area-name" wire:model="name" type="text" :disabled="Gate::denies('manage', InvArea::class)" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>    
        
        @can('manage', InvArea::class)
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
