<?php
// File: resources/views/livewire/tasks/items/index.blade.php
// Complete replacement with Tabulator Excel-like interface

use function Livewire\Volt\{layout, state, computed, on, mount};
use App\Models\TskItem;
use App\Models\TskProject;
use App\Models\TskType;
use App\Models\TskTeam;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

layout('layouts.app');

// State for filters
state([
    'search' => '',
    'project_filter' => '',
    'type_filter' => '', 
    'status_filter' => '',
    'priority_filter' => '',
    'assignee_filter' => '',
    'scope_filter' => 'team', // team, assigned, created
]);

// Get policy-filtered projects
$projects = computed(function () {
    $allProjects = TskProject::with('tsk_team')
        ->where('status', 'active')
        ->get();
    
    return $allProjects->filter(function ($project) {
        return Gate::allows('create', [TskItem::class, $project]);
    })->values();
});

// Get accessible teams for current user
$teams = computed(function () {
    if (Auth::id() === 1) { // Superuser
        return TskTeam::where('is_active', true)->get();
    }
    
    return Auth::user()->tsk_teams()
        ->where('is_active', true)
        ->get();
});

// Get active task types
$taskTypes = computed(function () {
    return TskType::where('is_active', true)
        ->orderBy('name')
        ->get();
});

// Get team members for assignment
$users = computed(function () {
    $teamIds = $this->teams->pluck('id');
    
    return User::whereHas('tsk_teams', function ($query) use ($teamIds) {
        $query->whereIn('tsk_teams.id', $teamIds);
    })
    ->select('id', 'name', 'emp_id')
    ->orderBy('name')
    ->get();
});

// Check if user can assign tasks
$canAssign = computed(function () {
    foreach ($this->teams as $team) {
        $auth = $team->tsk_auths()->where('user_id', Auth::id())->first();
        if ($auth && $auth->hasPermission('task-assign')) {
            return true;
        }
    }
    return false;
});

// Get filtered tasks
$tasks = computed(function () {
    $query = TskItem::with(['tsk_project.tsk_team', 'tsk_type', 'creator', 'assignee'])
        ->whereHas('tsk_project', function ($q) {
            $teamIds = $this->teams->pluck('id');
            $q->whereIn('tsk_team_id', $teamIds);
            
            if ($this->project_filter) {
                $q->where('id', $this->project_filter);
            }
        });

    // Apply search
    if ($this->search) {
        $query->where(function ($q) {
            $q->where('title', 'like', '%' . $this->search . '%')
              ->orWhere('desc', 'like', '%' . $this->search . '%');
        });
    }

    // Apply type filter
    if ($this->type_filter) {
        $query->where('tsk_type_id', $this->type_filter);
    }

    // Apply status filter
    if ($this->status_filter) {
        $query->where('status', $this->status_filter);
    }

    // Apply priority filter
    if ($this->priority_filter) {
        $query->where('priority', $this->priority_filter);
    }

    // Apply assignee filter
    if ($this->assignee_filter) {
        $query->where('assigned_to', $this->assignee_filter);
    }

    // Apply scope filter
    switch ($this->scope_filter) {
        case 'assigned':
            $query->where('assigned_to', Auth::id());
            break;
        case 'created':
            $query->where('created_by', Auth::id());
            break;
        // 'team' is default - no additional filter needed
    }

    return $query->orderBy('created_at', 'desc')->get();
});

// Task statistics
$taskStats = computed(function () {
    $tasks = $this->tasks;
    return [
        'total' => $tasks->count(),
        'todo' => $tasks->where('status', 'todo')->count(),
        'in_progress' => $tasks->where('status', 'in_progress')->count(),
        'review' => $tasks->where('status', 'review')->count(),
        'done' => $tasks->where('status', 'done')->count(),
    ];
});

// Create new task via AJAX
$createTask = function ($data) {
    try {
        // Find project and authorize
        $project = TskProject::findOrFail($data['project_id']);
        Gate::authorize('create', [TskItem::class, $project]);
        
        // Validate required fields (based on migration constraints)
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Judul wajib diisi'];
        }
        if (empty($data['project_id'])) {
            return ['success' => false, 'message' => 'Proyek wajib dipilih'];
        }
        if (empty($data['start_date'])) {
            return ['success' => false, 'message' => 'Tanggal mulai wajib diisi'];
        }
        if (empty($data['end_date'])) {
            return ['success' => false, 'message' => 'Tanggal selesai wajib diisi'];
        }
        if (empty($data['type_id'])) {
            return ['success' => false, 'message' => 'Tipe tugas wajib dipilih'];
        }
        
        // Create task
        $task = new TskItem();
        $task->title = trim($data['title']);
        $task->desc = trim($data['description'] ?? '');
        $task->tsk_project_id = $data['project_id'];
        $task->tsk_type_id = $data['type_id']; // Required field
        $task->status = $data['status'] ?? 'todo';
        $task->priority = $data['priority'] ?? 'medium'; // Add priority field
        $task->start_date = $data['start_date'];
        $task->end_date = $data['end_date']; // Required field
        $task->estimated_hours = $data['estimated_hours'] ?: null;
        $task->created_by = Auth::id();
        
        // Handle assignment if provided
        if (!empty($data['assigned_to'])) {
            $assignee = User::find($data['assigned_to']);
            if ($assignee) {
                Gate::authorize('assign', [$task, $assignee]);
                $task->assigned_to = $assignee->id;
            }
        }
        
        $task->save();
        
        // Reload task with relationships for proper display
        $task->load(['tsk_project.tsk_team', 'tsk_type', 'creator', 'assignee']);
        
        // Return success with formatted task data
        return [
            'success' => true,
            'message' => 'Tugas berhasil dibuat',
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->desc,
                'project_id' => $task->tsk_project_id,
                'type_id' => $task->tsk_type_id,
                'assigned_to' => $task->assigned_to,
                'status' => $task->status,
                'priority' => $task->priority,
                'start_date' => $task->start_date,
                'end_date' => $task->end_date,
                'estimated_hours' => $task->estimated_hours,
                'created_at' => $task->created_at->format('Y-m-d'),
                // Add relationship data for proper display
                'tsk_project' => $task->tsk_project,
                'tsk_type' => $task->tsk_type,
                'assignee' => $task->assignee
            ]
        ];
        
    } catch (\Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
};

// Update existing task
$updateTask = function ($taskId, $data) {
    try {
        $task = TskItem::findOrFail($taskId);
        Gate::authorize('update', $task);
        
        // Validate required fields (based on migration constraints)
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Judul wajib diisi'];
        }
        if (empty($data['start_date'])) {
            return ['success' => false, 'message' => 'Tanggal mulai wajib diisi'];
        }
        if (empty($data['end_date'])) {
            return ['success' => false, 'message' => 'Tanggal selesai wajib diisi'];
        }
        if (empty($data['type_id'])) {
            return ['success' => false, 'message' => 'Tipe tugas wajib dipilih'];
        }
        
        // Update fields
        $task->title = trim($data['title']);
        $task->desc = trim($data['description'] ?? '');
        $task->status = $data['status'];
        $task->priority = $data['priority'] ?? 'medium';
        $task->start_date = $data['start_date']; 
        $task->end_date = $data['end_date']; // Required field
        $task->estimated_hours = $data['estimated_hours'] ?: null;
        $task->tsk_type_id = $data['type_id']; // Required field
        
        // Handle assignment change
        if (isset($data['assigned_to'])) {
            if ($data['assigned_to']) {
                $assignee = User::find($data['assigned_to']);
                if ($assignee) {
                    Gate::authorize('assign', [$task, $assignee]);
                    $task->assigned_to = $assignee->id;
                }
            } else {
                $task->assigned_to = null;
            }
        }
        
        $task->save();
        
        return ['success' => true, 'message' => 'Tugas berhasil disimpan'];
        
    } catch (\Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
};

// Delete task
$deleteTask = function ($taskId) {
    try {
        $task = TskItem::findOrFail($taskId);
        Gate::authorize('delete', $task);
        
        $task->delete();
        
        return ['success' => true, 'message' => 'Tugas berhasil dihapus'];
        
    } catch (\Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
};

// Clear all filters
$resetFilters = function () {
    $this->search = '';
    $this->project_filter = '';
    $this->type_filter = '';
    $this->status_filter = '';
    $this->priority_filter = '';
    $this->assignee_filter = '';
    $this->scope_filter = 'team';
};

// Refresh data
$refreshData = function () {
    // Force recompute of all computed properties
    unset($this->tasks, $this->projects, $this->taskTypes, $this->users);
    
    // Let Livewire update, the JS will handle reloading table data
    $this->js('toast("Data berhasil diperbarui", { type: "info" });');
};

?>

<div id="content" class="relative py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Tugas') }}
            </h1>
            <p class="text-neutral-600 dark:text-neutral-400">
                Kelola tugas dalam format spreadsheet yang familiar
            </p>
        </div>
        
        <div class="flex items-center space-x-3">
            <button wire:click="refreshData" 
                    class="px-3 py-2 text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
                    title="Refresh data">
                <i class="icon-refresh"></i>
            </button>
            
            <button onclick="taskTable.addNewRow()" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                <i class="icon-plus mr-2"></i>
                {{ __('Tambah Baris') }}
            </button>
        </div>
    </div>

    {{-- Statistics Bar --}}
    <div class="bg-transparent mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-6 text-sm">
                <div class="flex items-center">
                    <span class="text-neutral-500 mr-2">Total:</span>
                    <span class="font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ $this->taskStats['total'] }}
                    </span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-gray-400 rounded-full mr-2"></span>
                    <span class="text-neutral-600 dark:text-neutral-400">
                        Todo: {{ $this->taskStats['todo'] }}
                    </span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-blue-400 rounded-full mr-2"></span>
                    <span class="text-neutral-600 dark:text-neutral-400">
                        Proses: {{ $this->taskStats['in_progress'] }}
                    </span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-yellow-400 rounded-full mr-2"></span>
                    <span class="text-neutral-600 dark:text-neutral-400">
                        Review: {{ $this->taskStats['review'] }}
                    </span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-green-400 rounded-full mr-2"></span>
                    <span class="text-neutral-600 dark:text-neutral-400">
                        Selesai: {{ $this->taskStats['done'] }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar (LDC-inspired 3-section layout) --}}
    <div class="bg-white dark:bg-neutral-800 rounded-lg shadow mb-6 p-4">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-end">
            {{-- Section 1: Search + Project --}}
            <div class="lg:col-span-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                        {{ __('Cari tugas') }}
                    </label>
                    <input type="text" wire:model.live.debounce.300ms="search" 
                           placeholder="Judul atau deskripsi..."
                           class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700">
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                        {{ __('Proyek') }}
                    </label>
                    <select wire:model.live="project_filter" 
                            class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700">
                        <option value="">Semua proyek</option>
                        @foreach($this->projects as $project)
                            <option value="{{ $project->id }}">
                                {{ $project->name }} ({{ $project->tsk_team->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Vertical Separator --}}
            <div class="hidden lg:block lg:col-span-0">
                <div class="w-px h-16 bg-neutral-200 dark:bg-neutral-700 mx-auto"></div>
            </div>

            {{-- Section 2: 2x2 Grid --}}
            <div class="lg:col-span-4 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                        {{ __('Tipe') }}
                    </label>
                    <select wire:model.live="type_filter" 
                            class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700">
                        <option value="">Semua tipe</option>
                        @foreach($this->taskTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                        {{ __('Filter') }}
                    </label>
                    <select wire:model.live="scope_filter" 
                            class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700">
                        <option value="team">Tim saya</option>
                        <option value="assigned">Ditugaskan ke saya</option>
                        <option value="created">Dibuat oleh saya</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                        {{ __('Status') }}
                    </label>
                    <select wire:model.live="status_filter" 
                            class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700">
                        <option value="">Semua status</option>
                        <option value="todo">Todo</option>
                        <option value="in_progress">Dalam Proses</option>
                        <option value="review">Review</option>
                        <option value="done">Selesai</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                        {{ __('Prioritas') }}
                    </label>
                    <select wire:model.live="priority_filter" 
                            class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700">
                        <option value="">Semua prioritas</option>
                        <option value="low">Rendah</option>
                        <option value="medium">Sedang</option>
                        <option value="high">Tinggi</option>
                        <option value="urgent">Mendesak</option>
                    </select>
                </div>
            </div>

            {{-- Vertical Separator --}}
            <div class="hidden lg:block lg:col-span-0">
                <div class="w-px h-16 bg-neutral-200 dark:bg-neutral-700 mx-auto"></div>
            </div>

            {{-- Section 3: Actions --}}
            <div class="lg:col-span-3 flex justify-end">
                <button wire:click="resetFilters" 
                        class="px-4 py-2 text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100 border border-neutral-300 dark:border-neutral-600 rounded-md">
                    {{ __('Reset') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Spreadsheet Table --}}
    <div class="bg-white dark:bg-neutral-800 rounded-lg shadow">
        <div class="p-4 border-b border-neutral-200 dark:border-neutral-700">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        Spreadsheet Tugas
                    </h3>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">
                        Klik sel untuk mengedit • <kbd class="px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded text-xs">Ctrl+N</kbd> baris baru • 
                        <kbd class="px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded text-xs">Ctrl+S</kbd> simpan semua
                    </p>
                </div>
                
                <div class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ number_format($this->tasks->count()) }} tugas
                </div>
            </div>
        </div>
        
        {{-- Tabulator container --}}
        <div id="task-table-container" class="p-4" style="min-height: 400px;"></div>
    </div>

    {{-- Initialize Tabulator on page load --}}
    <script>
        let taskTableInstance = null;
        
        function initializeTaskTable() {
            // Destroy existing instance first
            if (taskTableInstance) {
                taskTableInstance.destroy();
                taskTableInstance = null;
            }
            
            // Wait for DOM to be ready
            if (typeof TaskTable !== 'undefined' && document.getElementById('task-table-container')) {
                taskTableInstance = new TaskTable('#task-table-container', {
                    componentId: '{{ $this->getId() }}',
                    teams: @json($this->teams),
                    projects: @json($this->projects->toArray()),
                    taskTypes: @json($this->taskTypes->toArray()),
                    users: @json($this->users->toArray()),
                    canAssign: {{ $this->canAssign ? 'true' : 'false' }}
                });
                
                // Load existing tasks
                taskTableInstance.loadTasks(@json($this->tasks->toArray()));
            }
        }
        
        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            initializeTaskTable();
        });
        
        // Handle Livewire navigation
        document.addEventListener('livewire:navigated', function() {
            setTimeout(initializeTaskTable, 100);
        });
        
        // Handle Livewire updates (when filters change)
        document.addEventListener('livewire:updated', function() {
            // Only reinitialize if the data actually changed
            if (taskTableInstance && taskTableInstance.isTableReady) {
                taskTableInstance.loadTasks(@json($this->tasks->toArray()));
            }
        });
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (taskTableInstance) {
                taskTableInstance.destroy();
                taskTableInstance = null;
            }
        });
        
        // Make taskTable globally available for button clicks
        window.taskTable = {
            addNewRow: function() {
                if (taskTableInstance) {
                    taskTableInstance.addNewRow();
                } else {
                    console.warn('Task table not initialized yet');
                }
            }
        };
    </script>
</div>