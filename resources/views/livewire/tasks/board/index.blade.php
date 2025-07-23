<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\TskProject;

new #[Layout('layouts.app')]
class extends Component {
    
    public function mount()
    {
        // TODO: Check permissions
    }
    
    public function with(): array
    {
        return [
            // TODO: Load user's accessible projects
            'projects' => []
        ];
    }
};

?>

<x-slot name="title">{{ __('Board Kanban') }}</x-slot>

@auth
    <x-slot name="header">
        <x-nav-task-sub>{{ __('Board Kanban') }}</x-nav-task-sub>
    </x-slot>
@endauth

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-neutral-900 dark:text-neutral-100">
                
                <!-- Page Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-2xl font-bold mb-2">{{ __('Board Kanban') }}</h1>
                        <p class="text-neutral-600 dark:text-neutral-400">{{ __('Pilih proyek untuk melihat board tugas') }}</p>
                    </div>
                    <div class="flex gap-3">
                        <x-secondary-button onclick="window.location.href='{{ route('tasks.items.index') }}'" wire:navigate>
                            <i class="icon-list mr-2"></i>{{ __('List View') }}
                        </x-secondary-button>
                        <x-primary-button onclick="window.location.href='{{ route('tasks.projects.create') }}'" wire:navigate>
                            <i class="icon-plus mr-2"></i>{{ __('Buat Proyek') }}
                        </x-primary-button>
                    </div>
                </div>

                <!-- Project Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @forelse($projects as $project)
                        <!-- TODO: Project cards will be loaded here -->
                        <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-6 hover:shadow-md transition-shadow cursor-pointer"
                             onclick="window.location.href='{{ route('tasks.board.project', ['project_id' => $project['id'] ?? 1]) }}'"
                             wire:navigate>
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ $project['name'] ?? 'Sample Project' }}</h3>
                                    <p class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $project['team_name'] ?? 'Team Name' }} • {{ $project['status'] ?? 'Active' }}
                                    </p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full {{ $project['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-neutral-100 text-neutral-800' }}">
                                    {{ $project['status'] === 'active' ? __('Aktif') : __('Selesai') }}
                                </span>
                            </div>
                            
                            @if($project['description'] ?? false)
                            <p class="text-neutral-600 dark:text-neutral-400 mb-4 text-sm">
                                {{ $project['description'] }}
                            </p>
                            @endif
                            
                            <!-- Task Summary -->
                            <div class="grid grid-cols-4 gap-2 text-center text-xs">
                                <div class="bg-neutral-100 dark:bg-neutral-700 rounded p-2">
                                    <div class="font-semibold text-neutral-600 dark:text-neutral-400">{{ $project['todo_count'] ?? 0 }}</div>
                                    <div class="text-neutral-500">{{ __('To Do') }}</div>
                                </div>
                                <div class="bg-blue-100 dark:bg-blue-900/20 rounded p-2">
                                    <div class="font-semibold text-blue-600 dark:text-blue-400">{{ $project['in_progress_count'] ?? 0 }}</div>
                                    <div class="text-blue-600 dark:text-blue-400">{{ __('Progress') }}</div>
                                </div>
                                <div class="bg-yellow-100 dark:bg-yellow-900/20 rounded p-2">
                                    <div class="font-semibold text-yellow-600 dark:text-yellow-400">{{ $project['review_count'] ?? 0 }}</div>
                                    <div class="text-yellow-600 dark:text-yellow-400">{{ __('Review') }}</div>
                                </div>
                                <div class="bg-green-100 dark:bg-green-900/20 rounded p-2">
                                    <div class="font-semibold text-green-600 dark:text-green-400">{{ $project['done_count'] ?? 0 }}</div>
                                    <div class="text-green-600 dark:text-green-400">{{ __('Done') }}</div>
                                </div>
                            </div>
                            
                            <!-- View Board Button -->
                            <div class="mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-neutral-500">
                                        @if($project['due_date'] ?? false)
                                            {{ __('Due: ') . $project['due_date'] }}
                                        @else
                                            {{ ($project['total_tasks'] ?? 0) . ' ' . __('total tugas') }}
                                        @endif
                                    </span>
                                    <span class="text-caldy-600 dark:text-caldy-400 font-medium">
                                        {{ __('Lihat Board') }} →
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <!-- No Projects State -->
                        <div class="col-span-full text-center py-16">
                            <div class="text-neutral-500 dark:text-neutral-400">
                                <i class="icon-layout-kanban text-6xl mb-4"></i>
                                <h3 class="text-lg font-semibold mb-2">{{ __('Belum ada proyek') }}</h3>
                                <p class="mb-6">{{ __('Buat proyek pertama untuk mulai menggunakan board kanban') }}</p>
                                <div class="flex gap-3 justify-center">
                                    <x-primary-button onclick="window.location.href='{{ route('tasks.projects.create') }}'" wire:navigate>
                                        <i class="icon-plus mr-2"></i>{{ __('Buat Proyek Pertama') }}
                                    </x-primary-button>
                                    <x-secondary-button onclick="window.location.href='{{ route('tasks.projects.index') }}'" wire:navigate>
                                        <i class="icon-folder mr-2"></i>{{ __('Lihat Semua Proyek') }}
                                    </x-secondary-button>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>

                <!-- Quick Stats (if there are projects) -->
                @if(count($projects) > 0)
                <div class="mt-12 pt-8 border-t border-neutral-200 dark:border-neutral-700">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Ringkasan') }}</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ count($projects) }}</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Total Proyek') }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">0</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tugas Aktif') }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">0</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tugas Selesai') }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">0</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tugas Tertunda') }}</div>
                        </div>
                    </div>
                </div>
                @endif
                
            </div>
        </div>
    </div>
    <div wire:key="slideovers">
        <!-- Task Creation Slideover -->
        <x-slide-over name="task-create">
            <livewire:tasks.items.create />
        </x-slide-over>
    </div>
</div>
