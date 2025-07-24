@props(['task'])

<div class="bg-white dark:bg-neutral-800 shadow overflow-hidden rounded-lg hover:shadow-md transition-shadow group">
    <div class="flex">
        <!-- Status Color Bar -->
        <div class="w-1 bg-{{ $task->getStatusColor() }}-500"></div>
        
        <div class="flex-1 p-4">
            <div class="flex justify-between items-start mb-2">
                <div class="flex-1 min-w-0">
                    <h3 class="font-medium truncate">
                        <x-link href="#" class="hover:text-caldy-600">{{ $task->title }}</x-link>
                    </h3>
                    @if($task->desc)
                        <p class="text-sm text-neutral-600 dark:text-neutral-400 mt-1 line-clamp-1">{{ $task->desc }}</p>
                    @endif
                </div>
                <div class="ml-3 flex-shrink-0">
                    <x-task-status-badge :status="$task->status" />
                </div>
            </div>
            
            <div class="flex items-center justify-between text-sm text-neutral-600 dark:text-neutral-400 mb-3">
                <div class="flex items-center gap-3">
                    <span title="{{ $task->tsk_project->name }}">
                        <i class="icon-folder mr-1"></i>{{ $task->tsk_project->name }}
                    </span>
                    <span class="text-xs">{{ $task->tsk_project->tsk_team->short_name }}</span>
                </div>
                
                @if($task->end_date)
                    <span class="text-xs {{ $task->isOverdue() ? 'text-red-600' : '' }}">
                        <i class="icon-calendar mr-1"></i>{{ \Carbon\Carbon::parse($task->end_date)->format('d M') }}
                    </span>
                @endif
            </div>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @if($task->tsk_type)
                        <span class="text-xs px-2 py-1 bg-neutral-100 dark:bg-neutral-700 rounded">
                            {{ $task->tsk_type->name }}
                        </span>
                    @endif
                    @if($task->estimated_hours)
                        <span class="text-xs text-neutral-500">
                            <i class="icon-clock mr-1"></i>{{ $task->estimated_hours }}h
                        </span>
                    @endif
                </div>
                
                <div class="flex items-center gap-2">
                    @if($task->assignee)
                        <x-user-avatar :user="$task->assignee" size="sm" />
                    @else
                        <span class="text-xs text-neutral-400">{{ __('Belum ditugaskan') }}</span>
                    @endif
                </div>
            </div>
            
            <!-- Actions (shown on hover) -->
            <div class="mt-3 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                <x-text-button size="xs" class="text-neutral-400 hover:text-caldy-600">
                    <i class="icon-edit-2 mr-1"></i>{{ __('Edit') }}
                </x-text-button>
                <x-text-button size="xs" class="text-neutral-400 hover:text-red-600">
                    <i class="icon-trash-2 mr-1"></i>{{ __('Hapus') }}
                </x-text-button>
            </div>
        </div>
    </div>
</div>