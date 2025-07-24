@props(['task'])

<div class="bg-white dark:bg-neutral-800 shadow overflow-hidden rounded-lg hover:shadow-md transition-shadow group">
    <div class="p-4">
        <div class="flex justify-between items-start mb-3">
            <div class="flex-1 min-w-0">
                <h3 class="font-medium text-sm truncate">
                    <x-link href="#" class="hover:text-caldy-600">{{ $task->title }}</x-link>
                </h3>
                <div class="text-xs text-neutral-500 mt-1">{{ $task->tsk_project->name }}</div>
            </div>
            <div class="ml-2 flex-shrink-0">
                <x-task-status-badge :status="$task->status" size="sm" />
            </div>
        </div>
        
        @if($task->desc)
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mb-3 line-clamp-2">{{ $task->desc }}</p>
        @endif
        
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                @if($task->tsk_type)
                    <span class="text-xs px-1.5 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded text-neutral-600 dark:text-neutral-400">
                        {{ $task->tsk_type->name }}
                    </span>
                @endif
                @if($task->estimated_hours)
                    <span class="text-xs text-neutral-500">{{ $task->estimated_hours }}h</span>
                @endif
            </div>
            @if($task->assignee)
                <x-user-avatar :user="$task->assignee" size="xs" />
            @endif
        </div>
        
        @if($task->end_date)
            <div class="mt-2 text-xs {{ $task->isOverdue() ? 'text-red-600' : 'text-neutral-500' }}">
                <i class="icon-calendar mr-1"></i>{{ \Carbon\Carbon::parse($task->end_date)->format('d M Y') }}
            </div>
        @endif
        
        <!-- Actions (shown on hover) -->
        <div class="mt-3 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <x-text-button size="xs" class="text-neutral-400 hover:text-caldy-600">
                <i class="icon-edit-2 mr-1"></i>{{ __('Edit') }}
            </x-text-button>
            <x-text-button size="xs" class="text-neutral-400 hover:text-red-600">
                <i class="icon-trash-2 mr-1"></i>{{ __('Hapus') }}
            </x-text-button>
        </div>
    </div>
</div>
