<?php

use Livewire\Volt\Component;

use App\Models\InsOmvRecipe;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    
    public int $id;

    public string $name;
    public string $capture_points = '';
    public array $steps = [['description' => '', 'duration' => '']];

    public function rules()
    {
        return [
            'name'                  => ['required', 'min:1', 'max:140', Rule::unique('ins_omv_recipes', 'name')->ignore($this->id ?? null)],
            'capture_points'        => ['nullable', 'string'],
            'steps'                 => ['required', 'array', 'min:1', 'max:6'],
            'steps.*.description'   => ['required', 'string', 'max:480'],
            'steps.*.duration'      => ['required', 'integer', 'min:1', 'max:7200'],
        ];
    }

    #[On('recipe-edit')]
    public function loadRecipe(int $id)
    {
        $recipe = InsOmvRecipe::find($id);
        if ($recipe) {
            $this->id               = $recipe->id;
            $this->name             = $recipe->name;
            $this->capture_points   = '';
            $this->steps            = json_decode($recipe->steps, true);
        
            $cps = json_decode($recipe->capture_points, true);

            if (is_array($cps)) {
                $this->capture_points   = implode(', ', $cps);
            } 
            
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        $recipe = InsOmvRecipe::find($this->id);

        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        if($recipe) {
            Gate::authorize('manage', $recipe);

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

            $recipe->update([
                'name' => $validated['name'],
                'capture_points' => json_encode($capture_points),
                'steps' => json_encode($steps),
            ]);

            $this->js('$dispatch("close")');
            $this->js('notyfSuccess("' . __('Resep diperbarui') . '")');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }

        // $recipe = new InsOmvRecipe;
        // Gate::authorize('manage', $recipe);

        // $this->name = strtoupper(trim($this->name));
        // $validated = $this->validate();

        // // Process capture points
        // $capture_points = array_map('intval', array_filter(explode(',', $validated['capture_points'])));
        // $capture_points = array_unique($capture_points);
        // $capture_points = array_filter($capture_points, fn($point) => $point > 0 && $point <= 7200);
        // sort($capture_points);
        
        // if (count($capture_points) > 5) {
        //     $capture_points = array_slice($capture_points, 0, 5);
        // }

        // // Ensure 'duration' in steps is an integer
        // $steps = array_map(function($step) {
        //     return [
        //         'description' => $step['description'],
        //         'duration' => (int)$step['duration'],
        //     ];
        // }, $validated['steps']);

        // $recipe->fill([
        //     'name' => $validated['name'],
        //     'capture_points' => json_encode($capture_points),
        //     'steps' => json_encode($steps),
        // ]);

        // $recipe->save();

        // $this->js('$dispatch("close")');
        // $this->js('notyfSuccess("' . __('Resep dibuat') . '")');
        // $this->dispatch('updated');

        // $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['id', 'name', 'og_rs', 'std_min', 'std_max', 'std_mid', 'scale', 'pfc_min', 'pfc_max']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('notyfError("' . __('Tidak ditemukan') . '")');
        $this->dispatch('updated');
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
                {{ __('Resep ') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="recipe-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="recipe-name" wire:model="name" type="text" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  
        <div class="mt-6">
            <label for="recipe-capture-points" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Titik foto') }}</label>
            <x-text-input id="recipe-capture-points" wire:model="capture_points" type="text" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
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
                        <x-text-input type="text" wire:model="steps.{{ $index }}.description" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                        @error("steps.{$index}.description")
                            <x-input-error messages="{{ $message }}" class="mt-2" />
                        @enderror
                    </div>    
                    <div class="flex gap-x-3">
                        <div>
                            <x-text-input type="number" wire:model="steps.{{ $index }}.duration" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                            @error("steps.{$index}.duration")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                        @can('manage', InsOmvRecipe::class)
                            <x-text-button type="button" wire:click="removeStep({{ $index }})"><i class="fa fa-times"></i></x-text-button>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
        
        @can('manage', InsOmvRecipe::class)
        <x-secondary-button type="button" class="mt-6" type="button" wire:click="addStep">{{ __('Tambah langkah')}}</x-secondary-button>
        <div class="mt-6 flex justify-end items-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>

{{-- <div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Resep') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mb-6">
            <div class="grid grid-cols-3 gap-x-3">
                <div class="col-span-2 mt-6">
                    <label for="recipe-name"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                    <x-text-input id="recipe-name" wire:model="name" type="text" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                    @error('name')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-og_rs"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('OG/RS') }}</label>
                    <x-text-input id="recipe-og_rs" wire:model="og_rs" type="text" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                    @error('og_rs')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>
            <div class="grid grid-cols-3 gap-x-3">
                <div class="mt-6">
                    <label for="recipe-std_min"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Min') }}</label>
                    <x-text-input id="recipe-std_min" wire:model="std_min" type="number" step=".01" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                    @error('std_min')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-std_max"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Maks') }}</label>
                    <x-text-input id="recipe-std_max" wire:model="std_max" type="number" step=".01" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                    @error('std_max')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-std_mid"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Mid') }}</label>
                    <x-text-input id="recipe-std_mid" wire:model="std_mid" type="number" step=".01" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                    @error('std_mid')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>
            <div class="grid grid-cols-3 gap-x-3">
                <div class="mt-6">
                    <label for="recipe-pfc_min"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('PFC Min') }}</label>
                    <x-text-input id="recipe-pfc_min" wire:model="pfc_min" type="number" step=".01" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                    @error('pfc_min')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-pfc_max"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('PFC Max') }}</label>
                    <x-text-input id="recipe-pfc_max" wire:model="pfc_max" type="number" step=".01" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                    @error('pfc_max')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="recipe-scale"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Scale') }}</label>
                    <x-text-input id="recipe-scale" wire:model="scale" type="number" step=".01" :disabled="Gate::denies('manage', InsOmvRecipe::class)" />
                    @error('scale')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>
        </div>
        @can('manage', InsOmvRecipe::class)
        <div class="flex justify-between items-end">
            <div>
                {{-- <x-text-button type="button" class="uppercase text-xs text-red-500" wire:click="delete"
                    wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}">
                    {{ __('Hapus') }}
                </x-text-button> --}}
            {{-- </div>
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div> --}} 
