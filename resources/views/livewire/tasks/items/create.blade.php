<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\TskProject;
use App\Models\TskItem;
use App\Models\TskAuth;
use App\Models\TskType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public string $title = '';
    public string $desc = '';
    public int $tsk_project_id = 0;
    public int $tsk_type_id = 0;
    public string $start_date = '';
    public string $end_date = '';
    public int $assigned_to = 0;
    public float $estimated_hours = 0;

    public array $projects = [];
    public array $users = [];
    public array $types = [];
    public bool $hasProjects = false;
    public bool $canAssign = false;

    public function mount()
    {
        $this->loadProjects();
        $this->loadTypes();
        $this->checkAssignPermission();
        $this->hasProjects = count($this->projects) > 0;
        
        // Set default dates
        $this->start_date = now()->format('Y-m-d');
        $this->end_date = now()->addWeek()->format('Y-m-d');
    }

    #[On('task-create')]
    public function handleTaskCreate($data = [])
    {
        if (isset($data['project_id'])) {
            $this->tsk_project_id = $data['project_id'];
            $this->loadUsersForAssignment();
        }
    }

    public function updatedTskProjectId($value)
    {
        $this->loadUsersForAssignment();
    }

    private function loadProjects()
    {
        $user = auth()->user();
        
        // Superuser has access to all projects
        if ($user->id === 1) {
            $this->projects = TskProject::with('tsk_team')
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'team' => $p->tsk_team->short_name ?? $p->tsk_team->name
                ])
                ->toArray();
            return;
        }
        
        // Get all teams where user is a member
        $teamIds = TskAuth::where('user_id', $user->id)
            ->pluck('tsk_team_id')
            ->toArray();

        // Load projects from user's teams
        $this->projects = TskProject::with('tsk_team')
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

    private function loadTypes()
    {
        $this->types = TskType::active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    private function checkAssignPermission()
    {
        $user = auth()->user();
        
        // Check if user has task-assign permission in any team
        $this->canAssign = TskAuth::where('user_id', $user->id)
            ->get()
            ->contains(function ($auth) {
                return $auth->hasPermission('task-assign');
            });
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
        $this->users = User::whereHas('tsk_auths', function ($query) use ($project) {
            $query->where('tsk_team_id', $project->tsk_team_id);
        })
        ->orderBy('name')
        ->get(['id', 'name', 'emp_id'])
        ->toArray();
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

        $project = TskProject::find($this->tsk_project_id);
        $user = auth()->user();

        // Policy-based authorization for task creation
        Gate::authorize('create', [TskItem::class, $project]);

        // If assigning to someone, check assignment permission
        if ($this->assigned_to) {
            $assignee = User::find($this->assigned_to);
            $dummyTask = new TskItem(['tsk_project_id' => $project->id]);
            $dummyTask->setRelation('tsk_project', $project);
            
            Gate::authorize('assign', [$dummyTask, $assignee]);
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
                         placeholder="Masukkan deskripsi tugas (opsional)"></textarea>
                @error('desc') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Project Selection -->
            <div>
                <label for="tsk_project_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Proyek *
                </label>
                <select id="tsk_project_id"
                        wire:model.live="tsk_project_id"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Pilih Proyek</option>
                    @foreach($projects as $project)
                        <option value="{{ $project['id'] }}">{{ $project['name'] }} ({{ $project['team'] }})</option>
                    @endforeach
                </select>
                @error('tsk_project_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Task Type -->
            <div>
                <label for="tsk_type_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Tipe Tugas *
                </label>
                <select id="tsk_type_id"
                        wire:model="tsk_type_id"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Pilih Tipe Tugas</option>
                    @foreach($types as $type)
                        <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                    @endforeach
                </select>
                @error('tsk_type_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Date Range -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Tanggal Mulai *
                    </label>
                    <input type="date" 
                           id="start_date"
                           wire:model="start_date"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Tanggal Akhir *
                    </label>
                    <input type="date" 
                           id="end_date"
                           wire:model="end_date"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Assignment (only show if user has permission) -->
            @if($canAssign && count($users) > 0)
                <div>
                    <label for="assigned_to" class="block text-sm font-medium text-gray-700 mb-1">
                        Tugaskan Kepada
                    </label>
                    <select id="assigned_to"
                            wire:model="assigned_to"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Tidak ditugaskan</option>
                        @foreach($users as $user)
                            <option value="{{ $user['id'] }}">{{ $user['name'] }} ({{ $user['emp_id'] }})</option>
                        @endforeach
                    </select>
                    @error('assigned_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
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

            <!-- Error Messages -->
            @error('general') 
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    {{ $message }}
                </div>
            @enderror

            <!-- Submit Button -->
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button type="button" 
                        x-on:click="$dispatch('close-slide-over')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Batal
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Buat Tugas
                </button>
            </div>
        </form>
    @endif
</div>