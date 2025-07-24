@props(['task'])

<div class="group relative bg-white dark:bg-neutral-800 rounded-xl overflow-hidden transition-all duration-300 ease-out hover:shadow-2xl hover:shadow-blue-500/10 hover:-translate-y-1 hover:scale-[1.02] border border-neutral-200/60 dark:border-neutral-700/60 backdrop-blur-sm">
    <!-- Status gradient bar with glow effect -->
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-{{ $task->getStatusColor() }}-400 to-{{ $task->getStatusColor() }}-600 shadow-lg shadow-{{ $task->getStatusColor() }}-500/50"></div>
    
    <!-- Glass morphism overlay -->
    <div class="absolute inset-0 bg-gradient-to-br from-white/5 to-transparent pointer-events-none"></div>
    
    <div class="relative p-6">
        <!-- Header Section -->
        <div class="flex justify-between items-start mb-4">
            <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-lg text-neutral-900 dark:text-white mb-2 leading-tight">
                    <x-link href="#" class="hover:text-transparent hover:bg-clip-text hover:bg-gradient-to-r hover:from-blue-600 hover:to-purple-600 transition-all duration-300">
                        {{ $task->title }}
                    </x-link>
                </h3>
                @if($task->desc)
                    <p class="text-sm text-neutral-600 dark:text-neutral-400 line-clamp-2 leading-relaxed">{{ $task->desc }}</p>
                @endif
            </div>
            <div class="ml-4 flex-shrink-0">
                <x-task-status-badge :status="$task->status" enhanced="true" />
            </div>
        </div>
        
        <!-- Project & Team Info with modern pills -->
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-neutral-100 to-neutral-50 dark:from-neutral-700 dark:to-neutral-800 rounded-full border border-neutral-200/50 dark:border-neutral-600/50">
                <i class="icon-folder text-neutral-500 text-xs"></i>
                <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ $task->tsk_project->name }}</span>
            </div>
            <div class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-md text-xs font-semibold">
                {{ $task->tsk_project->tsk_team->short_name }}
            </div>
        </div>
        
        <!-- Metadata Row -->
        <div class="flex items-center justify-between mb-4 text-sm">
            <div class="flex items-center gap-4">
                @if($task->tsk_type)
                    <div class="flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-purple-100 to-pink-100 dark:from-purple-900/30 dark:to-pink-900/30 rounded-full border border-purple-200/50 dark:border-purple-600/30">
                        <div class="w-2 h-2 rounded-full bg-gradient-to-r from-purple-500 to-pink-500"></div>
                        <span class="text-purple-700 dark:text-purple-300 font-medium">{{ $task->tsk_type->name }}</span>
                    </div>
                @endif
                @if($task->estimated_hours)
                    <div class="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                        <i class="icon-clock text-xs"></i>
                        <span class="font-medium">{{ $task->estimated_hours }}h</span>
                    </div>
                @endif
            </div>
            
            @if($task->end_date)
                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $task->isOverdue() ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' }}">
                    <i class="icon-calendar text-xs"></i>
                    <span class="text-xs font-semibold">{{ \Carbon\Carbon::parse($task->end_date)->format('M d') }}</span>
                </div>
            @endif
        </div>
        
        <!-- Assignee Section -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if($task->assignee)
                    <div class="flex items-center gap-2">
                        <x-user-avatar :user="$task->assignee" size="sm" enhanced="true" />
                        <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ $task->assignee->name }}</span>
                    </div>
                @else
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-yellow-50 dark:bg-yellow-900/20 rounded-full border border-yellow-200/50 dark:border-yellow-600/30">
                        <div class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse"></div>
                        <span class="text-yellow-700 dark:text-yellow-400 text-sm font-medium">{{ __('Belum ditugaskan') }}</span>
                    </div>
                @endif
            </div>

        </div>
        
        <!-- Hover Actions with enhanced styling -->
        <div class="mt-4 flex gap-2 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">
            <button class="flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-lg text-xs font-semibold transition-all duration-200 hover:shadow-lg hover:shadow-blue-500/25 hover:scale-105">
                <i class="icon-edit-2"></i>
                {{ __('Edit') }}
            </button>
            <button class="flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg text-xs font-semibold transition-all duration-200 hover:shadow-lg hover:shadow-red-500/25 hover:scale-105">
                <i class="icon-trash-2"></i>
                {{ __('Hapus') }}
            </button>
        </div>
    </div>
    
    <!-- Bottom accent line -->
    <div class="h-0.5 bg-gradient-to-r from-transparent via-{{ $task->getStatusColor() }}-400 to-transparent opacity-60"></div>
</div>