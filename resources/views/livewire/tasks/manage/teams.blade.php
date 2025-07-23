<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\TskTeam;
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
        $teams = TskTeam::query()
            ->withCount(['tsk_projects', 'users'])
            ->orderBy('name', 'asc');

        if ($q) {
            $teams->where(function (Builder $query) use ($q) {
                $query
                    ->orWhere('name', 'LIKE', '%' . $q . '%')
                    ->orWhere('short_name', 'LIKE', '%' . $q . '%');
            });
        }

        return [
            'teams' => $teams->paginate($this->perPage),
        ];
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
<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Tim') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                <x-secondary-button type="button" 
                    x-on:click.prevent="$dispatch('open-modal', 'team-create')"><i class="icon-plus"></i></x-secondary-button>
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="icon-search"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="team-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="team-create">
            <x-modal name="team-create">
                <livewire:tasks.manage.team-create  />
            </x-modal>
        </div>
        <div wire:key="team-edit">
            <x-modal name="team-edit">
                <livewire:tasks.manage.team-edit  />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="teams-table" class="table">
                        <tr>
                            <th>{{ __('Nama Tim') }}</th>
                            <th>{{ __('Statistik') }}</th>
                        </tr>
                        @foreach ($teams as $team)
                            <tr wire:key="team-tr-{{ $team->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'team-edit'); $dispatch('team-edit', { id: '{{ $team->id }}'})">
                                <td>
                                    <div>
                                        <div class="font-semibold">{{ $team->name }}</div>
                                        <div class="text-xs text-neutral-400 dark:text-neutral-600">
                                            {{ $team->short_name }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-sm">
                                        <div>{{ $team->tsk_projects_count }} {{ __('proyek') }}</div>
                                        <div class="text-xs text-neutral-400 dark:text-neutral-600">
                                            {{ $team->users_count }} {{ __('anggota') }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                @if($teams->hasMorePages())
                <div class="text-center my-8">
                    <x-secondary-button wire:click="loadMore">{{ __('Muat lebih banyak') }}</x-secondary-button>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>