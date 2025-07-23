<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

use App\Models\TskTeam;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public $q = '';
    public $perPage = 10;

    public function with(): array
    {
        $q = trim($this->q);
        $teams = TskTeam::query();

        if ($q) {
            $teams->where(function ($query) use ($q) {
                $query->where('name', 'LIKE', '%' . $q . '%')
                      ->orWhere('short_name', 'LIKE', '%' . $q . '%')
                      ->orWhere('desc', 'LIKE', '%' . $q . '%');
            });
        }

        return [
            'teams' => $teams->withCount(['tsk_projects', 'tsk_auths'])->orderBy('name')->paginate($this->perPage),
        ];
    }

    #[On('updated')]
    public function refresh()
    {
        // Refresh component
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};
?>
<x-slot name="title">{{ __('Tim') . ' — ' . __('Tugas') }}</x-slot>
<x-slot name="header">
    <x-nav-task-sub>{{ __('Kelola') }}</x-nav-task-sub>
</x-slot>

<div id="content" class="py-12 max-w-4xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Tim') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'team-create')"><i class="icon-plus"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="icon-search"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="team-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>

        <div wire:key="team-create">
            <x-modal name="team-create">
                <livewire:tasks.manage.team-create />
            </x-modal>
        </div>
        
        <div wire:key="team-edit">
            <x-modal name="team-edit">
                <livewire:tasks.manage.team-edit />
            </x-modal>
        </div>

        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="teams-table" class="table">
                        <tr>
                            <th>{{ __('Tim') }}</th>
                            <th>{{ __('Proyek') }}</th>
                            <th>{{ __('Anggota') }}</th>
                            <th>{{ __('Status') }}</th>
                            @can('superuser')
                            <th>{{ __('Tindakan') }}</th>
                            @endcan
                        </tr>
                        @foreach ($teams as $team)
                            <tr wire:key="team-tr-{{ $team->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'team-edit'); $dispatch('team-edit', { id: {{ $team->id }} })">
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
                                    <span class="font-medium">{{ $team->tsk_auths_count }}</span>
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
                                @can('superuser')
                                <td>
                                    <x-text-button x-on:click.stop="$dispatch('open-modal', 'team-edit'); $dispatch('team-edit', { id: {{ $team->id }} })">
                                        <i class="icon-edit"></i>
                                    </x-text-button>
                                </td>
                                @endcan
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="teams-none">
                        @if (!$teams->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada tim ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$teams->isEmpty())
                @if ($teams->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((teams) => {
                                teams.forEach(team => {
                                    if (team.isIntersecting) {
                                        @this.loadMore()
                                    }
                                })
                            })
                            observer.observe(this.$el)
                        }
                    }" x-init="observe"></div>
                    <x-spinner class="sm" />
                @else
                    <div class="mx-auto">{{ __('Tidak ada lagi') }}</div>
                @endif
            @endif
        </div>
    </div>
</div>