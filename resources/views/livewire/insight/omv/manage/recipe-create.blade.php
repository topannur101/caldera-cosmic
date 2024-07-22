<?php

use Livewire\Volt\Component;
use App\Models\InsOmvRecipe;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public string $name = '';
    public string $capture_points = '';
    public array $steps = [['description' => '', 'duration' => '']];

    public function rules()
    {
        return [
            'name' => ['required', 'min:1', 'max:140'],
            'capture_points' => ['nullable', 'string'],
            'steps' => ['required', 'array', 'min:1', 'max:6'],
            'steps.*.description' => ['required', 'string', 'max:480'],
            'steps.*.duration' => ['required', 'integer', 'min:1', 'max:7200'],
        ];
    }

    public function save()
    {
        $recipe = new InsOmvRecipe;
        Gate::authorize('manage', $recipe);

        $validated = $this->validate();

        // Process capture points
        $capture_points = array_map('intval', array_filter(explode(',', $validated['capture_points'])));
        $capture_points = array_unique($capture_points);
        $capture_points = array_filter($capture_points, fn($point) => $point > 0 && $point <= 7200);
        sort($capture_points);
        
        if (count($capture_points) > 5) {
            $capture_points = array_slice($capture_points, 0, 5);
        }

        // Ensure 'duration' in steps is an integer
        $steps = array_map(function($step) {
            return [
                'description' => $step['description'],
                'duration' => (int)$step['duration'],
            ];
        }, $validated['steps']);

        $recipe->fill([
            'name' => $validated['name'],
            'capture_points' => json_encode($capture_points),
            'steps' => json_encode($validated['steps']),
        ]);

        $recipe->save();

        $this->js('$dispatch("close")');
        $this->js('notyfSuccess("' . __('Resep dibuat') . '")');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['name', 'capture_points', 'steps']);
        $this->steps = [['description' => '', 'duration' => '']];
    }

    public function addStep()
    {
        if (count($this->steps) < 5) {
            $this->steps[] = ['description' => '', 'duration' => ''];
        }
    }

    public function removeStep($index)
    {
        if (count($this->steps) > 1) {
            unset($this->steps[$index]);
            $this->steps = array_values($this->steps);
        }
    }

    public function moveStep($fromIndex, $toIndex)
    {
        if ($fromIndex !== $toIndex && $fromIndex >= 0 && $toIndex >= 0 && $fromIndex < count($this->steps) && $toIndex < count($this->steps)) {
            $step = $this->steps[$fromIndex];
            array_splice($this->steps, $fromIndex, 1);
            array_splice($this->steps, $toIndex, 0, [$step]);
        }
    }
};
?>

<div x-data="{ 
    draggingIndex: null,
    dragoverIndex: null,
    isDragging: false,
    startDrag(index) {
        this.draggingIndex = index;
        this.isDragging = true;
    },
    endDrag() {
        this.draggingIndex = null;
        this.dragoverIndex = null;
        this.isDragging = false;
    },
    onDragOver(index) {
        if (this.draggingIndex !== null && this.draggingIndex !== index) {
            this.dragoverIndex = index;
        }
    },
    onDrop(index) {
        if (this.draggingIndex !== null) {
            $wire.moveStep(this.draggingIndex, index);
            this.endDrag();
        }
    }
}">
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Resep baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="recipe-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="recipe-name" wire:model="name" type="text" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  
        <div class="mt-6">
            <label for="recipe-capture-points" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Titik tangkap foto') }}</label>
            <x-text-input id="recipe-capture-points" wire:model="capture_points" type="text" placeholder="contoh: 30, 90, 120" />
            @error('capture_points')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>     
        <div class="mt-6">
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Langkah-langkah') }}</label>
            @foreach($steps as $index => $step)
                <div class="grid grid-cols-4 gap-y-2 gap-x-2 mt-2" 
                     draggable="true"
                     x-on:dragstart="startDrag({{ $index }})"
                     x-on:dragend="endDrag"
                     x-on:dragover.prevent="onDragOver({{ $index }})"
                     x-on:drop.prevent="onDrop({{ $index }})"
                     :class="{ 'opacity-50': draggingIndex === {{ $index }}, 'border-t-2 border-blue-500': dragoverIndex === {{ $index }} }">
                    <div class="col-span-3">
                        <x-text-input type="text" wire:model="steps.{{ $index }}.description" placeholder="{{ __('Deskripsi')}}" />
                        @error("steps.{$index}.description")
                            <x-input-error messages="{{ $message }}" class="mt-2" />
                        @enderror
                    </div>    
                    <div class="flex gap-x-3">
                        <div>
                            <x-text-input type="number" wire:model="steps.{{ $index }}.duration" placeholder="{{ __('Detik') }}" />
                            @error("steps.{$index}.duration")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                        <x-text-button type="button" wire:click="removeStep({{ $index }})"><i class="fa fa-times"></i></x-text-button>
                    </div>
                </div>
            @endforeach
        </div>
        <x-secondary-button type="button" class="mt-6" type="button" wire:click="addStep">{{ __('Tambah langkah')}}</x-secondary-button>
        <div class="mt-6 flex justify-end items-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>