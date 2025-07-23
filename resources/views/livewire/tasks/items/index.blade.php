<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')]
class extends Component {
    
    use WithPagination;
    
    #[Url]
    public string $search = '';
    
    #[Url]
    public string $status = '';
    
    #[Url]
    public string $priority = '';
    
    #[Url]
    public string $project = '';
    
    #[Url]
    public string $assignee = '';
    
    #[Url]
    public string $view = 'list'; // list or board
    
    public function mount()
    {
        // TODO: Check permissions
    }
    
    public function with(): array
    {
        return [
            // TODO: Load tasks with filters
            'tasks' => [],
            'projects' => [],
            'users' => [],
            'can_create' => true // TODO: Check permissions
        ];
    }
    
    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function switchView($view)
    {
        $this->view = $view;
    }
};

?>

<x-slot name="title">{{ __('Tugas') }}</x-slot>

@auth
    <x-slot name="header">
        <x-nav-task></x-nav-task>
    </x-slot>
@endauth

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-neutral-900 dark:text-neutral-100">
                
                <!-- Page Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-2xl font-bold mb-2">{{ __('Tugas') }}</h1>
                        <p class="text-neutral-600 dark:text-neutral-400">{{ __('Kelola dan pantau semua tugas') }}</p>
                    </div>
                    <div class="flex gap-3">
                        <!-- View Toggle -->
                        <div class="flex border border-neutral-300 dark:border-neutral-600 rounded-lg overflow-hidden">
                            <button 
                                wire:click="switchView('list')" 
                                class="px-3 py-2 text-sm {{ $view === 'list' ? 'bg-caldy-500 text-white' : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300' }}">
                                <i class="icon-list"></i>
                            </button>
                            <button 
                                wire:click="switchView('board')" 
                                class="px-3 py-2 text-sm {{ $view === 'board' ? 'bg-caldy-500 text-white' : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300' }}">
                                <i class="icon-kanban"></i>
                            </button>
                        </div>
                        
                        @if($can_create)
                        <x-primary-button onclick="window.location.href='{{ route('tasks.items.create') }}'" wire:navigate>
                            <i class="icon-plus mr-2"></i>{{ __('Buat Tugas') }}
                        </x-primary-button>
                        @endif
                    </div>
                </div>

                <!-- Filters -->
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                    <div>
                        <x-input-label for="search" value="{{ __('Cari Tugas') }}" />
                        <x-text-input 
                            id="search" 
                            wire:model.live.debounce.300ms="search" 
                            type="text" 
                            placeholder="{{ __('Judul tugas...') }}" 
                            class="block w-full" />
                    </div>
                    
                    <div>
                        <x-input-label for="status" value="{{ __('Status') }}" />
                        <select wire:model.live="status" id="status" class="block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                            <option value="">{{ __('Semua Status') }}</option>
                            <option value="todo">{{ __('To Do') }}</option>
                            <option value="in_progress">{{ __('Dalam Proses') }}</option>
                            <option value="review">{{ __('Review') }}</option>
                            <option value="done">{{ __('Selesai') }}</option>
                        </select>
                    </div>
                    
                    <div>
                        <x-input-label for="priority" value="{{ __('Prioritas') }}" />
                        <select wire:model.live="priority" id="priority" class="block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                            <option value="">{{ __('Semua Prioritas') }}</option>
                            <option value="low">{{ __('Rendah') }}</option>
                            <option value="medium">{{ __('Sedang') }}</option>
                            <option value="high">{{ __('Tinggi') }}</option>
                            <option value="urgent">{{ __('Mendesak') }}</option>
                        </select>
                    </div>
                    
                    <div>
                        <x-input-label for="project" value="{{ __('Proyek') }}" />
                        <select wire:model.live="project" id="project" class="block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                            <option value="">{{ __('Semua Proyek') }}</option>
                            <!-- TODO: Loop through projects -->
                        </select>
                    </div>
                    
                    <div>
                        <x-input-label for="assignee" value="{{ __('Ditugaskan') }}" />
                        <select wire:model.live="assignee" id="assignee" class="block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                            <option value="">{{ __('Semua Pengguna') }}</option>
                            <!-- TODO: Loop through users -->
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <x-secondary-button wire:click="$set('search', ''); $set('status', ''); $set('priority', ''); $set('project', ''); $set('assignee', '')" class="w-full">
                            <i class="icon-x mr-2"></i>{{ __('Reset') }}
                        </x-secondary-button>
                    </div>
                </div>

                <!-- Tasks Content -->
                @if($view === 'list')
                    <!-- List View -->
                    <div class="space-y-4">
                        <!-- Placeholder when no tasks -->
                        <div class="text-center py-12 text-neutral-500 dark:text-neutral-400">
                            <i class="icon-list-todo text-6xl mb-4"></i>
                            <h3 class="text-lg font-semibold mb-2">{{ __('Belum ada tugas') }}</h3>
                            <p class="mb-4">{{ __('Mulai dengan membuat tugas pertama Anda') }}</p>
                            @if($can_create)
                            <x-primary-button onclick="window.location.href='{{ route('tasks.items.create') }}'" wire:navigate>
                                <i class="icon-plus mr-2"></i>{{ __('Buat Tugas Pertama') }}
                            </x-primary-button>
                            @endif
                        </div>
                        
                        <!-- TODO: Task list items will go here -->
                        <!-- Example task item structure:
                        <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="font-semibold">Task Title</h3>
                                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">In Progress</span>
                                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">High</span>
                                    </div>
                                    <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-2">Task description...</p>
                                    <div class="flex items-center gap-4 text-xs text-neutral-500">
                                        <span>Project Name</span>
                                        <span>Assigned to: User Name</span>
                                        <span>Due: Date</span>
                                    </div>
                                </div>
                                <x-primary-button size="sm">View</x-primary-button>
                            </div>
                        </div>
                        -->
                    </div>
                @else
                    <!-- Board View -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <!-- To Do Column -->
                        <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4">
                            <h3 class="font-semibold mb-4 text-neutral-700 dark:text-neutral-300">
                                <i class="icon-circle mr-2"></i>{{ __('To Do') }}
                                <span class="ml-2 px-2 py-1 text-xs bg-neutral-200 dark:bg-neutral-700 rounded-full">0</span>
                            </h3>
                            <div class="space-y-3">
                                <!-- TODO: Task cards will go here -->
                                <div class="text-center py-8 text-neutral-400">
                                    <p class="text-sm">{{ __('Tidak ada tugas') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- In Progress Column -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                            <h3 class="font-semibold mb-4 text-blue-700 dark:text-blue-300">
                                <i class="icon-clock mr-2"></i>{{ __('Dalam Proses') }}
                                <span class="ml-2 px-2 py-1 text-xs bg-blue-200 dark:bg-blue-700 rounded-full">0</span>
                            </h3>
                            <div class="space-y-3">
                                <!-- TODO: Task cards will go here -->
                                <div class="text-center py-8 text-blue-300">
                                    <p class="text-sm">{{ __('Tidak ada tugas') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Review Column -->
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                            <h3 class="font-semibold mb-4 text-yellow-700 dark:text-yellow-300">
                                <i class="icon-eye mr-2"></i>{{ __('Review') }}
                                <span class="ml-2 px-2 py-1 text-xs bg-yellow-200 dark:bg-yellow-700 rounded-full">0</span>
                            </h3>
                            <div class="space-y-3">
                                <!-- TODO: Task cards will go here -->
                                <div class="text-center py-8 text-yellow-300">
                                    <p class="text-sm">{{ __('Tidak ada tugas') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Done Column -->
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                            <h3 class="font-semibold mb-4 text-green-700 dark:text-green-300">
                                <i class="icon-check mr-2"></i>{{ __('Selesai') }}
                                <span class="ml-2 px-2 py-1 text-xs bg-green-200 dark:bg-green-700 rounded-full">0</span>
                            </h3>
                            <div class="space-y-3">
                                <!-- TODO: Task cards will go here -->
                                <div class="text-center py-8 text-green-300">
                                    <p class="text-sm">{{ __('Tidak ada tugas') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Pagination will go here -->
                
            </div>
        </div>
    </div>
</div>