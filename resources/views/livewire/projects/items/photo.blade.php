<?php

// resources/livewire/projects/items/photo.blade.php
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use App\Models\PjtItem;

new class extends Component {

    use WithFileUploads;

    public int $id = 0;
    public string $size = 'lg';
    public bool $is_editing = false;
    public string $photo_url = '';

    #[Validate('image|max:2048')]
    public $photo;

    public function mount()
    {
        if ($this->id && !$this->photo_url) {
            $project = PjtItem::find($this->id);
            if ($project && $project->photo) {
                $this->photo_url = '/storage/pjt-items/' . $project->photo;
            }
        }
    }

    public function updatedPhoto()
    {
        $this->validate();
        
        if ($this->photo) {
            $this->dispatch('photo-updated', $this->photo->hashName());
        }
    }

    public function removePhoto()
    {
        $this->photo = null;
        $this->photo_url = '';
        
        if ($this->id) {
            $project = PjtItem::find($this->id);
            if ($project) {
                $project->updatePhoto(null);
            }
        }
        
        $this->dispatch('photo-updated', null);
    }

    public function savePhoto()
    {
        if (!$this->photo || !$this->id) return;
        
        $project = PjtItem::find($this->id);
        if ($project) {
            $project->updatePhoto($this->photo->hashName());
            $this->photo_url = $project->photo_url;
            $this->photo = null;
        }
    }

    public function getDimensions(): array
    {
        return match($this->size) {
            'sm' => ['container' => 'w-20 h-20', 'icon' => 'w-8 h-8'],
            'md' => ['container' => 'w-32 h-32', 'icon' => 'w-12 h-12'],
            'lg' => ['container' => 'w-48 h-48', 'icon' => 'w-16 h-16'],
            default => ['container' => 'w-32 h-32', 'icon' => 'w-12 h-12']
        };
    }

}; ?>

@php $dims = $this->getDimensions(); @endphp

<div>
    <div class="relative {{ $dims['container'] }} mx-auto bg-neutral-200 dark:bg-neutral-700 rounded-lg overflow-hidden group">
        
        {{-- Default Icon --}}
        @if(!$photo && !$photo_url)
            <div class="flex items-center justify-center w-full h-full">
                <svg xmlns="http://www.w3.org/2000/svg" class="block {{ $dims['icon'] }} fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 24 24">
                    <path d="M20 6h-2.18l-1.41-1.41C16.05 4.22 15.55 4 15 4H9c-.55 0-1.05.22-1.41.59L6.18 6H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.65 0-3 1.35-3 3s1.35 3 3 3 3-1.35 3-3-1.35-3-3-3z"/>
                </svg>
            </div>
        @endif

        {{-- Preview Photo --}}
        @if($photo)
            <img class="w-full h-full object-cover dark:brightness-75" src="{{ $photo->temporaryUrl() }}" />
        @elseif($photo_url)
            <img class="w-full h-full object-cover dark:brightness-75" src="{{ $photo_url }}" />
        @endif

        {{-- Upload/Edit Overlay --}}
        @if($is_editing)
            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center">
                <div class="flex gap-2">
                    <label for="photo-upload-{{ $id }}" class="cursor-pointer bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 px-3 py-1 rounded text-sm hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors">
                        <i class="icon-upload mr-1"></i>{{ __('Upload') }}
                    </label>
                    
                    @if($photo || $photo_url)
                        <button wire:click="removePhoto" type="button" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors">
                            <i class="icon-trash mr-1"></i>{{ __('Hapus') }}
                        </button>
                    @endif
                </div>
            </div>

            {{-- Hidden File Input --}}
            <input 
                type="file" 
                id="photo-upload-{{ $id }}" 
                wire:model="photo" 
                accept="image/*" 
                class="hidden"
            />
        @endif

        {{-- Loading Spinner --}}
        <div wire:loading wire:target="photo" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <x-spinner class="text-white"></x-spinner>
        </div>
    </div>

    {{-- Photo Actions (for editing existing projects) --}}
    @if($is_editing && $id && $photo)
        <div class="flex justify-center mt-3 gap-2">
            <x-secondary-button wire:click="savePhoto" type="button">
                <i class="icon-save mr-2"></i>{{ __('Simpan') }}
            </x-secondary-button>
            <x-text-button wire:click="removePhoto" type="button">
                <i class="icon-x mr-2"></i>{{ __('Batal') }}
            </x-text-button>
        </div>
    @endif

    {{-- Upload Progress --}}
    <div wire:loading wire:target="photo" class="mt-2">
        <div class="text-xs text-center text-neutral-500">{{ __('Mengupload...') }}</div>
        <div class="w-full bg-neutral-200 rounded-full h-1 mt-1">
            <div class="bg-caldy-500 h-1 rounded-full animate-pulse" style="width: 60%"></div>
        </div>
    </div>

    {{-- Error Messages --}}
    @error('photo')
        <div class="mt-2 text-xs text-red-500 text-center">{{ $message }}</div>
    @enderror

    {{-- Photo Info --}}
    @if($photo_url && $size === 'lg')
        <div class="mt-3 text-xs text-center text-neutral-500">
            {{ __('Foto project') }}
        </div>
    @endif
</div>

@script
<script>
    // Listen for remove photo event from parent
    $wire.on('remove-photo', () => {
        $wire.removePhoto();
    });
</script>
@endscript