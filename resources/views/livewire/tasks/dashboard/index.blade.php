<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\TskProject;
use App\Models\TskItem;
use App\Models\TskTeam;
use Carbon\Carbon;

new #[Layout('layouts.app')]
class extends Component {
    
    public function with(): array
    {
        $user = auth()->user();
        
        // Get user's teams
        $userTeams = $user->tsk_teams()->pluck('tsk_teams.id');
        
        // Calculate statistics
        $stats = [
            'total_projects' => TskProject::whereIn('tsk_team_id', $userTeams)->count(),
            'active_projects' => TskProject::whereIn('tsk_team_id', $userTeams)->where('status', 'active')->count(),
            'total_tasks' => TskItem::whereHas('tsk_project', function ($q) use ($userTeams) {
                $q->whereIn('tsk_team_id', $userTeams);
            })->count(),
            'my_tasks' => TskItem::where('assigned_to', $user->id)->count(),
            'pending_tasks' => TskItem::where('assigned_to', $user->id)->whereIn('status', ['todo', 'in_progress'])->count(),
            'overdue_tasks' => TskItem::where('assigned_to', $user->id)
                ->where('due_date', '<', Carbon::now())
                ->whereIn('status', ['todo', 'in_progress'])
                ->count(),
        ];
        
        // Recent tasks assigned to user
        $recentTasks = TskItem::with(['tsk_project.tsk_team', 'creator'])
            ->where('assigned_to', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
            
        // User's teams with basic info
        $teams = TskTeam::whereIn('id', $userTeams)
            ->withCount(['tsk_projects', 'activeAuths'])
            ->get();
            
        // Recent projects from user's teams
        $recentProjects = TskProject::with(['tsk_team', 'user'])
            ->whereIn('tsk_team_id', $userTeams)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        return [
            'stats' => $stats,
            'recent_tasks' => $recentTasks,
            'teams' => $teams,
            'recent_projects' => $recentProjects,
        ];
    }
    
    public function getStatusColor($status): string
    {
        return match($status) {
            'todo' => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200',
            'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
            'review' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
            'done' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
            default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200'
        };
    }
    
    public function getStatusLabel($status): string
    {
        return match($status) {
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'review' => 'Review',
            'done' => 'Done',
            default => ucfirst($status)
        };
    }
    
    public function getPriorityColor($priority): string
    {
        return match($priority) {
            'low' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
            'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
            'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100',
            'urgent' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
            default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200'
        };
    }
    
    public function getPriorityLabel($priority): string
    {
        return match($priority) {
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
            default => ucfirst($priority)
        };
    }
};

?>

<x-slot name="title">{{ __('Dasbor') . ' — ' . __('Tugas') }}</x-slot>
<x-slot name="header">
    <x-nav-task>{{ __('Dasbor') }}</x-nav-task>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div class="px-6 space-y-8">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                <i class="icon-briefcase text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Total Proyek') }}</dt>
                                <dd class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ $stats['total_projects'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                <i class="icon-play text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Proyek Aktif') }}</dt>
                                <dd class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ $stats['active_projects'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                <i class="icon-check-square text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Tugas Saya') }}</dt>
                                <dd class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ $stats['my_tasks'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                <i class="icon-clock text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Terlambat') }}</dt>
                                <dd class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ $stats['overdue_tasks'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Recent Tasks -->
            <div class="bg-white dark:bg-neutral-800 shadow rounded-lg">
                <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Tugas Terbaru') }}</h3>
                </div>
                <div class="p-6">
                    @if($recent_tasks->count() > 0)
                        <div class="space-y-4">
                            @foreach($recent_tasks as $task)
                            <div class="flex items-center justify-between p-3 bg-neutral-50 dark:bg-neutral-700 rounded-md">
                                <div class="flex-1">
                                    <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $task->title }}</div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $task->tsk_project->name }} • {{ $task->tsk_project->tsk_team->short_name }}
                                    </div>
                                    @if($task->due_date)
                                    <div class="text-xs text-neutral-500 mt-1">
                                        {{ __('Deadline: ') }}{{ $task->due_date->format('d M Y') }}
                                    </div>
                                    @endif
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $this->getStatusColor($task->status) }}">
                                        {{ $this->getStatusLabel($task->status) }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $this->getPriorityColor($task->priority) }}">
                                        {{ $this->getPriorityLabel($task->priority) }}
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('tasks.items.index') }}" class="text-sm text-caldy-600 hover:text-caldy-500 font-medium">
                                {{ __('Lihat semua tugas') }} →
                            </a>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="icon-check-square text-4xl text-neutral-300 dark:text-neutral-600 mb-4"></i>
                            <p class="text-neutral-500">{{ __('Belum ada tugas yang ditugaskan kepada Anda.') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Teams & Recent Projects -->
            <div class="bg-white dark:bg-neutral-800 shadow rounded-lg">
                <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Tim & Proyek') }}</h3>
                </div>
                <div class="p-6">
                    @if($teams->count() > 0)
                        <!-- Teams -->
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-3">{{ __('Tim Anda') }}</h4>
                            <div class="space-y-2">
                                @foreach($teams as $team)
                                <div class="flex items-center justify-between p-2 bg-neutral-50 dark:bg-neutral-700 rounded">
                                    <div>
                                        <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $team->name }}</div>
                                        <div class="text-xs text-neutral-500">{{ $team->short_name }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $team->tsk_projects_count }}</div>
                                        <div class="text-xs text-neutral-500">{{ __('proyek') }}</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Recent Projects -->
                        @if($recent_projects->count() > 0)
                        <div>
                            <h4 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-3">{{ __('Proyek Terbaru') }}</h4>
                            <div class="space-y-2">
                                @foreach($recent_projects as $project)
                                <div class="p-2 bg-neutral-50 dark:bg-neutral-700 rounded">
                                    <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $project->name }}</div>
                                    <div class="text-xs text-neutral-500">
                                        {{ $project->tsk_team->short_name }} • {{ __('oleh ') }}{{ $project->user->name }}
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('tasks.projects.index') }}" class="text-sm text-caldy-600 hover:text-caldy-500 font-medium">
                                    {{ __('Lihat semua proyek') }} →
                                </a>
                            </div>
                        </div>
                        @endif
                    @else
                        <div class="text-center py-8">
                            <i class="icon-users text-4xl text-neutral-300 dark:text-neutral-600 mb-4"></i>
                            <p class="text-neutral-500">{{ __('Anda belum menjadi anggota tim manapun.') }}</p>
                            <p class="text-sm text-neutral-400 mt-2">{{ __('Hubungi administrator untuk bergabung dengan tim.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>