<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

use App\Models\TskProject;
use App\Models\TskItem;
use App\Models\TskType;
use App\Models\User;

new class extends Component
{
    public array $task = [
        'title' => '',
        'desc' => '',
        'tsk_project_id' => 0,
        'tsk_type_id' => 0,
        'priority' => 'medium',
        'start_date' => '',
        'end_date' => '',
        'assigned_to' => 0,
        'estimated_hours' => null,
    ];

    public array $projects = [];
    public array $users = [];
    public array $types = [];
    public bool $can_assign = false;
    public bool $is_loading = false;

    public function mount()
    {
        $this->task['start_date'] = date('Y-m-d'); // Default to today
        $this->loadUserProjects();
        $this->loadTaskTypes();
    }

    private function loadTaskTypes()
    {
        $this->types = TskType::active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                ];
            })
            ->toArray();
    }

    private function loadUserProjects()
    {
        // Load projects based on policy authorization
        // For superuser (handled by policy before() method) or team members
        $allProjects = TskProject::where('status', 'active')
            ->with('tsk_team:id,name,short_name')
            ->get();

        $this->projects = $allProjects->filter(function ($project) {
            // Use policy to check if user can create tasks in this project
            return Gate::allows('create', [TskItem::class, $project]);
        })->map(function ($project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'team_name' => $project->tsk_team->name ?? '',
                'tsk_team_id' => $project->tsk_team_id,
            ];
        })->values()->toArray();
    }

    private function checkCanAssign()
    {
        if (!$this->task['tsk_project_id']) return false;

        $project = TskProject::find($this->task['tsk_project_id']);
        if (!$project) return false;

        // Create a dummy task with the project relationship for policy check
        $dummyTask = new TskItem(['tsk_project_id' => $project->id]);
        $dummyTask->setRelation('tsk_project', $project);
        
        // Create a dummy assignee for the permission check
        $dummyAssignee = Auth::user(); // Use current user as dummy
        
        return Gate::allows('assign', [$dummyTask, $dummyAssignee]);
    }

    private function loadProjectUsers()
    {
        if (!$this->task['tsk_project_id']) {
            $this->users = [];
            return;
        }

        $project = TskProject::find($this->task['tsk_project_id']);
        if (!$project) {
            $this->users = [];
            return;
        }

        // Load users from the project's team
        $this->users = User::whereHas('tsk_auths', function ($query) use ($project) {
            $query->where('tsk_team_id', $project->tsk_team_id);
        })
        ->orderBy('name')
        ->get(['id', 'name', 'emp_id'])
        ->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'emp_id' => $user->emp_id ?? '',
            ];
        })
        ->toArray();
    }

    public function updatedTaskTskProjectId()
    {
        $this->loadProjectUsers();
        $this->can_assign = $this->checkCanAssign();
        
        // Reset assigned_to if project changes
        $this->task['assigned_to'] = 0;
    }

    #[On('task-create')]
    public function loadContext($project_id = null)
    {
        $this->resetForm();
        
        if ($project_id) {
            $this->task['tsk_project_id'] = $project_id;
            $this->loadProjectUsers();
            $this->can_assign = $this->checkCanAssign();
        }
        
        // Reset start_date to today
        $this->task['start_date'] = date('Y-m-d');
    }

    private function resetForm()
    {
        $this->task = [
            'title' => '',
            'desc' => '',
            'tsk_project_id' => 0,
            'tsk_type_id' => 0,
            'priority' => 'medium',
            'start_date' => date('Y-m-d'),
            'end_date' => '',
            'assigned_to' => 0,
            'estimated_hours' => null,
        ];
        $this->users = [];
        $this->can_assign = false;
        $this->resetErrorBag();
    }

    public function save()
    {
        $this->is_loading = true;

        // Clean up data
        $this->task['title'] = trim($this->task['title']);
        $this->task['desc'] = trim($this->task['desc']);
        $this->task['end_date'] = $this->task['end_date'] ?: null;
        $this->task['estimated_hours'] = $this->task['estimated_hours'] ?: null;
        $this->task['assigned_to'] = $this->task['assigned_to'] ?: null;

        // Validate
        $this->validate([
            'task.title' => 'required|string|max:255',
            'task.desc' => 'nullable|string|max:1000',
            'task.tsk_project_id' => 'required|exists:tsk_projects,id',
            'task.tsk_type_id' => 'nullable|exists:tsk_types,id',
            'task.priority' => 'required|in:low,medium,high,urgent',
            'task.start_date' => 'required|date',
            'task.end_date' => 'nullable|date|after:task.start_date',
            'task.assigned_to' => 'nullable|exists:users,id',
            'task.estimated_hours' => 'nullable|numeric|min:0.5|max:999',
        ], [
            'task.title.required' => 'Judul tugas wajib diisi',
            'task.title.max' => 'Judul tugas maksimal 255 karakter',
            'task.desc.max' => 'Deskripsi maksimal 1000 karakter',
            'task.tsk_project_id.required' => 'Proyek wajib dipilih',
            'task.tsk_project_id.exists' => 'Proyek tidak valid',
            'task.tsk_type_id.exists' => 'Tipe tugas tidak valid',
            'task.priority.required' => 'Prioritas wajib dipilih',
            'task.priority.in' => 'Prioritas tidak valid',
            'task.start_date.required' => 'Tanggal mulai wajib diisi',
            'task.start_date.date' => 'Format tanggal mulai tidak valid',
            'task.end_date.date' => 'Format tanggal deadline tidak valid',
            'task.end_date.after' => 'Tanggal deadline harus setelah tanggal mulai',
            'task.assigned_to.exists' => 'Pengguna yang dipilih tidak valid',
            'task.estimated_hours.numeric' => 'Estimasi jam harus berupa angka',
            'task.estimated_hours.min' => 'Estimasi jam minimal 0.5',
            'task.estimated_hours.max' => 'Estimasi jam maksimal 999',
        ]);

        try {
            $project = TskProject::findOrFail($this->task['tsk_project_id']);
            
            // Use policy for task creation authorization
            Gate::authorize('create', [TskItem::class, $project]);

            // Create task first
            $task = TskItem::create([
                'title' => $this->task['title'],
                'desc' => $this->task['desc'],
                'tsk_project_id' => $this->task['tsk_project_id'],
                'tsk_type_id' => $this->task['tsk_type_id'] ?: null,
                'priority' => $this->task['priority'],
                'start_date' => $this->task['start_date'],
                'end_date' => $this->task['end_date'],
                'estimated_hours' => $this->task['estimated_hours'],
                'created_by' => Auth::id(),
                'status' => 'todo',
            ]);

            // Handle assignment if specified
            if ($this->task['assigned_to']) {
                $assignee = User::findOrFail($this->task['assigned_to']);
                
                // Use policy for assignment authorization
                Gate::authorize('assign', [$task, $assignee]);
                
                // Update task with assignment
                $task->update(['assigned_to' => $this->task['assigned_to']]);
            }

            $this->js('toast("' . __('Tugas berhasil dibuat') . '", { type: "success" })');
            $this->js('window.dispatchEvent(escKey)'); // close slideover
            $this->dispatch('task-created', $task->id);

            // Smart redirect
            $currentRoute = url()->livewire_current();
            $path = parse_url($currentRoute, PHP_URL_PATH);
            if ($path !== '/tasks/items') {
                $this->redirect(route('tasks.items.index'), navigate: true); 
            }

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->js('toast("' . $e->getMessage() . '", { type: "danger" })');
        } catch (\Exception $e) {
            $this->js('toast("' . __('Terjadi kesalahan saat membuat tugas') . '", { type: "danger" })');
        } finally {
            $this->is_loading = false;
        }
    }
};

?>

<div class="relative overflow-y-auto">
    <!-- Header -->
    <div class="flex justify-between items-center p-6">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Tugas baru') }}
        </h2>
        <div>
            <div wire:loading wire:target="save">
                <x-primary-button type="button" disabled>
                    <i class="icon-save mr-2"></i>{{ __('Simpan') }}
                </x-primary-button>
            </div>
            <div wire:loading.remove wire:target="save">
                <x-primary-button type="button" wire:click="save">
                    <i class="icon-save mr-2"></i>{{ __('Simpan') }}
                </x-primary-button>
            </div>
        </div>
    </div>

    <!-- Error Display -->
    @if ($errors->any())
        <div class="px-6">
            <x-input-error :messages="$errors->first()" />
        </div>
    @endif

    <!-- Form Content -->
    <div class="grid grid-cols-1 gap-y-6 px-6 pb-6">
        
        <!-- Basic Info Section (No Header) -->
        <div class="grid grid-cols-1 gap-y-4">
            <div>
                <label for="task-title" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                    {{ __('Judul tugas') }}
                </label>
                <x-text-input 
                    id="task-title" 
                    wire:model="task.title" 
                    type="text" 
                    placeholder="{{ __('Masukkan judul tugas...') }}"
                    class="w-full"
                />
            </div>

            <div>
                <label for="task-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                    {{ __('Deskripsi') }}
                </label>
                <textarea 
                    id="task-desc" 
                    wire:model="task.desc"
                    rows="3"
                    placeholder="{{ __('Jelaskan detail tugas...') }}"
                    class="w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                ></textarea>
            </div>
        </div>

        <!-- Identifikasi Section -->
        <div>
            <x-pill class="uppercase mb-4">{{ __('Identifikasi') }}</x-pill>
            
            <div class="grid grid-cols-1 gap-y-4">
                <div>
                    <label for="task-project" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                        {{ __('Proyek') }}
                    </label>
                    <x-select id="task-project" wire:model.live="task.tsk_project_id" class="w-full">
                        <option value="">{{ __('Pilih proyek...') }}</option>
                        @foreach($projects as $project)
                            <option value="{{ $project['id'] }}">
                                {{ $project['name'] }} ({{ $project['team_name'] }})
                            </option>
                        @endforeach
                    </x-select>
                </div>

                <div>
                    <label for="task-type" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                        {{ __('Tipe') }}
                    </label>
                    <x-select id="task-type" wire:model="task.tsk_type_id" class="w-full">
                        <option value="">{{ __('Pilih tipe tugas...') }}</option>
                        @foreach($types as $type)
                            <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                        @endforeach
                    </x-select>
                </div>
                
                @if($can_assign && $task['tsk_project_id'])
                <div>
                    <label for="task-assigned-to" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                        {{ __('Ditugaskan kepada') }}
                    </label>
                    <x-select id="task-assigned-to" wire:model="task.assigned_to" class="w-full">
                        <option value="">{{ __('Pilih anggota tim...') }}</option>
                        @foreach($users as $user)
                            <option value="{{ $user['id'] }}">
                                {{ $user['name'] }}{{ $user['emp_id'] ? ' (' . $user['emp_id'] . ')' : '' }}
                            </option>
                        @endforeach
                    </x-select>
                </div>
                @endif
            </div>
        </div>

        <!-- Task Details Section -->
        <div>
            <x-pill class="uppercase mb-4">{{ __('Penjadwalan') }}</x-pill>
            
            <div class="grid grid-cols-1 gap-y-4">
                <div class="grid grid-cols-2 gap-x-4">
                    <div>
                        <label for="task-start-date" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                            {{ __('Tanggal mulai') }}
                        </label>
                        <x-text-input 
                            id="task-start-date" 
                            wire:model="task.start_date" 
                            type="date" 
                            class="w-full"
                        />
                    </div>

                    <div>
                        <label for="task-end-date" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                            {{ __('Deadline') }}
                        </label>
                        <x-text-input 
                            id="task-end-date" 
                            wire:model="task.end_date" 
                            type="date" 
                            class="w-full"
                        />
                    </div>
                </div>

                <div>
                    <label for="task-estimated-hours" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                        {{ __('Estimasi jam') }}
                    </label>
                    <x-text-input-suffix 
                        suffix="jam"
                        id="task-estimated-hours" 
                        wire:model="task.estimated_hours" 
                        type="number" 
                        step="0.5"
                        min="0.5"
                        max="999"
                        placeholder="{{ __('Contoh: 8') }}"
                        autocomplete="off"
                    />
                </div>
            </div>
        </div>

    </div>

    <!-- Loading Overlay -->
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="save"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target="save" class="hidden"></x-spinner>
</div>