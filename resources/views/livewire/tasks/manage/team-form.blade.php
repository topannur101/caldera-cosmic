<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\TskTeam;

new class extends Component {
    
    public ?int $team_id = null;  // null = create, value = edit
    public string $name = '';
    public string $short_name = '';
    public string $desc = '';
    public bool $is_active = true;
    
    public bool $isEdit = false;
    
    #[On('team-create')]
    public function openCreate()
    {
        $this->reset(['team_id', 'name', 'short_name', 'desc']);
        $this->is_active = true;
        $this->isEdit = false;
    }
    
    #[On('team-edit')]
    public function openEdit($id)
    {
        $this->team_id = $id;
        $this->isEdit = true;
        
        // TODO: Load team data
        // $team = TskTeam::findOrFail($id);
        // $this->name = $team->name;
        // $this->short_name = $team->short_name;
        // $this->desc = $team->desc ?? '';
        // $this->is_active = $team->is_active;
        
        // Placeholder data
        $this->name = 'Demo Team';
        $this->short_name = 'DMO';
        $this->desc = 'Demo team description';
        $this->is_active = true;
    }
    
    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:10',
            'desc' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        
        if ($this->isEdit) {
            // TODO: Update existing team
            // $team = TskTeam::findOrFail($this->team_id);
            // $team->update([
            //     'name' => $this->name,
            //     'short_name' => $this->short_name,
            //     'desc' => $this->desc,
            //     'is_active' => $this->is_active,
            // ]);
            
            session()->flash('message', 'Tim berhasil diperbarui');
        } else {
            // TODO: Create new team
            // TskTeam::create([
            //     'name' => $this->name,
            //     'short_name' => $this->short_name,
            //     'desc' => $this->desc,
            //     'is_active' => $this->is_active,
            // ]);
            
            session()->flash('message', 'Tim berhasil dibuat');
        }
        
        // Close modal and refresh list
        $this->dispatch('close-modal', 'team-form');
        $this->dispatch('updated'); // Refresh the team list
    }
    
    public function cancel()
    {
        $this->reset(['team_id', 'name', 'short_name', 'desc']);
        $this->dispatch('close-modal', 'team-form');
    }
};

?>

<div class="p-6">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ $isEdit ? __('Edit Tim') : __('Buat Tim Baru') }}
        </h2>
        <x-text-button type="button" wire:click="cancel">
            <i class="icon-x"></i>
        </x-text-button>
    </div>
    
    <form wire:submit="save" class="space-y-6">
        
        <!-- Basic Info -->
        <div class="space-y-4">
            <div>
                <x-input-label for="name" :value="__('Nama Tim')" />
                <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            
            <div>
                <x-input-label for="short_name" :value="__('Nama Singkat')" />
                <x-text-input wire:model="short_name" id="short_name" class="block mt-1 w-full" type="text" required maxlength="10" placeholder="DGT" />
                <x-input-error :messages="$errors->get('short_name')" class="mt-2" />
                <p class="mt-1 text-sm text-neutral-500">{{ __('Maksimal 10 karakter, contoh: DGT, HRD, IT') }}</p>
            </div>
            
            <div>
                <x-input-label for="desc" :value="__('Deskripsi')" />
                <textarea wire:model="desc" id="desc" rows="3" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm" placeholder="{{ __('Deskripsi tim dan tugasnya...') }}"></textarea>
                <x-input-error :messages="$errors->get('desc')" class="mt-2" />
            </div>
            
            <div class="flex items-center">
                <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-neutral-300 text-caldy-600 shadow-sm focus:ring-caldy-500 dark:border-neutral-600 dark:bg-neutral-900 dark:focus:ring-caldy-600 dark:focus:ring-offset-neutral-800">
                <x-input-label for="is_active" :value="__('Tim Aktif')" class="ml-2" />
                <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
            </div>
        </div>
        
        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-neutral-200 dark:border-neutral-700">
            <x-secondary-button type="button" wire:click="cancel">
                {{ __('Batal') }}
            </x-secondary-button>
            <x-primary-button type="submit">
                <i class="icon-save mr-2"></i>
                {{ $isEdit ? __('Update Tim') : __('Simpan Tim') }}
            </x-primary-button>
        </div>
        
    </form>
</div>