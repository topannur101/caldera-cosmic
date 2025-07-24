<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

use App\Models\TskItem;
use App\Models\TskProject;
use App\Models\TskTeam;
use App\Models\User;

new #[Layout('layouts.app')]
class extends Component {
    
    use WithPagination;
    
    #[Url]
    public string $search = '';
    
    #[Url]
    public string $status = '';
    
    #[Url]
    public string $type = '';
    
    #[Url]
    public string $project = '';
    
    #[Url]
    public string $assignee = '';
    
    #[Url]
    public string $view = 'list'; // list, content, grid
    
    #[Url]
    public string $filter = 'team'; // team, assigned, created
    
    public int $perPage = 20;
    
    public function mount()
    {
        // Policy-based access will be handled in with() method
    }
    
    public function with(): array
    {
        $user = Auth::user();
        
        // Get user's teams for filtering
        $userTeams = $user->tsk_teams()->where('is_active', true)->pluck('tsk_teams.id');
        
        // Build tasks query with policy-based filtering
        $tasksQuery = TskItem::query()
            ->with([
                'tsk_project.tsk_team:id,name,short_name',
                'tsk_type:id,name',
                'creator:id,name,emp_id,photo',
                'assignee:id,name,emp_id,photo'
            ]);
        
        // Apply team-based filtering (superuser sees all)
        if ($user->id !== 1) {
            // Get projects from user's teams
            $teamProjectIds = TskProject::whereIn('tsk_team_id', $userTeams)->pluck('id');
            $tasksQuery->whereIn('tsk_project_id', $teamProjectIds);
        }
        
        // Apply filter type
        switch ($this->filter) {
            case 'assigned':
                $tasksQuery->where('assigned_to', $user->id);
                break;
            case 'created':
                $tasksQuery->where('created_by', $user->id);
                break;
            // 'team' is default - shows all team tasks (already filtered above)
        }
        
        // Apply search
        if ($this->search) {
            $tasksQuery->where(function($q) {
                $q->where('title', 'LIKE', '%' . $this->search . '%')
                  ->orWhere('desc', 'LIKE', '%' . $this->search . '%');
            });
        }
        
        // Apply status filter
        if ($this->status) {
            $tasksQuery->where('status', $this->status);
        }
        
        // Apply type filter
        if ($this->type) {
            $tasksQuery->where('tsk_type_id', $this->type);
        }
        
        // Apply project filter
        if ($this->project) {
            $tasksQuery->where('tsk_project_id', $this->project);
        }
        
        // Apply assignee filter
        if ($this->assignee) {
            $tasksQuery->where('assigned_to', $this->assignee);
        }
        
        // Order by updated_at desc
        $tasksQuery->orderBy('updated_at', 'desc');
        
        // Get projects for filter dropdown (policy-filtered)
        $projectsQuery = TskProject::where('status', 'active')
            ->with('tsk_team:id,name,short_name');
            
        if ($user->id !== 1) {
            $projectsQuery->whereIn('tsk_team_id', $userTeams);
        }
        
        // Get team members for assignee filter
        $usersQuery = User::whereIn('id', function($query) use ($userTeams) {
            $query->select('user_id')
                  ->from('tsk_auths')
                  ->whereIn('tsk_team_id', $userTeams)
                  ->where('is_active', true);
        });
        
        if ($user->id === 1) {
            // Superuser sees all users with task auths
            $usersQuery = User::whereHas('tsk_auths');
        }
        
        // Get task types
        $taskTypes = \App\Models\TskType::where('is_active', true)->orderBy('name')->get();
        
        return [
            'tasks' => $tasksQuery->paginate($this->perPage),
            'projects' => $projectsQuery->get(),
            'users' => $usersQuery->orderBy('name')->get(),
            'task_types' => $taskTypes,
            'can_create' => Gate::allows('create', TskItem::class),
            'task_counts' => [
                'total' => $tasksQuery->count(),
                'todo' => (clone $tasksQuery)->where('status', 'todo')->count(),
                'in_progress' => (clone $tasksQuery)->where('status', 'in_progress')->count(),
                'review' => (clone $tasksQuery)->where('status', 'review')->count(),
                'done' => (clone $tasksQuery)->where('status', 'done')->count(),
            ]
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
    
    public function updatedType()
    {
        $this->resetPage();
    }
    
    public function updatedProject()
    {
        $this->resetPage();
    }
    
    public function updatedAssignee()
    {
        $this->resetPage();
    }
    
    public function updatedFilter()
    {
        $this->resetPage();
    }
    
    public function loadMore()
    {
        $this->perPage += 20;
    }
    
    public function clearFilters()
    {
        $this->reset(['search', 'status', 'project', 'assignee', 'type']);
        $this->resetPage();
    }
    
    #[On('task-created')]
    public function refresh()
    {
        // Refresh the component when a task is created
    }
};

?>

<x-slot name="title">{{ __('Tugas') }}</x-slot>

@auth
    <x-slot name="header">
        <x-nav-task></x-nav-task>
    </x-slot>
@endauth


<div class="relative py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
        
    <!-- Filters Card (White Background) -->
    <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
        <div>
            <div class="grid gap-3">
                <div>
                    <label for="task-search" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                        {{ __('Pencarian') }}
                    </label>
                    <x-text-input wire:model.live="search" 
                        id="task-search"
                        class="w-full"
                        type="search" 
                        placeholder="{{ __('Cari tugas...') }}" 
                        autocomplete="off" />
                </div>
                <div>
                    <label for="task-project" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                        {{ __('Proyek') }}
                    </label>
                    <x-select wire:model.live="project" id="task-project" class="w-full">
                        <option value="">{{ __('Semua proyek') }}</option>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}">{{ $proj->name }} ({{ $proj->tsk_team->short_name }})</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
        </div>
        <!-- Vertical Separator -->
        <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-3 lg:my-0"></div>
        <!-- Section 2: Type & Filter, Status & Assignee -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="task-type" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                    {{ __('Tipe') }}
                </label>
                <x-select wire:model.live="type" id="task-type" class="w-full">
                    <option value="">{{ __('Semua tipe') }}</option>
                    @foreach($task_types as $taskType)
                        <option value="{{ $taskType->id }}">{{ $taskType->name }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <label for="task-filter" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                    {{ __('Filter') }}
                </label>
                <x-select wire:model.live="filter" id="task-filter" class="w-full">
                    <option value="team">{{ __('Tim saya') }}</option>
                    <option value="assigned">{{ __('Ditugaskan ke saya') }}</option>
                    <option value="created">{{ __('Dibuat saya') }}</option>
                </x-select>
            </div>
            <div>
                <label for="task-status" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                    {{ __('Status') }}
                </label>
                <x-select wire:model.live="status" id="task-status" class="w-full">
                    <option value="">{{ __('Semua status') }}</option>
                    <option value="todo">{{ __('To Do') }}</option>
                    <option value="in_progress">{{ __('Dalam Proses') }}</option>
                    <option value="review">{{ __('Review') }}</option>
                    <option value="done">{{ __('Selesai') }}</option>
                </x-select>
            </div>
            <div>
                <label for="task-assignee" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                    {{ __('Penugasan') }}
                </label>
                <x-select wire:model.live="assignee" id="task-assignee" class="w-full">
                    <option value="">{{ __('Semua penugasan') }}</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </x-select>
            </div>
        </div>
        <!-- Vertical Separator -->
        <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-3 lg:my-0"></div>
        <!-- Section 3: Actions -->
        <div class="grow flex justify-end items-center gap-3">
            <div class="grow flex justify-center">
                @if($can_create)
                    <x-primary-button x-on:click.prevent="$dispatch('open-slide-over', 'task-create'); $dispatch('task-create')">
                        <i class="icon-plus mr-2"></i>{{ __('Tugas Baru') }}
                    </x-primary-button>
                @endif
            </div>
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <x-text-button><i class="icon-ellipsis"></i></x-text-button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-link href="#" wire:click.prevent="clearFilters">
                        <i class="icon-rotate-cw mr-2"></i>{{ __('Reset filter') }}
                    </x-dropdown-link>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
    
    <!-- Stats Bar (Transparent Background) -->
    <div class="flex items-center justify-between my-6 px-1">
        <div class="text-sm text-neutral-600 dark:text-neutral-400">
            {{ $tasks->total() . ' ' . __('tugas') }} • 
            <span class="text-neutral-400 dark:text-neutral-600">{{ $task_counts['todo'] }} todo</span> • 
            <span class="text-blue-600">{{ $task_counts['in_progress'] }} proses</span> • 
            <span class="text-yellow-600">{{ $task_counts['review'] }} review</span> • 
            <span class="text-green-600">{{ $task_counts['done'] }} selesai</span>
        </div>
        <div class="btn-group">
            <x-radio-button wire:model.live="view" value="list" name="view" id="view-list">
                <i class="icon-align-justify text-center m-auto"></i>
            </x-radio-button>
            <x-radio-button wire:model.live="view" value="content" name="view" id="view-content">
                <i class="icon-layout-list text-center m-auto"></i>
            </x-radio-button>
            <x-radio-button wire:model.live="view" value="grid" name="view" id="view-grid">
                <i class="icon-layout-grid text-center m-auto"></i>
            </x-radio-button>
        </div>
    </div>

    <!-- Data Display (Transparent Background) -->
    @if (!$tasks->count())
        <div class="py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="icon-clipboard-list"></i>
            </div>
            <div class="text-center text-neutral-400 dark:text-neutral-600 mb-4">
                {{ __('Tidak ada tugas ditemukan') }}
            </div>
            @if($can_create)
                <div class="text-center">
                    <x-primary-button x-on:click.prevent="$dispatch('open-slide-over', 'task-create'); $dispatch('task-create')">
                        <i class="icon-plus mr-2"></i>{{ __('Buat Tugas Pertama') }}
                    </x-primary-button>
                </div>
            @endif
        </div>
    @else
        @switch($view)
            @case('grid')
                <div wire:key="grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($tasks as $task)
                        <x-task-card-grid :task="$task" />
                    @endforeach
                </div>
            @break

            @case('list')
                <div wire:key="list" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto">
                    <table class="text-neutral-600 dark:text-neutral-400 w-full table text-sm [&_th]:text-center [&_th]:px-2 [&_th]:py-3 [&_td]:px-2 [&_td]:py-1">
                        <tr class="uppercase text-xs">
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Tugas') }}</th>
                            <th>{{ __('Proyek') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Tipe') }}</th>
                            <th>{{ __('Penugasan') }}</th>
                            <th>{{ __('Deadline') }}</th>
                            <th>{{ __('Jam') }}</th>
                            <th></th>
                        </tr>
                        @foreach ($tasks as $task)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50 cursor-pointer group"
                                wire:key="task-{{ $task->id }}">
                                <td class="font-mono text-xs">{{ $task->id }}</td>
                                <td>
                                    <div class="font-medium">
                                        <x-link href="#" class="hover:text-caldy-600">{{ $task->title }}</x-link>
                                    </div>
                                    @if($task->desc)
                                        <div class="text-xs text-neutral-500 truncate">{{ $task->desc }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-sm">{{ $task->tsk_project->name }}</div>
                                    <div class="text-xs text-neutral-500">{{ $task->tsk_project->tsk_team->short_name }}</div>
                                </td>
                                <td>
                                    <x-task-status-badge :status="$task->status" />
                                </td>
                                <td>
                                    @if($task->tsk_type)
                                        <span class="text-xs px-2 py-1 bg-neutral-100 dark:bg-neutral-700 rounded">
                                            {{ $task->tsk_type->name }}
                                        </span>
                                    @else
                                        <span class="text-neutral-400">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($task->assignee)
                                        <x-user-avatar :user="$task->assignee" size="sm" />
                                    @else
                                        <span class="text-neutral-400">{{ __('Belum ditugaskan') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($task->end_date)
                                        <span class="text-xs {{ $task->isOverdue() ? 'text-red-600' : 'text-neutral-600' }}">
                                            {{ \Carbon\Carbon::parse($task->end_date)->format('d M') }}
                                        </span>
                                    @else
                                        <span class="text-neutral-400">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($task->estimated_hours)
                                        <span class="text-xs text-neutral-600">{{ $task->estimated_hours }}h</span>
                                    @else
                                        <span class="text-neutral-400">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <x-text-button size="xs" class="text-neutral-400 hover:text-caldy-600">
                                            <i class="icon-edit-2"></i>
                                        </x-text-button>
                                        <x-text-button size="xs" class="text-neutral-400 hover:text-red-600">
                                            <i class="icon-trash-2"></i>
                                        </x-text-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @break

            @default
                <div wire:key="content" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach ($tasks as $task)
                        <x-task-card-content :task="$task" />
                    @endforeach
                </div>
        @endswitch

        <!-- Pagination Observer -->
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$tasks->isEmpty())
                @if ($tasks->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((tasks) => {
                                tasks.forEach(task => {
                                    if (task.isIntersecting) {
                                        @this.loadMore()
                                    }
                                })
                            })
                            observer.observe(this.$el)
                        }
                    }" x-init="observe"></div>
                    <x-spinner class="sm" />
                @else
                    <div class="mx-auto text-neutral-400">{{ __('Tidak ada lagi') }}</div>
                @endif
            @endif
        </div>
    @endif
    <!-- Slideovers -->
    <div wire:key="slideovers">
        <x-slide-over name="task-create">
            <livewire:tasks.items.create />
        </x-slide-over>
    </div>
</div>
