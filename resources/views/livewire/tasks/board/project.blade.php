<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\TskProject;
use App\Models\TskItem;

new #[Layout('layouts.app')]
class extends Component {
    
    public int $project_id;
    public $project;
    
    public function mount($project_id)
    {
        $this->project_id = $project_id;
        // TODO: Check permissions and load project
        // $this->project = TskProject::findOrFail($project_id);
    }
    
    public function with(): array
    {
        return [
            // TODO: Load tasks grouped by status
            'todo_tasks' => [],
            'in_progress_tasks' => [],
            'review_tasks' => [],
            'done_tasks' => [],
            'project_name' => 'Demo Project' // TODO: Load from actual project
        ];
    }
    
    public function updateTaskStatus($taskId, $newStatus)
    {
        // TODO: Update task status and emit event
        $this->dispatch('task-status-updated');
    }
};

?>

<x-slot name="title">{{ __('Board - ') . ($project_name ?? 'Project') }}</x-slot>

@auth
    <x-slot name="header">
        <x-nav-task-sub>{{ __('Board - ') . ($project_name ?? 'Project') }}</x-nav-task-sub>
    </x-slot>
@endauth

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Board Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                    {{ $project_name ?? 'Demo Project' }}
                </h1>
                <p class="text-neutral-600 dark:text-neutral-400">{{ __('Kanban Board') }}</p>
            </div>
            <div class="flex gap-3">
                <x-secondary-button onclick="window.location.href='{{ route('tasks.items.index') }}'" wire:navigate>
                    <i class="icon-list mr-2"></i>{{ __('List View') }}
                </x-secondary-button>
                <x-primary-button x-on:click.prevent="$dispatch('open-slide-over', 'task-create'); $dispatch('task-create', { project_id: {{ $project_id ?? 1 }} })">
                    <i class="icon-plus mr-2"></i>{{ __('Tambah Tugas') }}
                </x-primary-button>
            </div>
        </div>

        <!-- Kanban Board -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            
            <!-- To Do Column -->
            <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4 min-h-96">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-neutral-700 dark:text-neutral-300">
                        <i class="icon-circle mr-2"></i>{{ __('To Do') }}
                    </h3>
                    <span class="px-2 py-1 text-xs bg-neutral-200 dark:bg-neutral-700 rounded-full">
                        {{ count($todo_tasks) }}
                    </span>
                </div>
                <div class="space-y-3" id="todo-column">
                    @forelse($todo_tasks as $task)
                        <!-- TODO: Task card component -->
                        <div class="bg-white dark:bg-neutral-800 p-4 rounded-lg shadow-sm border border-neutral-200 dark:border-neutral-700 cursor-move">
                            <h4 class="font-medium text-sm mb-2">{{ $task['title'] ?? 'Sample Task' }}</h4>
                            <div class="flex justify-between items-center text-xs text-neutral-500">
                                <span>{{ $task['assignee'] ?? 'Unassigned' }}</span>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">{{ $task['priority'] ?? 'Medium' }}</span>
                            </div>
                        </div>
                    @empty
                        <!-- Placeholder -->
                        <div class="text-center py-8 text-neutral-400">
                            <i class="icon-plus-circle text-2xl mb-2"></i>
                            <p class="text-sm">{{ __('Drag tugas ke sini atau') }}</p>
                            <x-text-button x-on:click.prevent="$dispatch('open-slide-over', 'task-create'); $dispatch('task-create', { project_id: {{ $project_id ?? 1 }} })" class="mt-2">
                                {{ __('Tambah tugas baru') }}
                            </x-text-button>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- In Progress Column -->
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 min-h-96">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-blue-700 dark:text-blue-300">
                        <i class="icon-clock mr-2"></i>{{ __('Dalam Proses') }}
                    </h3>
                    <span class="px-2 py-1 text-xs bg-blue-200 dark:bg-blue-700 rounded-full">
                        {{ count($in_progress_tasks) }}
                    </span>
                </div>
                <div class="space-y-3" id="in-progress-column">
                    @forelse($in_progress_tasks as $task)
                        <!-- TODO: Task card component -->
                        <div class="bg-white dark:bg-neutral-800 p-4 rounded-lg shadow-sm border border-blue-200 dark:border-blue-700 cursor-move">
                            <h4 class="font-medium text-sm mb-2">{{ $task['title'] ?? 'Sample Task' }}</h4>
                            <div class="flex justify-between items-center text-xs text-neutral-500">
                                <span>{{ $task['assignee'] ?? 'Unassigned' }}</span>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">{{ $task['priority'] ?? 'High' }}</span>
                            </div>
                        </div>
                    @empty
                        <!-- Placeholder -->
                        <div class="text-center py-8 text-blue-300">
                            <i class="icon-arrow-right text-2xl mb-2"></i>
                            <p class="text-sm">{{ __('Tugas dalam proses akan muncul di sini') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Review Column -->
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 min-h-96">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-yellow-700 dark:text-yellow-300">
                        <i class="icon-eye mr-2"></i>{{ __('Review') }}
                    </h3>
                    <span class="px-2 py-1 text-xs bg-yellow-200 dark:bg-yellow-700 rounded-full">
                        {{ count($review_tasks) }}
                    </span>
                </div>
                <div class="space-y-3" id="review-column">
                    @forelse($review_tasks as $task)
                        <!-- TODO: Task card component -->
                        <div class="bg-white dark:bg-neutral-800 p-4 rounded-lg shadow-sm border border-yellow-200 dark:border-yellow-700 cursor-move">
                            <h4 class="font-medium text-sm mb-2">{{ $task['title'] ?? 'Sample Task' }}</h4>
                            <div class="flex justify-between items-center text-xs text-neutral-500">
                                <span>{{ $task['assignee'] ?? 'Unassigned' }}</span>
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded">{{ $task['priority'] ?? 'Urgent' }}</span>
                            </div>
                        </div>
                    @empty
                        <!-- Placeholder -->
                        <div class="text-center py-8 text-yellow-300">
                            <i class="icon-search text-2xl mb-2"></i>
                            <p class="text-sm">{{ __('Tugas dalam review akan muncul di sini') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Done Column -->
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 min-h-96">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-green-700 dark:text-green-300">
                        <i class="icon-check mr-2"></i>{{ __('Selesai') }}
                    </h3>
                    <span class="px-2 py-1 text-xs bg-green-200 dark:bg-green-700 rounded-full">
                        {{ count($done_tasks) }}
                    </span>
                </div>
                <div class="space-y-3" id="done-column">
                    @forelse($done_tasks as $task)
                        <!-- TODO: Task card component -->
                        <div class="bg-white dark:bg-neutral-800 p-4 rounded-lg shadow-sm border border-green-200 dark:border-green-700 cursor-move opacity-75">
                            <h4 class="font-medium text-sm mb-2 line-through">{{ $task['title'] ?? 'Completed Task' }}</h4>
                            <div class="flex justify-between items-center text-xs text-neutral-500">
                                <span>{{ $task['assignee'] ?? 'Unassigned' }}</span>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded">{{ __('Done') }}</span>
                            </div>
                        </div>
                    @empty
                        <!-- Placeholder -->
                        <div class="text-center py-8 text-green-300">
                            <i class="icon-trophy text-2xl mb-2"></i>
                            <p class="text-sm">{{ __('Tugas selesai akan muncul di sini') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

        <!-- TODO: Add drag and drop functionality with JavaScript -->
        <!-- TODO: Add task detail modal when clicking on task cards -->
        
    </div>
    <div wire:key="slideovers">
        <!-- Task Creation Slideover -->
        <x-slide-over name="task-create">
            <livewire:tasks.items.create />
        </x-slide-over>
    </div>
</div>


@script
<script>
// TODO: Implement drag and drop functionality
// TODO: Add real-time updates
console.log('Kanban board loaded - drag and drop functionality to be implemented');
</script>
@endscript