<?php

use Livewire\Volt\Component;
use App\Models\InsRdcStd;
use App\Models\InsRdcTag;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {

    public string $machine;
    public float $mcs;
    public string $tag_id;
    public float $tc10;
    public float $tc90;

    public function rules()
    {
        return [
            'machine'   => ['required', 'string', 'min:1', 'max:20'],
            'mcs'       => ['required', 'integer', 'min:1', 'max:999'],
            'tag_id'    => ['nullable', 'exists:ins_rdc_tags,id'],
            'tc10'      => ['required', 'numeric', 'gt:0', 'lt:999'],
            'tc90'      => ['required', 'numeric', 'gt:0', 'lt:999'],
        ];
    }

    public function save()
    {
        $this->machine  = strtoupper(trim($this->machine));
        $validated = $this->validate();

        $std = InsRdcStd::firstOrNew(
            [
                'machine'           => $validated['machine'],
                'mcs'               => $validated['mcs'],
                'ins_rdc_tag_id'    => $validated['tag_id'],       
            ]
        );
        Gate::authorize('manage', $std);

        $std->fill([
            'ins_rdc_tag_id'    => $validated['tag_id'],  
            'tc10'      => $validated['tc10'],
            'tc90'      => $validated['tc90'],
        ]);

        $std->save();

        $this->js('$dispatch("close")');
        $this->js('notyfSuccess("' . __('Standar dibuat') . '")');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function with(): array
    {
        $tags = InsRdcTag::orderBy('name')->get();
        return [
            'tags' => InsRdcTag::orderBy('name')->get()
        ];
    }

    public function customReset()
    {
        $this->reset(['machine', 'mcs', 'tag_id', 'tc10', 'tc90']);
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Standar baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="std-machine" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mesin') }}</label>
            <x-text-input id="std-machine" wire:model="machine" type="text" />
            @error('machine')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="grid grid-cols-2 gap-x-3">
            <div class="mt-6">
                <label for="std-mcs" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('MCS') }}</label>
                <x-text-input id="std-mcs" wire:model="mcs" type="number" step="1" />
                @error('mcs')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="std-tag_id" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tag') }}</label>
                <x-select class="w-full" id="std-tag_id" wire:model="tag_id">
                    <option value=""></option>
                    @foreach($tags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </x-select>
                @error('tag_id')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>
        <div class="grid grid-cols-2 gap-x-3">
            <div class="mt-6">
                <label for="test-tc10"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC10') }}</label>
                <x-text-input id="test-tc10" wire:model="tc10" type="number" step=".01" />
                @error('tc10')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="test-tc90"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC90') }}</label>
                <x-text-input id="test-tc90" wire:model="tc90" type="number" step=".01" />
                @error('tc90')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
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