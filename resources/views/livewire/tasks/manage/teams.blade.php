<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\TskTeam;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component {
    use WithPagination;

    public string $q = '';
    public int $perPage = 10;

    // Team creation/edit properties
    public int $team_id = 0;
    public string $name = '';
    public string $short_name = '';
    public string $desc = '';
    public bool $is_active = true;
    public bool $showForm = false;

    public function rules()
    {
        $teamId = $this->team_id ?: 'NULL';
        
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:10', "unique:tsk_teams,short_name,{$teamId}"],
            'desc' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    public function with(): array
    {
        $teams = TskTeam::query();

        if ($this->q) {
            $q = '%' . $this->q . '%';
            $teams->where(function ($query) use ($q) {
                $query->where('name', 'LIKE', $q)
                      ->orWhere('short_name', 'LIKE', $q)
                      ->orWhere('desc', 'LIKE', $q);
            });
        }

        return [
            'teams' => $teams->withCount(['tsk_projects', 'activeAuths'])
                             ->orderBy('name')
                             ->paginate($this->perPage),
            'can_manage' => $this->canManageTeams(),
        ];
    }

    private function canManageTeams(): bool
    {
        $user = auth()->user();
        
        return $user->tsk_auths()
            ->where('is_active', true)
            ->get()
            ->contains(function ($auth) {
                return $auth->hasPermission('project-manage');
            });
    }

    public function create()
    {
        if (!$this->canManageTeams()) {
            $this->js('toast("' . __('Tidak memiliki izin untuk mengelola tim') . '", { type: "danger" })');
            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id)
    {
        if (!$this->canManageTeams()) {
            $this->js('toast("' . __('Tidak memiliki izin untuk mengelola tim') . '", { type: "danger" })');
            return;
        }

        $team = TskTeam::find($id);
        if ($team) {
            $this->team_id = $team->id;
            $this->name = $team->name;
            $this->short_name = $team->short_name;
            $this->desc = $team->desc ?? '';
            $this->is_active = $team->is_active;
            $this->showForm = true;
            $this->resetValidation();
        }
    }

    public function save()
    {
        if (!$this->canManageTeams()) {
            $this->js('toast("' . __('Tidak memiliki izin untuk mengelola tim') . '", { type: "danger" })');
            return;
        }

        $this->validate();

        if ($this->team_id) {
            // Update existing team
            $team = TskTeam::find($this->team_id);
            if ($team) {
                $team->update([
                    'name' => $this->name,
                    'short_name' => $this->short_name,
                    'desc' => $this->desc,
                    'is_active' => $this->is_active,
                ]);
                $this->js('toast("' . __('Tim diperbarui') . '", { type: "success" })');
            }
        } else {
            // Create new team
            TskTeam::create([
                'name' => $this->name,
                'short_name' => $this->short_name,
                'desc' => $this->desc,
                'is_active' => $this->is_active,
            ]);
            $this->js('toast("' . __('Tim dibuat') . '", { type: "success" })');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id)
    {
        if (!$this->canManageTeams()) {
            $this->js('toast("' . __('Tidak memiliki izin untuk mengelola tim') . '", { type: "danger" })');
            return;
        }

        $team = TskTeam::find($id);
        if ($team) {
            // Check if team has projects
            if ($team->tsk_projects()->count() > 0) {
                $this->js('toast("' . __('Tim tidak dapat dihapus karena memiliki proyek') . '", { type: "danger" })');
                return;
            }

            // Delete team auths first
            $team->tsk_auths()->delete();
            
            // Delete team
            $team->delete();
            
            $this->js('toast("' . __('Tim dihapus') . '", { type: "success" })');
        }
    }

    public function cancelForm()
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function resetForm()
    {
        $this->reset(['team_id', 'name', 'short_name', 'desc', 'is_active']);
        $this->is_active = true;
        $this->resetValidation();
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};

?>

<x-slot name="title">{{ __('Tim') . ' — ' . __('Tugas') }}</x-slot>
<x-slot name="header">
    <x-nav-task-sub>{{ __('Kelola Tim') }}</x-nav-task-sub>
</x-slot>

<div id="content" class="py-12 max-w-4xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Tim') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @if($can_manage)
                <x-secondary-button type="button" wire:click="create">
                    <i class="icon-plus"></i>
                </x-secondary-button>
                @endif
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open">
                    <i class="icon-search"></i>
                </x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="team-q" x-ref="search" placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>

        @if($showForm)
        <div class="mx-6 my-8 p-6 bg-white dark:bg-neutral-800 shadow rounded-lg">
            <form wire:submit="save" class="space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ $team_id ? __('Edit Tim') : __('Buat Tim Baru') }}
                    </h3>
                    <x-text-button type="button" wire:click="cancelForm">
                        <i class="icon-x"></i>
                    </x-text-button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="name" :value="__('Nama Tim')" />
                        <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="short_name" :value="__('Nama Singkat')" />
                        <x-text-input wire:model="short_name" id="short_name" class="block mt-1 w-full" type="text" maxlength="10" required />
                        <x-input-error :messages="$errors->get('short_name')" class="mt-2" />
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Maksimal 10 karakter') }}</p>
                    </div>
                </div>

                <div>
                    <x-input-label for="desc" :value="__('Deskripsi')" />
                    <textarea wire:model="desc" id="desc" rows="3" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm"></textarea>
                    <x-input-error :messages="$errors->get('desc')" class="mt-2" />
                </div>

                <div>
                    <label class="flex items-center">
                        <input wire:model="is_active" type="checkbox" class="rounded border-neutral-300 text-caldy-600 shadow-sm focus:ring-caldy-500 dark:border-neutral-600 dark:bg-neutral-900 dark:focus:ring-caldy-600 dark:focus:ring-offset-neutral-800">
                        <span class="ml-2 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tim Aktif') }}</span>
                    </label>
                </div>

                <div class="flex items-center justify-end pt-6 border-t border-neutral-200 dark:border-neutral-700 space-x-2">
                    <x-secondary-button type="button" wire:click="cancelForm">
                        {{ __('Batal') }}
                    </x-secondary-button>
                    <x-primary-button>
                        {{ $team_id ? __('Simpan Perubahan') : __('Buat Tim') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
        @endif

        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="teams-table" class="table">
                        <tr>
                            <th>{{ __('Tim') }}</th>
                            <th>{{ __('Proyek') }}</th>
                            <th>{{ __('Anggota') }}</th>
                            <th>{{ __('Status') }}</th>
                            @if($can_manage)
                            <th>{{ __('Tindakan') }}</th>
                            @endif
                        </tr>
                        @foreach ($teams as $team)
                            <tr wire:key="team-tr-{{ $team->id }}">
                                <td>
                                    <div>
                                        <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $team->name }}</div>
                                        <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ $team->short_name }}</div>
                                        @if($team->desc)
                                        <div class="text-sm text-neutral-500 mt-1">{{ $team->desc }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="font-medium">{{ $team->tsk_projects_count }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="font-medium">{{ $team->active_auths_count }}</span>
                                </td>
                                <td>
                                    @if($team->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                            {{ __('Aktif') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                            {{ __('Nonaktif') }}
                                        </span>
                                    @endif
                                </td>
                                @if($can_manage)
                                <td>
                                    <div class="flex items-center space-x-2">
                                        <x-text-button wire:click="edit({{ $team->id }})">
                                            <i class="icon-edit"></i>
                                        </x-text-button>
                                        @if($team->tsk_projects_count == 0)
                                        <x-text-button 
                                            wire:click="delete({{ $team->id }})"
                                            wire:confirm="{{ __('Yakin ingin menghapus tim ini?') }}"
                                            class="text-red-600 hover:text-red-500">
                                            <i class="icon-trash"></i>
                                        </x-text-button>
                                        @endif
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>

        @if($teams->hasPages())
        <div class="px-6">
            {{ $teams->links() }}
        </div>
        @endif

        @if($teams->hasMorePages())
        <div class="flex justify-center mt-6">
            <x-secondary-button wire:click="loadMore">
                {{ __('Muat Lebih Banyak') }}
            </x-secondary-button>
        </div>
        @endif
    </div>
</div>