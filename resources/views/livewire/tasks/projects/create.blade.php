<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\Attributes\Validate;
use App\Models\TskTeam;
use App\Models\TskProject;
use App\Models\TskAuth;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new #[Layout('layouts.app')]
class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:1000')]
    public string $desc = '';

    #[Validate('required|exists:tsk_teams,id')]
    public string $tsk_team_id = '';

    #[Validate('nullable|exists:users,id')]
    public string $user_id = '';

    #[Validate('required|in:active,on_hold,cancelled')]
    public string $status = 'active';

    #[Validate('required|in:low,medium,high,urgent')]
    public string $priority = 'medium';

    #[Validate('nullable|date')]
    public string $start_date = '';

    #[Validate('nullable|date|after_or_equal:start_date')]
    public string $end_date = '';

    public $teams = [];
    public $team_members = [];
    public $can_manage_projects = false;
    public $user_teams = [];

    public function mount()
    {
        $user = Auth::user();
        
        // Check if user has project-manage permission or is superuser
        if ($user->id === 1) {
            // Superuser can manage everything
            $this->can_manage_projects = true;
        } else {
            $this->can_manage_projects = TskAuth::where('user_id', $user->id)
                ->where('is_active', true)
                ->get()
                ->contains(function ($auth) {
                    $perms = is_array($auth->perms) ? $auth->perms : json_decode($auth->perms ?? '[]', true);
                    return in_array('project-manage', $perms);
                });
        }

        // Load teams based on permissions
        if ($this->can_manage_projects) {
            // Can create projects in any team
            $this->teams = TskTeam::where('is_active', true)->get()->toArray();
        } else {
            // Can only create projects in teams they're member of
            $this->user_teams = TskAuth::where('user_id', $user->id)
                ->where('is_active', true)
                ->with('tsk_team')
                ->get()
                ->pluck('tsk_team')
                ->where('is_active', true)
                ->values()
                ->toArray();
            
            $this->teams = $this->user_teams;
        }

        // If user only has access to one team, auto-select it
        if (count($this->teams) === 1) {
            $this->tsk_team_id = (string) $this->teams[0]['id'];
            $this->loadTeamMembers();
        }
    }

    public function updatedTskTeamId()
    {
        $this->loadTeamMembers();
        // Reset user selection when team changes
        $this->user_id = '';
    }

    public function loadTeamMembers()
    {
        if (!$this->tsk_team_id) {
            $this->team_members = [];
            return;
        }

        // Load team members (users with auth for this team)
        $this->team_members = User::whereHas('tsk_auths', function ($query) {
            $query->where('tsk_team_id', $this->tsk_team_id)
                  ->where('is_active', true);
        })->select('id', 'name', 'emp_id')->get()->toArray();
    }

    public function save()
    {
        $user = Auth::user();

        // Validate form
        $this->validate();

        // Additional business logic validation
        
        // 1. Check if user can create project in selected team
        if (!$this->can_manage_projects) {
            $userTeamIds = collect($this->user_teams)->pluck('id')->toArray();
            if (!in_array((int) $this->tsk_team_id, $userTeamIds)) {
                $this->addError('tsk_team_id', 'Anda tidak memiliki izin untuk membuat proyek di tim ini.');
                return;
            }
        }

        // 2. Check if selected owner is member of selected team
        if ($this->user_id) {
            $isTeamMember = TskAuth::where('user_id', $this->user_id)
                ->where('tsk_team_id', $this->tsk_team_id)
                ->where('is_active', true)
                ->exists();
            
            if (!$isTeamMember) {
                $this->addError('user_id', 'Pengguna yang dipilih bukan anggota tim ini.');
                return;
            }
        }

        // 3. Check name uniqueness (global)
        $nameExists = TskProject::where('name', $this->name)->exists();
        if ($nameExists) {
            $this->addError('name', 'Nama proyek sudah digunakan.');
            return;
        }

        // 4. Generate unique project code
        $code = $this->generateProjectCode();

        // 5. Create project
        try {
            $project = TskProject::create([
                'name' => $this->name,
                'desc' => $this->desc,
                'code' => $code,
                'tsk_team_id' => $this->tsk_team_id,
                'user_id' => $this->user_id ?: $user->id, // Default to creator if no owner selected
                'status' => $this->status,
                'priority' => $this->priority,
                'start_date' => $this->start_date ?: null,
                'end_date' => $this->end_date ?: null,
            ]);

            session()->flash('success', 'Proyek berhasil dibuat!');
            
            // Redirect to projects index
            return $this->redirect('/tasks/projects');

        } catch (\Exception $e) {
            $this->addError('general', 'Terjadi kesalahan saat membuat proyek. Silakan coba lagi.');
            logger()->error('Project creation failed: ' . $e->getMessage());
        }
    }

    private function generateProjectCode(): string
    {
        $attempts = 0;
        $maxAttempts = 100;

        do {
            // Generate code: PRJ + 3-digit sequence
            $lastProject = TskProject::latest('id')->first();
            $nextNumber = $lastProject ? $lastProject->id + 1 : 1;
            $code = 'PRJ' . str_pad($nextNumber + $attempts, 3, '0', STR_PAD_LEFT);
            
            $exists = TskProject::where('code', $code)->exists();
            $attempts++;

        } while ($exists && $attempts < $maxAttempts);

        if ($exists) {
            // Fallback to timestamp-based code if we can't find unique code
            $code = 'PRJ' . date('ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }

        return strtoupper($code);
    }

};

?>

<x-slot name="title">{{ __('Buat Proyek Baru') }}</x-slot>

@auth
    <x-slot name="header">
        <x-nav-task-sub>{{ __('Buat Proyek Baru') }}</x-nav-task-sub>
    </x-slot>
@endauth

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow sm:rounded-lg">
            <div class="p-6 text-neutral-900 dark:text-neutral-100">
                
                {{-- Page Header --}}
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100 mb-2">
                        <i class="icon-plus mr-2"></i>{{ __('Buat Proyek Baru') }}
                    </h2>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        {{ __('Isi formulir di bawah untuk membuat proyek baru.') }}
                    </p>
                </div>

                {{-- Success Message --}}
                @if (session('success'))
                    <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg">
                        <div class="flex items-center">
                            <i class="icon-check text-green-600 dark:text-green-400 mr-2"></i>
                            <span class="text-green-800 dark:text-green-200">{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                {{-- General Error --}}
                @error('general')
                    <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg">
                        <div class="flex items-center">
                            <i class="icon-alert-triangle text-red-600 dark:text-red-400 mr-2"></i>
                            <span class="text-red-800 dark:text-red-200">{{ $message }}</span>
                        </div>
                    </div>
                @enderror

                {{-- Project Creation Form --}}
                <form wire:submit="save" class="space-y-6">
                    
                    {{-- Basic Information --}}
                    <div class="bg-neutral-50 dark:bg-neutral-700 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-4 text-neutral-900 dark:text-neutral-100">
                            <i class="icon-info mr-2"></i>{{ __('Informasi Dasar') }}
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Project Name --}}
                            <div class="md:col-span-2">
                                <x-input-label for="name" :value="__('Nama Proyek')" />
                                <x-text-input 
                                    wire:model="name" 
                                    id="name" 
                                    class="mt-1 block w-full" 
                                    type="text" 
                                    placeholder="{{ __('Masukkan nama proyek...') }}"
                                    required 
                                />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            {{-- Team Selection --}}
                            <div>
                                <x-input-label for="tsk_team_id" :value="__('Tim')" />
                                <select 
                                    wire:model.live="tsk_team_id" 
                                    id="tsk_team_id"
                                    class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="">{{ __('Pilih Tim...') }}</option>
                                    @foreach($teams as $team)
                                        <option value="{{ $team['id'] }}">
                                            {{ $team['name'] }} ({{ $team['short_name'] }})
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('tsk_team_id')" class="mt-2" />
                            </div>

                            {{-- Project Owner (only if can manage projects) --}}
                            @if($can_manage_projects)
                                <div>
                                    <x-input-label for="user_id" :value="__('Pemilik Proyek')" />
                                    <select 
                                        wire:model="user_id" 
                                        id="user_id"
                                        class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                        @if(!$tsk_team_id) disabled @endif
                                    >
                                        <option value="">{{ __('Pilih Pemilik...') }}</option>
                                        @foreach($team_members as $member)
                                            <option value="{{ $member['id'] }}">
                                                {{ $member['name'] }} @if($member['emp_id']) ({{ $member['emp_id'] }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                                    @if(!$tsk_team_id)
                                        <p class="mt-1 text-sm text-neutral-500">{{ __('Pilih tim terlebih dahulu') }}</p>
                                    @endif
                                </div>
                            @endif

                            {{-- Status --}}
                            <div>
                                <x-input-label for="status" :value="__('Status')" />
                                <select 
                                    wire:model="status" 
                                    id="status"
                                    class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                >
                                    <option value="active">{{ __('Aktif') }}</option>
                                    <option value="on_hold">{{ __('Ditunda') }}</option>
                                    <option value="cancelled">{{ __('Dibatalkan') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>

                            {{-- Priority --}}
                            <div>
                                <x-input-label for="priority" :value="__('Prioritas')" />
                                <select 
                                    wire:model="priority" 
                                    id="priority"
                                    class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                >
                                    <option value="low">{{ __('Rendah') }}</option>
                                    <option value="medium">{{ __('Sedang') }}</option>
                                    <option value="high">{{ __('Tinggi') }}</option>
                                    <option value="urgent">{{ __('Mendesak') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                            </div>

                            {{-- Description --}}
                            <div class="md:col-span-2">
                                <x-input-label for="desc" :value="__('Deskripsi')" />
                                <textarea 
                                    wire:model="desc" 
                                    id="desc"
                                    rows="4"
                                    class="mt-1 block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    placeholder="{{ __('Masukkan deskripsi proyek...') }}"
                                ></textarea>
                                <x-input-error :messages="$errors->get('desc')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    {{-- Timeline --}}
                    <div class="bg-neutral-50 dark:bg-neutral-700 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-4 text-neutral-900 dark:text-neutral-100">
                            <i class="icon-calendar mr-2"></i>{{ __('Timeline (Opsional)') }}
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Start Date --}}
                            <div>
                                <x-input-label for="start_date" :value="__('Tanggal Mulai')" />
                                <x-text-input 
                                    wire:model="start_date" 
                                    id="start_date" 
                                    class="mt-1 block w-full" 
                                    type="date"
                                />
                                <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                            </div>

                            {{-- End Date --}}
                            <div>
                                <x-input-label for="end_date" :value="__('Tanggal Selesai')" />
                                <x-text-input 
                                    wire:model="end_date" 
                                    id="end_date" 
                                    class="mt-1 block w-full" 
                                    type="date"
                                />
                                <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    {{-- Form Actions --}}
                    <div class="flex items-center justify-end space-x-4 pt-6">
                        <x-secondary-button type="button" onclick="window.location.href='/tasks/projects'">
                            {{ __('Batal') }}
                        </x-secondary-button>
                        
                        <x-primary-button 
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                        >
                            <span wire:loading.remove wire:target="save">
                                <i class="icon-plus mr-2"></i>{{ __('Buat Proyek') }}
                            </span>
                            <span wire:loading wire:target="save">
                                <i class="icon-loader mr-2 animate-spin"></i>{{ __('Menyimpan...') }}
                            </span>
                        </x-primary-button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>