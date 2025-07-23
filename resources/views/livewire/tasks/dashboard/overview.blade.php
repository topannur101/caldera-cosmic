<?php

use Livewire\Volt\Component;
use App\Models\TskTeam;
use App\Models\TskProject;
use App\Models\TskItem;
use App\Models\TskAuth;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public array $stats = [];
    public array $recent_tasks = [];
    public array $recent_projects = [];
    public array $team_stats = [];

    public function mount()
    {
        $this->loadStats();
        $this->loadRecentItems();
        $this->loadTeamStats();
    }

    private function loadStats()
    {
        $user = Auth::user();
        
        // For superuser, get all stats
        if ($user->id === 1) {
            $this->stats = [
                'total_teams' => TskTeam::where('is_active', true)->count(),
                'total_projects' => TskProject::count(),
                'active_projects' => TskProject::where('status', 'active')->count(),
                'total_tasks' => TskItem::count(),
                'pending_tasks' => TskItem::whereIn('status', ['todo', 'in_progress'])->count(),
                'completed_tasks' => TskItem::where('status', 'done')->count(),
                'my_tasks' => TskItem::where('assigned_to', $user->id)->count(),
                'overdue_tasks' => TskItem::where('end_date', '<', now())
                    ->whereNotIn('status', ['done'])
                    ->count(),
            ];
        } else {
            // For regular users, get their team-specific stats
            $user_teams = $user->tsk_auths()->where('is_active', true)->pluck('tsk_team_id');
            
            $this->stats = [
                'my_teams' => $user_teams->count(),
                'my_projects' => TskProject::whereIn('tsk_team_id', $user_teams)->count(),
                'active_projects' => TskProject::whereIn('tsk_team_id', $user_teams)
                    ->where('status', 'active')->count(),
                'my_tasks' => TskItem::where('assigned_to', $user->id)->count(),
                'pending_tasks' => TskItem::where('assigned_to', $user->id)
                    ->whereIn('status', ['todo', 'in_progress'])->count(),
                'completed_tasks' => TskItem::where('assigned_to', $user->id)
                    ->where('status', 'done')->count(),
                'created_tasks' => TskItem::where('created_by', $user->id)->count(),
                'overdue_tasks' => TskItem::where('assigned_to', $user->id)
                    ->where('end_date', '<', now())
                    ->whereNotIn('status', ['done'])
                    ->count(),
            ];
        }
    }

    private function loadRecentItems()
    {
        $user = Auth::user();

        // Recent tasks assigned to user
        $this->recent_tasks = TskItem::with(['tsk_project.tsk_team', 'assignee', 'creator'])
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['done'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        // Recent projects (user teams or all for superuser)
        if ($user->id === 1) {
            $this->recent_projects = TskProject::with(['tsk_team', 'user'])
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->toArray();
        } else {
            $user_teams = $user->tsk_auths()->where('is_active', true)->pluck('tsk_team_id');
            
            $this->recent_projects = TskProject::with(['tsk_team', 'user'])
                ->whereIn('tsk_team_id', $user_teams)
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->toArray();
        }
    }

    private function loadTeamStats()
    {
        $user = Auth::user();

        if ($user->id === 1) {
            // Superuser sees all teams
            $teams = TskTeam::withCount(['tsk_projects', 'tsk_auths'])
                ->where('is_active', true)
                ->get();
        } else {
            // Regular user sees their teams
            $user_team_ids = $user->tsk_auths()->where('is_active', true)->pluck('tsk_team_id');
            $teams = TskTeam::withCount(['tsk_projects', 'tsk_auths'])
                ->whereIn('id', $user_team_ids)
                ->where('is_active', true)
                ->get();
        }

        $this->team_stats = $teams->map(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'short_name' => $team->short_name,
                'projects_count' => $team->tsk_projects_count,
                'members_count' => $team->tsk_auths_count,
                'active_tasks' => TskItem::whereHas('tsk_project', function ($q) use ($team) {
                    $q->where('tsk_team_id', $team->id);
                })->whereNotIn('status', ['done'])->count(),
            ];
        })->toArray();
    }

    public function getTaskStatusColor($status)
    {
        return match($status) {
            'todo' => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200',
            'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
            'review' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
            'done' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
            default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200'
        };
    }

    public function getTaskStatusLabel($status)
    {
        return match($status) {
            'todo' => 'Belum',
            'in_progress' => 'Berlangsung',
            'review' => 'Tinjauan',
            'done' => 'Selesai',
            default => ucfirst($status)
        };
    }

    public function getPriorityColor($priority)
    {
        return match($priority) {
            'low' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
            'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
            'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100',
            'urgent' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
            default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200'
        };
    }

    public function getPriorityLabel($priority)
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

<div class="px-8">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @foreach($stats as $key => $value)
            <div class="bg-white dark:bg-neutral-800 p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">
                            {{ match($key) {
                                'total_teams' => 'Total Tim',
                                'my_teams' => 'Tim Saya',
                                'total_projects' => 'Total Proyek',
                                'my_projects' => 'Proyek Saya',
                                'active_projects' => 'Proyek Aktif',
                                'total_tasks' => 'Total Tugas',
                                'my_tasks' => 'Tugas Saya',
                                'pending_tasks' => 'Tugas Tertunda',
                                'completed_tasks' => 'Tugas Selesai',
                                'created_tasks' => 'Tugas Dibuat',
                                'overdue_tasks' => 'Tugas Terlambat',
                                default => ucfirst(str_replace('_', ' ', $key))
                            } }}
                        </p>
                        <p class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $value }}</p>
                    </div>
                    <div class="ml-4">
                        <i class="{{ match($key) {
                            'total_teams', 'my_teams' => 'icon-users',
                            'total_projects', 'my_projects', 'active_projects' => 'icon-folder',
                            'total_tasks', 'my_tasks', 'created_tasks' => 'icon-check-square',
                            'pending_tasks' => 'icon-clock',
                            'completed_tasks' => 'icon-check-circle',
                            'overdue_tasks' => 'icon-alert-triangle',
                            default => 'icon-bar-chart'
                        } }} text-2xl text-caldy-500"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Tasks -->
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow">
            <div class="p-6 border-b border-neutral-200 dark:border-neutral-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Tugas Terbaru') }}</h3>
                    <a href="{{ route('tasks.items.index') }}" wire:navigate class="text-sm text-caldy-600 hover:text-caldy-700">
                        {{ __('Lihat Semua') }}
                    </a>
                </div>
            </div>
            <div class="p-6">
                @if(empty($recent_tasks))
                    <p class="text-neutral-500 text-center py-4">{{ __('Belum ada tugas yang ditugaskan') }}</p>
                @else
                    <div class="space-y-4">
                        @foreach($recent_tasks as $task)
                            <div class="flex items-center justify-between p-3 border border-neutral-200 dark:border-neutral-700 rounded">
                                <div class="flex-1">
                                    <h4 class="font-medium text-neutral-900 dark:text-neutral-100">{{ $task['title'] }}</h4>
                                    <p class="text-sm text-neutral-500">{{ $task['tsk_project']['name'] ?? 'No Project' }}</p>
                                    @if($task['end_date'])
                                        <p class="text-xs text-neutral-400">{{ __('Deadline: ') . date('d M Y', strtotime($task['end_date'])) }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $this->getTaskStatusColor($task['status']) }}">
                                        {{ $this->getTaskStatusLabel($task['status']) }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $this->getPriorityColor($task['priority']) }}">
                                        {{ $this->getPriorityLabel($task['priority']) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent Projects -->
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow">
            <div class="p-6 border-b border-neutral-200 dark:border-neutral-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Proyek Terbaru') }}</h3>
                    <a href="{{ route('tasks.projects.index') }}" wire:navigate class="text-sm text-caldy-600 hover:text-caldy-700">
                        {{ __('Lihat Semua') }}
                    </a>
                </div>
            </div>
            <div class="p-6">
                @if(empty($recent_projects))
                    <p class="text-neutral-500 text-center py-4">{{ __('Belum ada proyek') }}</p>
                @else
                    <div class="space-y-4">
                        @foreach($recent_projects as $project)
                            <div class="flex items-center justify-between p-3 border border-neutral-200 dark:border-neutral-700 rounded">
                                <div class="flex-1">
                                    <h4 class="font-medium text-neutral-900 dark:text-neutral-100">{{ $project['name'] }}</h4>
                                    <p class="text-sm text-neutral-500">{{ $project['tsk_team']['name'] ?? 'No Team' }}</p>
                                    @if($project['end_date'])
                                        <p class="text-xs text-neutral-400">{{ __('Target: ') . date('d M Y', strtotime($project['end_date'])) }}</p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $this->getPriorityColor($project['priority']) }}">
                                        {{ $this->getPriorityLabel($project['priority']) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Team Statistics -->
    @if(!empty($team_stats))
    <div class="mt-8">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow">
            <div class="p-6 border-b border-neutral-200 dark:border-neutral-700">
                <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Statistik Tim') }}</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($team_stats as $team)
                        <div class="p-4 border border-neutral-200 dark:border-neutral-700 rounded">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-neutral-900 dark:text-neutral-100">{{ $team['name'] }}</h4>
                                <span class="text-xs text-neutral-500">({{ $team['short_name'] }})</span>
                            </div>
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p class="text-xl font-bold text-caldy-600">{{ $team['projects_count'] }}</p>
                                    <p class="text-xs text-neutral-500">Proyek</p>
                                </div>
                                <div>
                                    <p class="text-xl font-bold text-blue-600">{{ $team['members_count'] }}</p>
                                    <p class="text-xs text-neutral-500">Anggota</p>
                                </div>
                                <div>
                                    <p class="text-xl font-bold text-orange-600">{{ $team['active_tasks'] }}</p>
                                    <p class="text-xs text-neutral-500">Tugas Aktif</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif
</div>