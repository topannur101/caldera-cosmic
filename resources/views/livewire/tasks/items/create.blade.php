<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component {
    
    public string $title = '';
    public string $desc = '';
    public int $tsk_project_id = 0;
    public int $assigned_to = 0;
    public string $status = 'todo';
    public string $priority = 'medium';
    public string $due_date = '';
    public int $estimated_hours = 0;
    
    public function mount()
    {
        // TODO: Check permissions
        // TODO: Load user's projects and team members
    }
    
    public function with(): array
    {
        return [
            // TODO: Load projects and users
            'projects' => [],
            'users' => []
        ];
    }
    
    public function save()
    {
        // TODO: Validate and save task
        $this->validate([
            'title' => 'required|string|max:255',
            'desc' => 'nullable|string',
            'tsk_project_id' => 'required|exists:tsk_projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:todo,in_progress,review,done',
            'priority' => 'required|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
        ]);
        
        // TODO: Create task
        session()->flash('message', 'Tugas berhasil dibuat');
        $this->redirect(route('tasks.items.index'), navigate: true);
    }
};

?>

<x-slot name="title">{{ __('Buat Tugas Baru') }}</x-slot>

@auth
    <x-slot name="header">
        <x-nav-task-sub>{{ __('Buat Tugas Baru') }}</x-nav-task-sub>
    </x-slot>
@endauth

<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-neutral-900 dark:text-neutral-100">
                
                <form wire:submit="save" class="space-y-6">
                    
                    <!-- Basic Info -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold">{{ __('Informasi Tugas') }}</h3>
                        
                        <div>
                            <x-input-label for="title" :value="__('Judul Tugas')" />
                            <x-text-input wire:model="title" id="title" class="block mt-1 w-full" type="text" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>
                        
                        <div>
                            <x-input-label for="desc" :value="__('Deskripsi')" />
                            <textarea wire:model="desc" id="desc" rows="4" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm" placeholder="{{ __('Jelaskan detail tugas yang harus diselesaikan...') }}"></textarea>
                            <x-input-error :messages="$errors->get('desc')" class="mt-2" />
                        </div>
                        
                        <div>
                            <x-input-label for="tsk_project_id" :value="__('Proyek')" />
                            <select wire:model="tsk_project_id" id="tsk_project_id" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm" required>
                                <option value="">{{ __('Pilih Proyek') }}</option>
                                <!-- TODO: Loop through projects -->
                            </select>
                            <x-input-error :messages="$errors->get('tsk_project_id')" class="mt-2" />
                        </div>
                    </div>
                    
                    <!-- Assignment & Priority -->
                    <div class="space-y-4 pt-6 border-t border-neutral-200 dark:border-neutral-700">
                        <h3 class="text-lg font-semibold">{{ __('Penugasan & Prioritas') }}</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="assigned_to" :value="__('Ditugaskan Kepada')" />
                                <select wire:model="assigned_to" id="assigned_to" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                                    <option value="">{{ __('Belum ditugaskan') }}</option>
                                    <!-- TODO: Loop through team members -->
                                </select>
                                <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
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
                                <x-input-label for="status" :value="__('Status')" />
                                <select wire:model="status" id="status" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                                    <option value="todo">{{ __('To Do') }}</option>
                                    <option value="in_progress">{{ __('Dalam Proses') }}</option>
                                    <option value="review">{{ __('Review') }}</option>
                                    <option value="done">{{ __('Selesai') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                            
                            <div>
                                <x-input-label for="due_date" :value="__('Tenggat Waktu')" />
                                <x-text-input wire:model="due_date" id="due_date" class="block mt-1 w-full" type="date" />
                                <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                            </div>
                        </div>
                        
                        <div>
                            <x-input-label for="estimated_hours" :value="__('Estimasi Waktu (Jam)')" />
                            <x-text-input wire:model="estimated_hours" id="estimated_hours" class="block mt-1 w-full" type="number" min="0" placeholder="0" />
                            <x-input-error :messages="$errors->get('estimated_hours')" class="mt-2" />
                            <p class="mt-1 text-sm text-neutral-500">{{ __('Perkiraan waktu yang dibutuhkan untuk menyelesaikan tugas ini') }}</p>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-3 pt-6 border-t border-neutral-200 dark:border-neutral-700">
                        <x-secondary-button type="button" onclick="window.location.href='{{ route('tasks.items.index') }}'" wire:navigate>
                            {{ __('Batal') }}
                        </x-secondary-button>
                        <x-primary-button type="submit">
                            <i class="icon-save mr-2"></i>{{ __('Simpan Tugas') }}
                        </x-primary-button>
                    </div>
                    
                </form>
                
            </div>
        </div>
    </div>
</div>