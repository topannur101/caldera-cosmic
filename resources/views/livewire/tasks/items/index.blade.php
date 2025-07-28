<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Carbon\Carbon;
use App\Models\TskItem;
use App\Models\TskProject;
use App\Models\TskType;
use App\Models\User;
use App\Traits\HasDateRangeFilter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] 
class extends Component {
    
    use HasDateRangeFilter;

    #[Url]
    public $start_at;

    #[Url] 
    public $end_at;

    public $pendingChanges = [];
    public $hasChanges = false;

    // Initialize component with date range
    public function mount()
    {
        if (!$this->start_at) {
            $this->start_at = now()->startOfMonth()->format('Y-m-d');
        }
        if (!$this->end_at) {
            $this->end_at = now()->endOfMonth()->format('Y-m-d');
        }
    }

    // Get filtered tasks for Tabulator
    public function getTasksProperty()
    {
        $start = Carbon::parse($this->start_at)->startOfDay();
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = TskItem::with(['tsk_project.tsk_team', 'tsk_type', 'creator', 'assignee'])
            ->whereBetween('created_at', [$start, $end]);

        // Apply team-based policy filtering
        $user = Auth::user();
        if ($user->id !== 1) { // Not superuser
            $teamIds = $user->tsk_auths()->pluck('tsk_team_id');
            $query->whereHas('tsk_project', function ($q) use ($teamIds) {
                $q->whereIn('tsk_team_id', $teamIds);
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    // Get projects for dropdowns
    public function getProjectsProperty()
    {
        $allProjects = TskProject::where('status', 'active')
            ->with('tsk_team:id,name')
            ->get();

        return $allProjects->filter(function ($project) {
            return Gate::allows('create', [TskItem::class, $project]);
        })->map(function ($project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'team_name' => $project->tsk_team->name ?? '',
            ];
        })->values();
    }

    // Get task types
    public function getTaskTypesProperty()
    {
        return TskType::active()->orderBy('name')->get()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
            ];
        });
    }

    // Get users for assignment
    public function getUsersProperty()
    {
        $user = Auth::user();
        if ($user->id === 1) { // Superuser sees all
            return User::where('is_active', true)->get(['id', 'name', 'emp_id']);
        }

        // Get users from teams the current user belongs to
        $teamIds = $user->tsk_auths()->pluck('tsk_team_id');
        return User::whereHas('tsk_auths', function ($q) use ($teamIds) {
            $q->whereIn('tsk_team_id', $teamIds);
        })->where('is_active', true)->get(['id', 'name', 'emp_id']);
    }

    // Save all pending changes
    public function saveAllChanges()
    {
        $savedCount = 0;
        $errors = [];

        foreach ($this->pendingChanges as $taskId => $changes) {
            try {
                $task = TskItem::findOrFail($taskId);
                
                // Authorize update
                Gate::authorize('update', $task);

                // Validate and apply changes
                $validatedData = $this->validateTaskData($changes);
                
                foreach ($validatedData as $field => $value) {
                    $task->{$field} = $value;
                }

                $task->save();
                $savedCount++;

            } catch (\Exception $e) {
                $errors[] = "Tugas ID {$taskId}: " . $e->getMessage();
            }
        }

        // Clear pending changes
        $this->pendingChanges = [];
        $this->hasChanges = false;

        // Dispatch events
        $this->dispatch('changes-saved', [
            'saved' => $savedCount,
            'errors' => $errors
        ]);

        $this->dispatch('refresh-tabulator');

        if ($savedCount > 0) {
            $this->js("toast('{$savedCount} tugas berhasil disimpan', { type: 'success' });");
        }

        if (!empty($errors)) {
            $this->js("toast('" . implode('; ', $errors) . "', { type: 'danger' });");
        }
    }

    // Validate task data
    private function validateTaskData($data)
    {
        $validated = [];

        // Title validation
        if (isset($data['title'])) {
            if (empty(trim($data['title']))) {
                throw new \Exception('Judul wajib diisi');
            }
            $validated['title'] = trim($data['title']);
        }

        // Project validation
        if (isset($data['tsk_project_id'])) {
            $project = TskProject::find($data['tsk_project_id']);
            if (!$project) {
                throw new \Exception('Proyek tidak ditemukan');
            }
            $validated['tsk_project_id'] = $data['tsk_project_id'];
        }

        // Type validation
        if (isset($data['tsk_type_id'])) {
            if ($data['tsk_type_id']) {
                $type = TskType::find($data['tsk_type_id']);
                if (!$type) {
                    throw new \Exception('Tipe tugas tidak ditemukan');
                }
            }
            $validated['tsk_type_id'] = $data['tsk_type_id'] ?: null;
        }

        // Date validations
        if (isset($data['start_date'])) {
            if (empty($data['start_date'])) {
                throw new \Exception('Tanggal mulai wajib diisi');
            }
            $validated['start_date'] = $data['start_date'];
        }

        if (isset($data['end_date'])) {
            if (empty($data['end_date'])) {
                throw new \Exception('Tanggal selesai wajib diisi');
            }
            // Check start_date <= end_date
            if (isset($data['start_date']) && $data['start_date'] > $data['end_date']) {
                throw new \Exception('Tanggal selesai harus setelah tanggal mulai');
            }
            $validated['end_date'] = $data['end_date'];
        }

        // Assignment validation
        if (isset($data['assigned_to'])) {
            if ($data['assigned_to']) {
                $assignee = User::find($data['assigned_to']);
                if (!$assignee) {
                    throw new \Exception('User tidak ditemukan');
                }
                // TODO: Add assignment authorization check
            }
            $validated['assigned_to'] = $data['assigned_to'] ?: null;
        }

        // Hours validation
        if (isset($data['estimated_hours'])) {
            if ($data['estimated_hours'] && (!is_numeric($data['estimated_hours']) || $data['estimated_hours'] < 0)) {
                throw new \Exception('Estimasi jam harus berupa angka positif');
            }
            $validated['estimated_hours'] = $data['estimated_hours'] ?: null;
        }

        if (isset($data['actual_hours'])) {
            if ($data['actual_hours'] && (!is_numeric($data['actual_hours']) || $data['actual_hours'] < 0)) {
                throw new \Exception('Jam aktual harus berupa angka positif');
            }
            $validated['actual_hours'] = $data['actual_hours'] ?: null;
        }

        return $validated;
    }

    // Create new task
    public function createTask($data)
    {
        try {
            $validatedData = $this->validateTaskData($data);
            
            $project = TskProject::findOrFail($validatedData['tsk_project_id']);
            Gate::authorize('create', [TskItem::class, $project]);

            $task = new TskItem();
            foreach ($validatedData as $field => $value) {
                $task->{$field} = $value;
            }
            $task->created_by = Auth::id();
            $task->status = 'todo';
            $task->priority = 'medium';

            $task->save();

            $this->dispatch('task-created', ['task' => $task]);
            $this->dispatch('refresh-tabulator');

            return ['success' => true, 'message' => 'Tugas berhasil dibuat'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Track pending changes from Tabulator
    public function trackChanges($taskId, $changes)
    {
        $this->pendingChanges[$taskId] = array_merge(
            $this->pendingChanges[$taskId] ?? [],
            $changes
        );
        
        $this->hasChanges = !empty($this->pendingChanges);
        
        $this->dispatch('changes-tracked', [
            'taskId' => $taskId,
            'totalChanges' => count($this->pendingChanges)
        ]);
    }

    // Clear all pending changes
    public function clearChanges()
    {
        $this->pendingChanges = [];
        $this->hasChanges = false;
        $this->dispatch('changes-cleared');
        $this->dispatch('refresh-tabulator');
    }

    // Delete task
    public function deleteTask($taskId)
    {
        try {
            $task = TskItem::findOrFail($taskId);
            Gate::authorize('delete', $task);
            
            $task->delete();
            
            // Remove from pending changes if exists
            unset($this->pendingChanges[$taskId]);
            $this->hasChanges = !empty($this->pendingChanges);
            
            $this->dispatch('task-deleted', ['taskId' => $taskId]);
            $this->dispatch('refresh-tabulator');
            
            return ['success' => true, 'message' => 'Tugas berhasil dihapus'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

}; ?>

<x-slot name="title">{{ __('Tugas Spreadsheet') . ' — ' . __('Tugas') }}</x-slot>

<x-slot name="header">
    <x-nav-task-sub>{{ __('Tugas Spreadsheet') }}</x-nav-task-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    
    {{-- Date Filter (OMV Pattern) --}}
    <div class="bg-white dark:bg-neutral-800 rounded-lg shadow mb-6">
        <div class="p-4">
            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                {{-- Date Preset Dropdown --}}
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <x-dropdown width="48">
                            <x-slot name="trigger">
                                <button type="button" class="inline-flex items-center px-3 py-2 border border-neutral-300 dark:border-neutral-700 rounded-md font-semibold text-xs text-neutral-700 dark:text-neutral-300 uppercase tracking-widest shadow-sm hover:bg-neutral-50 dark:hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 transition ease-in-out duration-150">
                                    <i class="icon-calendar mr-2"></i>{{ __('Preset') }}
                                    <div class="ms-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setToday">
                                    {{ __('Hari ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                    {{ __('Kemarin') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                    {{ __('Minggu ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                    {{ __('Minggu lalu') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                    {{ __('Bulan ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                    {{ __('Bulan lalu') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" id="filter-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at" id="filter-date-end" type="date"></x-text-input>
                </div>
                
                {{-- Filter Info --}}
                <div class="text-sm text-neutral-600 dark:text-neutral-400">
                    <i class="icon-info"></i>
                    Menampilkan tugas dibuat {{ \Carbon\Carbon::parse($this->start_at)->format('d M Y') }} - {{ \Carbon\Carbon::parse($this->end_at)->format('d M Y') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Tabulator Container --}}
    <div class="bg-white dark:bg-neutral-800 rounded-lg shadow">
        <div class="p-4 border-b border-neutral-200 dark:border-neutral-700">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        <i class="icon-grid mr-2"></i>Spreadsheet Tugas
                    </h3>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">
                        Klik sel untuk mengedit • <kbd class="px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded text-xs">Ctrl+N</kbd> baris baru • 
                        <kbd class="px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded text-xs">Ctrl+S</kbd> simpan semua
                    </p>
                </div>
                
                {{-- Action Buttons --}}
                <div class="flex items-center gap-3">
                    {{-- Change Counter --}}
                    <div class="text-sm" 
                         x-data="{ changes: 0 }"
                         x-on:changes-tracked.window="changes = $event.detail.totalChanges"
                         x-on:changes-cleared.window="changes = 0"
                         x-on:changes-saved.window="changes = 0">
                        <span class="text-neutral-600 dark:text-neutral-400">Perubahan:</span>
                        <span class="font-semibold" 
                              :class="changes > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-neutral-600 dark:text-neutral-400'"
                              x-text="changes"></span>
                    </div>
                    
                    {{-- Save All Button --}}
                    <x-primary-button 
                        wire:click="saveAllChanges"
                        wire:loading.attr="disabled"
                        class="relative"
                        x-data="{ hasChanges: false }"
                        x-on:changes-tracked.window="hasChanges = true"
                        x-on:changes-cleared.window="hasChanges = false"
                        x-on:changes-saved.window="hasChanges = false"
                        :disabled="!$this->hasChanges">
                        <i class="icon-save mr-2"></i>
                        <span wire:loading.remove wire:target="saveAllChanges">Simpan Semua</span>
                        <span wire:loading wire:target="saveAllChanges">Menyimpan...</span>
                    </x-primary-button>
                    
                    {{-- Clear Changes Button --}}
                    <x-secondary-button 
                        wire:click="clearChanges"
                        x-data="{ hasChanges: false }"
                        x-on:changes-tracked.window="hasChanges = true"
                        x-on:changes-cleared.window="hasChanges = false"
                        x-on:changes-saved.window="hasChanges = false"
                        x-show="hasChanges"
                        x-transition>
                        <i class="icon-x mr-2"></i>Batal
                    </x-secondary-button>
                </div>
            </div>
        </div>
        
        {{-- Tabulator Table --}}
        <div id="task-tabulator" class="min-h-96" wire:ignore></div>
    </div>
</div>

{{-- Initialize Tabulator --}}
@script
<script>
document.addEventListener('livewire:navigated', () => {
    initTaskTabulator();
});

// Initialize on first load
document.addEventListener('DOMContentLoaded', () => {
    initTaskTabulator();
});

function initTaskTabulator() {
    // Destroy existing instance
    if (window.taskTable) {
        window.taskTable.destroy();
        window.taskTable = null;
    }
    
    // Define columns
    const columns = [
        {
            title: "Judul", 
            field: "title", 
            editor: "input",
            validator: "required",
            width: 200,
            cellEdited: function(cell) {
                trackCellChange(cell);
            }
        },
        {
            title: "Proyek", 
            field: "tsk_project_id", 
            editor: "select",
            editorParams: {
                values: @json($this->projects->mapWithKeys(fn($p) => [$p['id'] => $p['name'] . ' (' . $p['team_name'] . ')'])),
            },
            formatter: function(cell) {
                const projects = @json($this->projects);
                const project = projects.find(p => p.id == cell.getValue());
                return project ? project.name : '';
            },
            cellEdited: function(cell) {
                trackCellChange(cell);
            }
        },
        {
            title: "Tipe", 
            field: "tsk_type_id", 
            editor: "select",
            editorParams: {
                values: Object.assign(
                    { "": "Tidak ada" },
                    @json($this->taskTypes->mapWithKeys(fn($t) => [$t['id'] => $t['name']]))
                ),
            },
            formatter: function(cell) {
                const types = @json($this->taskTypes);
                const type = types.find(t => t.id == cell.getValue());
                return type ? type.name : '-';
            },
            cellEdited: function(cell) {
                trackCellChange(cell);
            }
        },
        {
            title: "Tanggal Mulai", 
            field: "start_date", 
            editor: "date",
            validator: "required",
            cellEdited: function(cell) {
                trackCellChange(cell);
            }
        },
        {
            title: "Tanggal Selesai", 
            field: "end_date", 
            editor: "date",
            validator: "required",
            cellEdited: function(cell) {
                trackCellChange(cell);
            }
        },
        {
            title: "Ditugaskan Kepada", 
            field: "assigned_to", 
            editor: "select",
            editorParams: {
                values: Object.assign(
                    { "": "Tidak ada" },
                    @json($this->users->mapWithKeys(fn($u) => [$u['id'] => $u['name'] . ' (' . $u['emp_id'] . ')']))
                ),
            },
            formatter: function(cell) {
                const users = @json($this->users);
                const user = users.find(u => u.id == cell.getValue());
                return user ? user.name : '-';
            },
            cellEdited: function(cell) {
                trackCellChange(cell);
            }
        },
        {
            title: "Estimasi Jam", 
            field: "estimated_hours", 
            editor: "input",
            validator: "numeric",
            editorParams: {
                elementAttributes: {
                    type: "number",
                    min: "0",
                    step: "0.5"
                }
            },
            cellEdited: function(cell) {
                trackCellChange(cell);
            }
        },
        {
            title: "Jam Aktual", 
            field: "actual_hours", 
            editor: "input",
            validator: "numeric",
            editorParams: {
                elementAttributes: {
                    type: "number",
                    min: "0",
                    step: "0.5"
                }
            },
            cellEdited: function(cell) {
                trackCellChange(cell);
            }
        },
        {
            title: "Aksi", 
            field: "actions",
            formatter: function(cell) {
                const row = cell.getRow();
                const data = row.getData();
                const isChanged = window.pendingChanges && window.pendingChanges[data.id];
                
                return `
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full ${isChanged ? 'bg-amber-400' : 'bg-green-400'}" 
                             title="${isChanged ? 'Ada perubahan' : 'Tersimpan'}"></div>
                        <button onclick="deleteTaskRow(${data.id})" 
                                class="text-red-600 hover:text-red-800 p-1">
                            <i class="icon-trash-2"></i>
                        </button>
                    </div>
                `;
            },
            headerSort: false,
            width: 80
        }
    ];

    // Initialize Tabulator
    window.taskTable = new Tabulator("#task-tabulator", {
        data: @json($this->tasks->toArray()),
        layout: "fitColumns",
        columns: columns,
        height: "calc(100vh - 20rem)",
        editTriggerEvent: "click",
        validationMode: "highlight",
        
        // Enable keyboard shortcuts
        keybindings: {
            "ctrl+78": function() { // Ctrl+N for new row
                addNewRow();
            },
            "ctrl+83": function(e) { // Ctrl+S for save
                e.preventDefault();
                $wire.saveAllChanges();
            }
        }
    });
    
    // Initialize pending changes tracker
    window.pendingChanges = {};
    
    // Validation failed handler
    window.taskTable.on("validationFailed", function(cell, value, validators) {
        toast("Validasi gagal: " + validators.map(v => v.type).join(', '), { type: "danger" });
    });
}

function trackCellChange(cell) {
    const row = cell.getRow();
    const data = row.getData();
    const field = cell.getField();
    const value = cell.getValue();
    
    if (!window.pendingChanges[data.id]) {
        window.pendingChanges[data.id] = {};
    }
    
    window.pendingChanges[data.id][field] = value;
    
    // Update action column indicator
    row.reformat();
    
    // Notify Livewire
    $wire.trackChanges(data.id, { [field]: value });
}

function addNewRow() {
    const newRow = {
        id: 'new_' + Date.now(),
        title: '',
        tsk_project_id: '',
        tsk_type_id: '',
        start_date: new Date().toISOString().split('T')[0],
        end_date: '',
        assigned_to: '',
        estimated_hours: '',
        actual_hours: ''
    };
    
    window.taskTable.addRow(newRow);
}

function deleteTaskRow(taskId) {
    if (confirm('Hapus tugas ini?')) {
        $wire.deleteTask(taskId);
    }
}

// Listen for Livewire events
document.addEventListener('refresh-tabulator', () => {
    if (window.taskTable) {
        window.taskTable.setData(@json($this->tasks->toArray()));
        window.pendingChanges = {};
    }
});

document.addEventListener('task-created', (event) => {
    if (window.taskTable) {
        window.taskTable.addRow(event.detail.task);
    }
});

document.addEventListener('task-deleted', (event) => {
    if (window.taskTable) {
        window.taskTable.deleteRow(event.detail.taskId);
        delete window.pendingChanges[event.detail.taskId];
    }
});

document.addEventListener('changes-cleared', () => {
    window.pendingChanges = {};
    if (window.taskTable) {
        window.taskTable.redraw();
    }
});
</script>
@endscript