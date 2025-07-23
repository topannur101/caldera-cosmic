<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component {
    
    public string $name = '';
    public string $desc = '';
    public string $code = '';
    public int $tsk_team_id = 0;
    public string $status = 'active';
    public string $priority = 'medium';
    public string $start_date = '';
    public string $end_date = '';
    
    public function mount()
    {
        // TODO: Check permissions
        // TODO: Load user's teams
    }
    
    public function with(): array
    {
        return [
            // TODO: Load teams user has access to
            'teams' => []
        ];
    }
    
    public function save()
    {
        // TODO: Validate and save project
        $this->validate([
            'name' => 'required|string|max:255',
            'desc' => 'nullable|string',
            'code' => 'nullable|string|max:50',
            'tsk_team_id' => 'required|exists:tsk_teams,id',
            'status' => 'required|in:active,completed,on_hold,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        
        // TODO: Create project
        session()->flash('message', 'Proyek berhasil dibuat');
        $this->redirect(route('tasks.projects.index'), navigate: true);
    }
};

?>

<x-slot name="title">{{ __('Buat Proyek Baru') }}</x-slot>

@auth
    <x-slot name="header">
        <x-nav-task-sub>{{ __('Buat Proyek Baru') }}</x-nav-task-sub>
    </x-slot>
@endauth

<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-neutral-900 dark:text-neutral-100">
                
                <form wire:submit="save" class="space-y-6">
                    
                    <!-- Basic Info -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold">{{ __('Informasi Dasar') }}</h3>
                        
                        <div>
                            <x-input-label for="name" :value="__('Nama Proyek')" />
                            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        
                        <div>
                            <x-input-label for="desc" :value="__('Deskripsi')" />
                            <textarea wire:model="desc" id="desc" rows="4" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm" placeholder="{{ __('Jelaskan tujuan dan ruang lingkup proyek...') }}"></textarea>
                            <x-input-error :messages="$errors->get('desc')" class="mt-2" />
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="code" :value="__('Kode Proyek (Opsional)')" />
                                <x-text-input wire:model="code" id="code" class="block mt-1 w-full" type="text" placeholder="PRJ-2025-001" />
                                <x-input-error :messages="$errors->get('code')" class="mt-2" />
                            </div>
                            
                            <div>
                                <x-input-label for="tsk_team_id" :value="__('Tim')" />
                                <select wire:model="tsk_team_id" id="tsk_team_id" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm" required>
                                    <option value="">{{ __('Pilih Tim') }}</option>
                                    <!-- TODO: Loop through teams -->
                                    <option value="1">{{ __('Digitalization (DGT)') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('tsk_team_id')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Settings -->
                    <div class="space-y-4 pt-6 border-t border-neutral-200 dark:border-neutral-700">
                        <h3 class="text-lg font-semibold">{{ __('Pengaturan Proyek') }}</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="status" :value="__('Status')" />
                                <select wire:model="status" id="status" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                                    <option value="active">{{ __('Aktif') }}</option>
                                    <option value="on_hold">{{ __('Ditahan') }}</option>
                                    <option value="completed">{{ __('Selesai') }}</option>
                                    <option value="cancelled">{{ __('Dibatalkan') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                            
                            <div>
                                <x-input-label for="priority" :value="__('Prioritas')" />
                                <select wire:model="priority" id="priority" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                                    <option value="low">{{ __('Rendah') }}</option>
                                    <option value="medium">{{ __('Sedang') }}</option>
                                    <option value="high">{{ __('Tinggi') }}</option>
                                    <option value="urgent">{{ __('Mendesak') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="start_date" :value="__('Tanggal Mulai')" />
                                <x-text-input wire:model="start_date" id="start_date" class="block mt-1 w-full" type="date" />
                                <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                            </div>
                            
                            <div>
                                <x-input-label for="end_date" :value="__('Tanggal Selesai')" />
                                <x-text-input wire:model="end_date" id="end_date" class="block mt-1 w-full" type="date" />
                                <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-3 pt-6 border-t border-neutral-200 dark:border-neutral-700">
                        <x-secondary-button type="button" onclick="window.location.href='{{ route('tasks.projects.index') }}'" wire:navigate>
                            {{ __('Batal') }}
                        </x-secondary-button>
                        <x-primary-button type="submit">
                            <i class="icon-save mr-2"></i>{{ __('Simpan Proyek') }}
                        </x-primary-button>
                    </div>
                    
                </form>
                
            </div>
        </div>
    </div>
</div>