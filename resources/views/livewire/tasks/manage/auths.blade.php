<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\TskAuth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
   use WithPagination;

    #[Url]
    public $q;
    public $perPage = 10;

    #[On('updated')]
    public function with(): array
    {
        $q = trim($this->q);
        $auths = TskAuth::join('users', 'tsk_auths.user_id', '=', 'users.id')
            ->join('tsk_teams', 'tsk_auths.tsk_team_id', '=', 'tsk_teams.id')
            ->select('tsk_auths.*', 'users.name as user_name', 'users.emp_id as user_emp_id', 'users.photo as user_photo', 'tsk_teams.name as team_name', 'tsk_teams.short_name as team_short_name')
            ->orderBy('tsk_auths.user_id', 'desc');

        if ($q) {
            $auths->where(function (Builder $query) use ($q) {
                $query
                    ->orWhere('users.name', 'LIKE', '%' . $q . '%')
                    ->orWhere('users.emp_id', 'LIKE', '%' . $q . '%')
                    ->orWhere('tsk_teams.name', 'LIKE', '%' . $q . '%')
                    ->orWhere('tsk_teams.short_name', 'LIKE', '%' . $q . '%');
            });
        }

        return [
            'auths' => $auths->paginate($this->perPage),
        ];
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
<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Wewenang') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                <x-secondary-button type="button" 
                    x-on:click.prevent="$dispatch('open-modal', 'auth-create')"><i class="icon-plus"></i></x-secondary-button>
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="icon-search"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="auth-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="auth-create">
            <x-modal name="auth-create">
                <livewire:tasks.manage.auth-create  />
            </x-modal>
        </div>
        <div wire:key="auth-edit">
            <x-modal name="auth-edit">
                <livewire:tasks.manage.auth-edit  />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="auths-table" class="table">
                        <tr>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Tim') }}</th>
                            <th>{{ __('Tindakan') }}</th>
                        </tr>
                        @foreach ($auths as $auth)
                            <tr wire:key="auth-tr-{{ $auth->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'auth-edit'); $dispatch('auth-edit', { id: '{{ $auth->id }}'})">
                                <td>
                                    <div class="flex">
                                        <div>
                                            <div
                                                class="w-8 h-8 my-auto mr-3 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                                @if ($auth->user_photo)
                                                    <img class="w-full h-full object-cover dark:brightness-75"
                                                        src="{{ '/storage/users/' . $auth->user_photo }}" />
                                                @else
                                                    <svg xmlns="http://www.w3.org/2000/svg"
                                                        class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                                        viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                                        <path
                                                            d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                                    </svg>
                                                @endif
                                            </div>
                                        </div>
                                        <div>
                                            <div>{{ $auth->user_name }}</div>
                                            <div class="text-xs text-neutral-400 dark:text-neutral-600">
                                                {{ $auth->user_emp_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div class="font-semibold">{{ $auth->team_name }}</div>
                                        <div class="text-xs text-neutral-400 dark:text-neutral-600">
                                            {{ $auth->team_short_name }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-sm">
                                        @if($auth->perms && count($auth->perms) > 0)
                                            {{ count($auth->perms) . ' ' . __('wewenang') }}
                                        @else
                                            {{ __('Anggota biasa') }}
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                @if($auths->hasMorePages())
                <div class="text-center my-8">
                    <x-secondary-button wire:click="loadMore">{{ __('Muat lebih banyak') }}</x-secondary-button>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>