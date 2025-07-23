<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\TskProject;
use App\Models\TskType;
use App\Models\TskItem;
use App\Models\TskAuth;
use App\Models\User;

new class extends Component
{
    public string $title = '';
    public string $desc = '';
    public int $tsk_project_id = 0;
    public int $tsk_type_id = 0;
    public int $assigned_to = 0;
    public string $start_date = '';
    public string $end_date = '';
    public float $estimated_hours = 0;

    public array $projects = [];
    public array $types = [];
    public array $users = [];
    public bool $canAssign = false;
    public bool $hasProjects = false;

    public function mount()
    {
        $this->loadInitialData();
    }

    #[On('task-create')]
    public function handleTaskCreate($params = [])
    {
        $this->reset(['title', 'desc', 'tsk_project_id', 'tsk_type_id', 'assigned_to', 'start_date', 'end_date', 'estimated_hours']);
        
        // Set default start date to today
        $this->start_date = now()->format('Y-m-d');
        
        // Auto-select project if provided
        if (isset($params['project_id'])) {
            $this->tsk_project_id = (int) $params['project_id'];
            $this->updatedTskProjectId();
        }
        
        $this->loadInitialData();
    }

    public function loadInitialData()
    {
        $user = auth()->user();
        
        // Load projects based on permissions
        $this->projects = $this->getAvailableProjects($user);
        $this->hasProjects = !empty($this->projects);
        
        // Load task types
        $this->types = TskType::getTypesForSelect();
        
        // Check if user can assign tasks
        $this->canAssign = $this->userCanAssignTasks($user);
        
        // Load users for assignment if user can assign
        if ($this->canAssign) {
            $this->loadUsersForAssignment();
        }
    }

    public function updatedTskProjectId()
    {
        if ($this->tsk_project_id && $this->canAssign) {
            $this->loadUsersForAssignment();
        }
    }

    private function getAvailableProjects($user): array
    {
        // Check if user has global task-create permission
        $globalPerms = TskAuth::where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->flatMap(function ($auth) {
                return is_array($auth->perms) ? $auth->perms : json_decode($auth->perms ?? '[]', true);
            })
            ->unique()
            ->values()
            ->toArray();

        if (in_array('task-create', $globalPerms)) {
            // User can create tasks in any project
            return TskProject::with('tsk_team')
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'team' => $p->tsk_team->short_name ?? $p->tsk_team->name
                ])
                ->toArray();
        }

        // User can only create tasks in their team projects
        $teamIds = TskAuth::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('tsk_team_id')
            ->toArray();

        return TskProject::with('tsk_team')
            ->whereIn('tsk_team_id', $teamIds)
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'team' => $p->tsk_team->short_name ?? $p->tsk_team->name
            ])
            ->toArray();
    }

    private function userCanAssignTasks($user): bool
    {
        $auths = TskAuth::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        foreach ($auths as $auth) {
            $perms = is_array($auth->perms) ? $auth->perms : json_decode($auth->perms ?? '[]', true);
            if (in_array('task-assign', $perms)) {
                return true;
            }
        }

        return false;
    }

    private function loadUsersForAssignment()
    {
        if (!$this->tsk_project_id) {
            $this->users = [];
            return;
        }

        $project = TskProject::find($this->tsk_project_id);
        if (!$project) {
            $this->users = [];
            return;
        }

        // Get team members for this project
        $teamMembers = User::whereHas('tsk_auths', function ($query) use ($project) {
            $query->where('tsk_team_id', $project->tsk_team_id)
                  ->where('is_active', true);
        })
        ->orderBy('name')
        ->get(['id', 'name', 'employee_id'])
        ->toArray();

        $this->users = $teamMembers;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'desc' => 'nullable|string',
            'tsk_project_id' => 'required|exists:tsk_projects,id',
            'tsk_type_id' => 'required|exists:tsk_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_hours' => 'nullable|numeric|min:0',
        ], [
            'title.required' => 'Judul tugas harus diisi.',
            'tsk_project_id.required' => 'Proyek harus dipilih.',
            'tsk_type_id.required' => 'Tipe tugas harus dipilih.',
            'start_date.required' => 'Tanggal mulai harus diisi.',
            'end_date.required' => 'Tanggal akhir harus diisi.',
            'end_date.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal mulai.',
            'assigned_to.exists' => 'Pengguna yang dipilih tidak valid.',
            'estimated_hours.numeric' => 'Estimasi jam harus berupa angka.',
            'estimated_hours.min' => 'Estimasi jam tidak boleh negatif.',
        ]);

        // Validate user permissions
        $user = auth()->user();
        $project = TskProject::find($this->tsk_project_id);

        // Check if user can create tasks in this project
        if (!$this->canCreateTaskInProject($user, $project)) {
            $this->addError('tsk_project_id', 'Anda tidak memiliki izin untuk membuat tugas di proyek ini.');
            return;
        }

        // Check if user can assign to selected user
        if ($this->assigned_to && !$this->canAssignToUser($user, $this->assigned_to, $project)) {
            $this->addError('assigned_to', 'Anda tidak dapat menugaskan ke pengguna ini.');
            return;
        }

        try {
            TskItem::create([
                'title' => $this->title,
                'desc' => $this->desc,
                'tsk_project_id' => $this->tsk_project_id,
                'tsk_type_id' => $this->tsk_type_id,
                'created_by' => $user->id,
                'assigned_to' => $this->assigned_to ?: null,
                'status' => 'todo',
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'estimated_hours' => $this->estimated_hours ?: null,
            ]);

            $this->dispatch('close-slide-over');
            $this->dispatch('task-created');
            
            session()->flash('success', 'Tugas berhasil dibuat.');

            // Redirect logic
            $currentRoute = request()->route()->getName();
            if (!str_contains($currentRoute, 'tasks.items.index')) {
                return redirect()->route('tasks.items.index');
            }

        } catch (\Exception $e) {
            $this->addError('general', 'Terjadi kesalahan saat membuat tugas. Silakan coba lagi.');
        }
    }

    private function canCreateTaskInProject($user, $project): bool
    {
        // Check if user has task-create permission globally
        $globalPerms = TskAuth::where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->flatMap(function ($auth) {
                return is_array($auth->perms) ? $auth->perms : json_decode($auth->perms ?? '[]', true);
            })
            ->unique()
            ->values()
            ->toArray();

        if (in_array('task-create', $globalPerms)) {
            return true;
        }

        // Check if user is member of this project's team
        $auth = TskAuth::where('user_id', $user->id)
            ->where('tsk_team_id', $project->tsk_team_id)
            ->where('is_active', true)
            ->first();

        return $auth !== null;
    }

    private function canAssignToUser($user, $assigneeId, $project): bool
    {
        if (!$this->canAssign) return false;

        // Check if assignee is member of the project's team
        $assigneeAuth = TskAuth::where('user_id', $assigneeId)
            ->where('tsk_team_id', $project->tsk_team_id)
            ->where('is_active', true)
            ->first();

        return $assigneeAuth !== null;
    }
}; ?>

<div>
    @if(!$hasProjects)
        <div class="p-6 text-center text-gray-500">
            <p>Anda tidak memiliki akses ke proyek apapun.</p>
            <p class="text-sm mt-2">Hubungi administrator untuk mendapatkan akses.</p>
        </div>
    @else
        <form wire:submit.prevent="save" class="space-y-6 p-6">
            <!-- Title -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                    Judul Tugas *
                </label>
                <input type="text" 
                       id="title"
                       wire:model="title"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Masukkan judul tugas">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Description -->
            <div>
                <label for="desc" class="block text-sm font-medium text-gray-700 mb-1">
                    Deskripsi
                </label>
                <textarea id="desc"
                          wire:model="desc"
                          rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          placeholder="Deskripsi tugas (opsional)"></textarea>
                @error('desc') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Project and Type Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Project -->
                <div>
                    <label for="tsk_project_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Proyek *
                    </label>
                    <select id="tsk_project_id"
                            wire:model.live="tsk_project_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Pilih Proyek</option>
                        @foreach($projects as $project)
                            <option value="{{ $project['id'] }}">
                                {{ $project['name'] }} ({{ $project['team'] }})
                            </option>
                        @endforeach
                    </select>
                    @error('tsk_project_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <!-- Type -->
                <div>
                    <label for="tsk_type_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Tipe *
                    </label>
                    <select id="tsk_type_id"
                            wire:model="tsk_type_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Pilih Tipe</option>
                        @foreach($types as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('tsk_type_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Date Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Start Date -->
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Awal *
                    </label>
                    <input type="date" 
                           id="start_date"
                           wire:model="start_date"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <!-- End Date -->
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Akhir *
                    </label>
                    <input type="date" 
                           id="end_date"
                           wire:model="end_date"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Assignment and Estimated Hours Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Assignment (only if user can assign) -->
                @if($canAssign)
                    <div>
                        <label for="assigned_to" class="block text-sm font-medium text-gray-700 mb-1">
                            Ditugaskan Ke
                        </label>
                        <select id="assigned_to"
                                wire:model="assigned_to"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                @if(!$tsk_project_id) disabled @endif>
                            <option value="">Pilih Pengguna</option>
                            @foreach($users as $user)
                                <option value="{{ $user['id'] }}">
                                    {{ $user['name'] }} @if($user['employee_id'])({{ $user['employee_id'] }})@endif
                                </option>
                            @endforeach
                        </select>
                        @if(!$tsk_project_id)
                            <p class="mt-1 text-xs text-gray-500">Pilih proyek terlebih dahulu</p>
                        @endif
                        @error('assigned_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div></div>
                @endif

                <!-- Estimated Hours -->
                <div>
                    <label for="estimated_hours" class="block text-sm font-medium text-gray-700 mb-1">
                        Estimasi Jam
                    </label>
                    <input type="number" 
                           id="estimated_hours"
                           wire:model="estimated_hours"
                           step="0.5"
                           min="0"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="0">
                    @error('estimated_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- General Error -->
            @error('general') 
                <div class="bg-red-50 border border-red-200 rounded-md p-3">
                    <p class="text-sm text-red-600">{{ $message }}</p>
                </div>
            @enderror

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button type="button" 
                        x-on:click="$dispatch('close-slide-over')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Batal
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Buat Tugas
                </button>
            </div>
        </form>
    @endif
</div>