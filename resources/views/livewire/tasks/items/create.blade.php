<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\TskProject;
use App\Models\TskItem;
use App\Models\User;
use App\Models\TskTeam;

new class extends Component {
    
    public string $title = '';
    public string $desc = '';
    public int $tsk_project_id = 0;
    public int $assigned_to = 0;
    public string $status = 'todo';
    public string $priority = 'medium';
    public string $due_date = '';
    public int $estimated_hours = 0;
    
    // Context data
    public int $context_project_id = 0;
    public string $current_route = '';
    
    #[On('task-create')]
    public function loadContext($context = [])
    {
        $this->reset(['title', 'desc', 'tsk_project_id', 'assigned_to', 'status', 'priority', 'due_date', 'estimated_hours']);
        
        // Auto-populate project if context provided
        if (isset($context['project_id'])) {
            $this->tsk_project_id = $context['project_id'];
            $this->context_project_id = $context['project_id'];
        }
        
        $this->current_route = request()->route()->getName() ?? '';
    }
    
    public function mount()
    {
        $this->current_route = request()->route()->getName() ?? '';
    }
    
    public function with(): array
    {
        $user = auth()->user();
        
        // Load projects based on permissions
        $projects = collect();
        
        if ($user->hasTaskPermission('task-create')) {
            // User has global task-create permission, load all active projects
            $projects = TskProject::with(['tsk_team'])
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        } else {
            // Load only projects from teams where user has permissions
            $userTeamIds = $user->tsk_auths()
                ->where('is_active', true)
                ->pluck('tsk_team_id');
                
            if ($userTeamIds->isNotEmpty()) {
                $projects = TskProject::with(['tsk_team'])
                    ->whereIn('tsk_team_id', $userTeamIds)
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get();
            }
        }
        
        // Load users from the same teams as selected project
        $users = collect();
        if ($this->tsk_project_id) {
            $project = TskProject::find($this->tsk_project_id);
            if ($project) {
                $teamUserIds = $project->tsk_team->tsk_auths()
                    ->where('is_active', true)
                    ->pluck('user_id');
                    
                $users = User::whereIn('id', $teamUserIds)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }
        }
        
        // Check if user can assign tasks
        $can_assign = false;
        if ($this->tsk_project_id) {
            $project = TskProject::find($this->tsk_project_id);
            if ($project) {
                $can_assign = $user->hasTaskPermission('task-assign', $project->tsk_team_id);
            }
        } else {
            $can_assign = $user->hasTaskPermission('task-assign');
        }
        
        return [
            'projects' => $projects,
            'users' => $users,
            'can_assign' => $can_assign
        ];
    }
    
    public function updatedTskProjectId()
    {
        // Reset assigned_to when project changes
        $this->assigned_to = 0;
    }
    
    public function save()
    {
        $user = auth()->user();
        
        // Validate input
        $this->validate([
            'title' => 'required|string|max:255',
            'desc' => 'nullable|string|max:1000',
            'tsk_project_id' => 'required|exists:tsk_projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:todo,in_progress,review,done',
            'priority' => 'required|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:today',
            'estimated_hours' => 'nullable|integer|min:0|max:1000',
        ]);
        
        // Get the project to check permissions
        $project = TskProject::findOrFail($this->tsk_project_id);
        
        // Check if user can create tasks in this project
        $canCreate = false;
        if ($user->hasTaskPermission('task-create')) {
            $canCreate = true; // Global permission
        } else {
            $canCreate = $user->hasTaskPermission('task-create', $project->tsk_team_id);
        }
        
        if (!$canCreate) {
            // Check if user is at least a member of the team (can create for own team)
            $userAuth = $user->tsk_auths()
                ->where('tsk_team_id', $project->tsk_team_id)
                ->where('is_active', true)
                ->first();
                
            if (!$userAuth) {
                session()->flash('error', 'Anda tidak memiliki izin untuk membuat tugas dalam proyek ini.');
                return;
            }
        }
        
        // Check assignment permission if assigning to someone else
        if ($this->assigned_to && $this->assigned_to != $user->id) {
            if (!$user->hasTaskPermission('task-assign', $project->tsk_team_id)) {
                session()->flash('error', 'Anda tidak memiliki izin untuk menugaskan tugas kepada orang lain.');
                return;
            }
            
            // Check if assigned user is in the same team
            $assignedUserAuth = User::find($this->assigned_to)
                ->tsk_auths()
                ->where('tsk_team_id', $project->tsk_team_id)
                ->where('is_active', true)
                ->first();
                
            if (!$assignedUserAuth) {
                session()->flash('error', 'Pengguna yang ditugaskan harus menjadi anggota tim yang sama.');
                return;
            }
        }
        
        // Create the task
        $task = TskItem::create([
            'title' => $this->title,
            'desc' => $this->desc,
            'tsk_project_id' => $this->tsk_project_id,
            'created_by' => $user->id,
            'assigned_to' => $this->assigned_to ?: null,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date ?: null,
            'estimated_hours' => $this->estimated_hours ?: null,
        ]);
        
        // Close slideover
        $this->dispatch('close-slide-over', 'task-create');
        
        // Show success message
        session()->flash('message', 'Tugas "' . $this->title . '" berhasil dibuat.');
        
        // Redirect logic based on current page
        if (!str_contains($this->current_route, 'tasks.items')) {
            $this->redirect(route('tasks.items.index'), navigate: true);
        } else {
            // If already on task list, refresh the data
            $this->dispatch('task-created');
        }
    }
    
    public function cancel()
    {
        $this->reset(['title', 'desc', 'tsk_project_id', 'assigned_to', 'status', 'priority', 'due_date', 'estimated_hours']);
        $this->dispatch('close-slide-over', 'task-create');
    }
};

?>

<div class="p-6 overflow-auto">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Buat Tugas Baru') }}
        </h2>
        <x-text-button type="button" wire:click="cancel">
            <i class="icon-x"></i>
        </x-text-button>
    </div>
    
    <form wire:submit="save" class="space-y-6">
        
        <!-- Title Field -->
        <div>
            <x-input-label for="title" :value="__('Judul Tugas')" />
            <x-text-input 
                id="title" 
                wire:model="title" 
                type="text" 
                class="mt-1 block w-full" 
                placeholder="Masukkan judul tugas..."
                required 
                autofocus 
            />
            <x-input-error :messages="$errors->get('title')" class="mt-2" />
        </div>

        <!-- Description Field -->
        <div>
            <x-input-label for="desc" :value="__('Deskripsi')" />
            <textarea 
                id="desc"
                wire:model="desc"
                rows="3"
                class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                placeholder="Deskripsi tugas (opsional)..."
            ></textarea>
            <x-input-error :messages="$errors->get('desc')" class="mt-2" />
        </div>

        <!-- Project Selection -->
        <div>
            <x-input-label for="tsk_project_id" :value="__('Proyek')" />
            <select 
                id="tsk_project_id" 
                wire:model.live="tsk_project_id"
                class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                required
            >
                <option value="">{{ __('Pilih Proyek') }}</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}">
                        {{ $project->name }} ({{ $project->tsk_team->short_name }})
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('tsk_project_id')" class="mt-2" />
            
            @if($projects->isEmpty())
                <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Tidak ada proyek yang tersedia. Hubungi administrator untuk mengatur akses tim.') }}
                </p>
            @endif
        </div>

        <!-- Assignment Field (only if user can assign) -->
        @if($can_assign && $tsk_project_id)
            <div>
                <x-input-label for="assigned_to" :value="__('Ditugaskan Kepada')" />
                <select 
                    id="assigned_to" 
                    wire:model="assigned_to"
                    class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                >
                    <option value="">{{ __('Pilih Anggota Tim (Opsional)') }}</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">
                            {{ $user->name }} ({{ $user->emp_id }})
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
                
                @if($users->isEmpty() && $tsk_project_id)
                    <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Tidak ada anggota tim yang tersedia untuk proyek ini.') }}
                    </p>
                @endif
            </div>
        @endif

        <!-- Status and Priority Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Status -->
            <div>
                <x-input-label for="status" :value="__('Status')" />
                <select 
                    id="status" 
                    wire:model="status"
                    class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                    required
                >
                    <option value="todo">{{ __('To Do') }}</option>
                    <option value="in_progress">{{ __('Dalam Proses') }}</option>
                    <option value="review">{{ __('Review') }}</option>
                    <option value="done">{{ __('Selesai') }}</option>
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <!-- Priority -->
            <div>
                <x-input-label for="priority" :value="__('Prioritas')" />
                <select 
                    id="priority" 
                    wire:model="priority"
                    class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                    required
                >
                    <option value="low">{{ __('Rendah') }}</option>
                    <option value="medium">{{ __('Sedang') }}</option>
                    <option value="high">{{ __('Tinggi') }}</option>
                    <option value="urgent">{{ __('Mendesak') }}</option>
                </select>
                <x-input-error :messages="$errors->get('priority')" class="mt-2" />
            </div>
        </div>

        <!-- Due Date and Estimated Hours Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Due Date -->
            <div>
                <x-input-label for="due_date" :value="__('Tenggat Waktu')" />
                <x-text-input 
                    id="due_date" 
                    wire:model="due_date" 
                    type="date" 
                    class="mt-1 block w-full" 
                    min="{{ date('Y-m-d') }}"
                />
                <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
            </div>

            <!-- Estimated Hours -->
            <div>
                <x-input-label for="estimated_hours" :value="__('Estimasi Jam')" />
                <x-text-input 
                    id="estimated_hours" 
                    wire:model="estimated_hours" 
                    type="number" 
                    class="mt-1 block w-full" 
                    min="0"
                    max="1000"
                    placeholder="0"
                />
                <x-input-error :messages="$errors->get('estimated_hours')" class="mt-2" />
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Estimasi waktu pengerjaan dalam jam (opsional)') }}
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-3 pt-6 border-t border-neutral-200 dark:border-neutral-700">
            <x-secondary-button type="button" wire:click="cancel">
                {{ __('Batal') }}
            </x-secondary-button>
            <x-primary-button type="submit" :disabled="$projects->isEmpty()">
                <i class="icon-check mr-2"></i>{{ __('Buat Tugas') }}
            </x-primary-button>
        </div>
    </form>
</div>