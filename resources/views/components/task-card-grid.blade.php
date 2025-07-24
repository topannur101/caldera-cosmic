@props(['task'])

<div class="group bg-white dark:bg-neutral-800 rounded-lg overflow-hidden shadow hover:shadow-lg border border-neutral-200 dark:border-neutral-700">
    <!-- Status indicator strip -->
    <div class="h-1 bg-{{ $task->getStatusColor() }}-500"></div>
    
    <div class="p-4">
        <!-- Header -->
        <div class="flex justify-between items-start mb-3">
            <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-sm text-neutral-900 dark:text-white leading-tight mb-1">
                    <x-link href="#" class="hover:text-blue-600 dark:hover:text-blue-400">
                        {{ $task->title }}
                    </x-link>
                </h3>
                <div class="text-xs text-neutral-500 dark:text-neutral-400 font-medium">{{ $task->tsk_project->name }}</div>
            </div>
            <div class="ml-2 flex-shrink-0">
                <x-task-status-badge :status="$task->status" size="sm" />
            </div>
        </div>
        
        @if($task->desc)
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mb-3 line-clamp-2 leading-relaxed">{{ $task->desc }}</p>
        @endif
        
        <!-- Metadata badges -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                @if($task->tsk_type)
                    <span class="inline-flex items-center gap-1 text-xs px-2 py-1 bg-purple-100 dark:bg-purple-900/30 rounded-md text-purple-700 dark:text-purple-300 font-medium">
                        <div class="w-1.5 h-1.5 rounded-full bg-purple-500"></div>
                        {{ $task->tsk_type->name }}
                    </span>
                @endif
                @if($task->estimated_hours)
                    <span class="inline-flex items-center gap-1 text-xs text-neutral-600 dark:text-neutral-400 font-medium">
                        <i class="icon-clock"></i>
                        {{ $task->estimated_hours }}h
                    </span>
                @endif
            </div>
            @if($task->assignee)
                <x-user-avatar :user="$task->assignee" size="xs" />
            @endif
        </div>
        
        @if($task->end_date)
            <div class="flex items-center gap-1.5 text-xs mb-3 {{ $task->isOverdue() ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                <i class="icon-calendar"></i>
                <span class="font-semibold">{{ \Carbon\Carbon::parse($task->end_date)->format('M d') }}</span>
            </div>
        @endif
        
        <!-- Quick actions -->
        <div class="flex gap-1 opacity-0 group-hover:opacity-100">
            <button class="flex-1 flex items-center justify-center gap-1 px-2 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-semibold">
                <i class="icon-edit-2 text-xs"></i>
                Edit
            </button>
            <button class="px-2 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded text-xs">
                <i class="icon-trash-2 text-xs"></i>
            </button>
        </div>
    </div>
</div>