<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use App\Models\TskAuth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component {
    use WithPagination;

    public string $q = '';
    public int $perPage = 10;

    #[On('updated')]
    public function refreshList()
    {
        // Force refresh the component
        $this->resetPage();
    }

    public function with(): array
    {
        $auths = TskAuth::with(['user', 'tsk_team'])
            ->where('is_active', true);

        if ($this->q) {
            $q = '%' . $this->q . '%';
            $auths->where(function ($query) use ($q) {
                $query->whereHas('user', function ($q_user) use ($q) {
                    $q_user->where('name', 'LIKE', $q)
                           ->orWhere('emp_id', 'LIKE', $q);
                })
                ->orWhereHas('tsk_team', function ($q_team) use ($q) {
                    $q_team->where('name', 'LIKE', $q)
                           ->orWhere('short_name', 'LIKE', $q);
                });
            });
        }

        return [
            'auths' => $auths->orderBy('created_at', 'desc')->paginate($this->perPage),
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

    public function loadMore()
    {
        $this->perPage += 10;
    }
};

?>

<x-slot name="title">{{ __('Wewenang') . ' — ' . __('Tugas') }}</x-slot>
<x-slot name="header">
    <x-nav-task-sub>{{ __('Kelola Wewenang') }}</x-nav-task-sub>
</x-slot>

<div id="content" class="py-12 max-w-4xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Wewenang') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @if($can_manage)
                <x-secondary-button type="button" 
                    x-on:click.prevent="$dispatch('open-modal', 'auth-create')">
                    <i class="icon-plus"></i>
                </x-secondary-button>
                @endif
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open">
                    <i class="icon-search"></i>
                </x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="auth-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>

        <div wire:key="auth-create">
            <x-modal name="auth-create">
                <livewire:tasks.manage.auth-create />
            </x-modal>
        </div>
        
        <div wire:key="auth-edit">
            <x-modal name="auth-edit">
                <livewire:tasks.manage.auth-edit />
            </x-modal>
        </div>

        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="auths-table" class="table">
                        <tr>
                            <th>{{ __('Pengguna') }}</th>
                            <th>{{ __('Tim') }}</th>
                            <th>{{ __('Wewenang') }}</th>
                            @if($can_manage)
                            <th>{{ __('Tindakan') }}</th>
                            @endif
                        </tr>
                        @foreach ($auths as $auth)
                            <tr wire:key="auth-tr-{{ $auth->user_id }}-{{ $auth->tsk_team_id }}">
                                <td>
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 mr-3 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                            @if ($auth->user->photo)
                                                <img class="w-full h-full object-cover dark:brightness-75"
                                                    src="{{ '/storage/users/' . $auth->user->photo }}" />
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                                    viewBox="0 0 1000 1000">
                                                    <path
                                                        d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $auth->user->name }}</div>
                                            <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ $auth->user->emp_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $auth->tsk_team->name }}</div>
                                        <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ $auth->tsk_team->short_name }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @if(empty($auth->perms))
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200">
                                                {{ __('Anggota') }}
                                            </span>
                                        @else
                                            @foreach($auth->perms as $perm)
                                                @php
                                                    $permLabels = [
                                                        'task-assign' => 'Tugaskan',
                                                        'task-manage' => 'Kelola Tugas',
                                                        'project-manage' => 'Kelola Proyek'
                                                    ];
                                                    $colors = [
                                                        'task-assign' => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
                                                        'task-manage' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                                        'project-manage' => 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100'
                                                    ];
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $colors[$perm] ?? 'bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200' }}">
                                                    {{ $permLabels[$perm] ?? $perm }}
                                                </span>
                                            @endforeach
                                        @endif
                                    </div>
                                </td>
                                @if($can_manage)
                                <td>
                                    <x-text-button 
                                        x-on:click.prevent="$dispatch('open-modal', 'auth-edit'); $dispatch('auth-edit', { user_id: {{ $auth->user_id }}, tsk_team_id: {{ $auth->tsk_team_id }} })">
                                        <i class="icon-edit"></i>
                                    </x-text-button>
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                    
                    @if($auths->isEmpty())
                    <div class="p-6 text-center text-neutral-500">
                        {{ __('Belum ada wewenang yang ditemukan.') }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($auths->hasPages())
        <div class="px-6">
            {{ $auths->links() }}
        </div>
        @endif

        @if($auths->hasMorePages())
        <div class="flex justify-center mt-6">
            <x-secondary-button wire:click="loadMore">
                {{ __('Muat Lebih Banyak') }}
            </x-secondary-button>
        </div>
        @endif
    </div>
</div>