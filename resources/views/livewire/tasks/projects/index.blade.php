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
    public string $team = '';
    
    public function mount()
    {
        // TODO: Check permissions
    }
    
    public function with(): array
    {
        return [
            // TODO: Load projects with filters
            'projects' => [],
            'teams' => [],
            'can_create' => true // TODO: Check permissions
        ];
    }
    
    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function updatedStatus()
    {
        $this->resetPage();
    }
    
    public function updatedTeam()
    {
        $this->resetPage();
    }
};

?>

<x-slot name="title">{{ __('Proyek') }}</x-slot>

@auth
    <x-slot name="header">
        <x-nav-task></x-nav-task>
    </x-slot>
@endauth

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow sm:rounded-lg">
            <div class="p-6 text-neutral-900 dark:text-neutral-100">
                
                <!-- Page Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-2xl font-bold mb-2">{{ __('Proyek') }}</h1>
                        <p class="text-neutral-600 dark:text-neutral-400">{{ __('Kelola proyek dan tugas tim') }}</p>
                    </div>
                    @if($can_create)
                    <x-primary-button onclick="window.location.href='{{ route('tasks.projects.create') }}'" wire:navigate>
                        <i class="icon-plus mr-2"></i>{{ __('Buat Proyek') }}
                    </x-primary-button>
                    @endif
                </div>

                <!-- Filters -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <x-input-label for="search" value="{{ __('Cari Proyek') }}" />
                        <x-text-input 
                            id="search" 
                            wire:model.live.debounce.300ms="search" 
                            type="text" 
                            placeholder="{{ __('Nama proyek...') }}" 
                            class="block w-full" />
                    </div>
                    
                    <div>
                        <x-input-label for="status" value="{{ __('Status') }}" />
                        <select wire:model.live="status" id="status" class="block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                            <option value="">{{ __('Semua Status') }}</option>
                            <option value="active">{{ __('Aktif') }}</option>
                            <option value="completed">{{ __('Selesai') }}</option>
                            <option value="on_hold">{{ __('Ditahan') }}</option>
                            <option value="cancelled">{{ __('Dibatalkan') }}</option>
                        </select>
                    </div>
                    
                    <div>
                        <x-input-label for="team" value="{{ __('Tim') }}" />
                        <select wire:model.live="team" id="team" class="block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm">
                            <option value="">{{ __('Semua Tim') }}</option>
                            <!-- TODO: Loop through teams -->
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <x-secondary-button wire:click="$set('search', ''); $set('status', ''); $set('team', '')" class="w-full">
                            <i class="icon-x mr-2"></i>{{ __('Reset Filter') }}
                        </x-secondary-button>
                    </div>
                </div>

                <!-- Projects List -->
                <div class="space-y-4">
                    <!-- Placeholder when no projects -->
                    <div class="text-center py-12 text-neutral-500 dark:text-neutral-400">
                        <i class="icon-drafting-compass text-6xl mb-4"></i>
                        <h3 class="text-lg font-semibold mb-2">{{ __('Belum ada proyek') }}</h3>
                        <p class="mb-4">{{ __('Mulai dengan membuat proyek pertama Anda') }}</p>
                        @if($can_create)
                        <x-primary-button onclick="window.location.href='{{ route('tasks.projects.create') }}'" wire:navigate>
                            <i class="icon-plus mr-2"></i>{{ __('Buat Proyek Pertama') }}
                        </x-primary-button>
                        @endif
                    </div>
                    
                    <!-- TODO: Project cards will go here -->
                    <!-- Example project card structure:
                    <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-6 hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold">Project Name</h3>
                                <p class="text-sm text-neutral-600 dark:text-neutral-400">Team • Status</p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                        </div>
                        <p class="text-neutral-600 dark:text-neutral-400 mb-4">Project description...</p>
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                X tasks • Due: Date
                            </div>
                            <x-primary-button size="sm">View</x-primary-button>
                        </div>
                    </div>
                    -->
                </div>

                <!-- Pagination will go here -->
                
            </div>
        </div>
    </div>
    <div wire:key="task-create-slideovers">
        <!-- Task Creation Slideover -->
        <x-slide-over name="task-create">
            <livewire:tasks.items.create />
        </x-slide-over>
    </div>
</div>
