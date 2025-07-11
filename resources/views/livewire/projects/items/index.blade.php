<?php

// resources/livewire/projects/items/index.blade.php
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\PjtItem;
use App\Models\PjtTeam;
use App\Models\User;
use Carbon\Carbon;

new #[Layout('layouts.app')]
class extends Component
{
    use WithPagination;

    public int $perPage = 24;

    #[Url]
    public string $view = 'grid';

    #[Url]
    public string $sort = 'updated';

    public array $teams = [];
    
    #[Url]
    public array $team_ids = [];

    public bool $team_multiple = false;

    #[Url]
    public array $statuses = ['active'];
    
    #[Url]
    public string $q = '';

    #[Url]
    public bool $my_projects_only = false;

    #[Url]
    public bool $ignore_params = false;

    public string $download_as = 'pjt_items';

    public function mount()
    {        
        // Load available teams - superuser sees all, others see their teams
        if (Auth::user()->id === 1) {
            $this->teams = PjtTeam::orderBy('name')->get()->toArray();
        } else {
            // Get teams through user's projects
            $this->teams = PjtTeam::whereHas('pjt_items.pjt_members', function($query) {
                $query->where('user_id', Auth::user()->id);
            })->orderBy('name')->get()->toArray();
        }

        if (!$this->ignore_params) {
            $projectItemsParams = session('pjt_items_params', []);

            if ($projectItemsParams) {
                $this->q                = $projectItemsParams['q'] ?? '';
                $this->statuses         = $projectItemsParams['statuses'] ?? ['active'];
                $this->team_ids         = $projectItemsParams['team_ids'] ?? [];
                $this->my_projects_only = $projectItemsParams['my_projects_only'] ?? false;
                $this->view             = $projectItemsParams['view'] ?? 'grid';
                $this->sort             = $projectItemsParams['sort'] ?? 'updated';
            }
            
            $teamsParams = session('pjt_teams_params', []);

            if (!empty($teamsParams)) {
                $this->team_ids = $teamsParams['ids'] ?? [];
                
                if (count($this->team_ids) > 1) {
                    $this->team_multiple = true;
                } else {
                    $this->team_multiple = $teamsParams['multiple'] ?? false;
                }
            } else {
                $this->team_multiple = false;
                $this->team_ids = !empty($this->teams) ? [$this->teams[0]['id']] : [];
            }
        }
    }

    public function with(): array
    {
        $pjt_items_params = [
            'q'                 => trim($this->q),
            'statuses'          => $this->statuses,
            'team_ids'          => $this->team_ids,
            'my_projects_only'  => $this->my_projects_only,
            'sort'              => $this->sort,
            'view'              => $this->view,
        ];

        $pjt_teams_params = [
            'multiple'      => $this->team_multiple,
            'ids'           => $pjt_items_params['team_ids'],
        ];

        session(['pjt_items_params' => $pjt_items_params]);
        session(['pjt_teams_params' => $pjt_teams_params]);

        $pjt_items_query = PjtItem::with([
            'pjt_team',
            'user',
            'pjt_members.user',
            'pjt_tasks'
        ])
        ->whereIn('pjt_team_id', $this->team_ids)
        ->whereIn('status', $this->statuses);

        // Search filter
        if (trim($this->q)) {
            $pjt_items_query->where(function($query) {
                $query->where('name', 'like', '%' . trim($this->q) . '%')
                      ->orWhere('desc', 'like', '%' . trim($this->q) . '%')
                      ->orWhere('location', 'like', '%' . trim($this->q) . '%');
            });
        }

        // My projects only filter
        if ($this->my_projects_only) {
            $pjt_items_query->where(function($query) {
                $query->where('user_id', Auth::user()->id)
                      ->orWhereHas('pjt_members', function($subQuery) {
                          $subQuery->where('user_id', Auth::user()->id);
                      });
            });
        }

        // Sorting
        switch ($this->sort) {
            case 'updated':
                $pjt_items_query->orderByDesc('updated_at');
                break;
            case 'created':
                $pjt_items_query->orderByDesc('created_at');
                break;
            case 'name':
                $pjt_items_query->orderBy('name');
                break;
            case 'team':
                $pjt_items_query->join('pjt_teams', 'pjt_items.pjt_team_id', '=', 'pjt_teams.id')
                                   ->orderBy('pjt_teams.name')
                                   ->select('pjt_items.*');
                break;
        }

        return [
            'pjt_items' => $pjt_items_query->paginate($this->perPage),
        ];
    }

    public function download()
    {
        $token = md5(uniqid());        
        session()->put('pjt_items_token', $token);
        return redirect()->route('download.pjt-items', ['token' => $token]);
    }

    public function resetQuery()
    {
        session()->forget('pjt_items_params');
        session()->forget('pjt_teams_params');
        $this->redirect(route('projects.items.index'), navigate: true);
    }

    public function loadMore()
    {
        $this->perPage += 24;
    }

    public function updated($property)
    {
        $resetProps = ['q', 'view', 'sort', 'team_ids', 'statuses', 'my_projects_only'];
        if(in_array($property, $resetProps)) {
            $this->reset(['perPage']);
        }
    }

    public function getProgressPercentage($project): int
    {
        $totalTasks = $project->pjt_tasks->count();
        if ($totalTasks === 0) return 0;
        
        $completedTasks = $project->pjt_tasks->where('hour_remaining', 0)->count();
        return round(($completedTasks / $totalTasks) * 100);
    }

    public function getActiveTasksCount($project): int
    {
        return $project->pjt_tasks->where('hour_remaining', '>', 0)->count();
    }
};

?>

<x-slot name="title">{{ __('Item proyek') . ' — ' . __('Proyek') }}</x-slot>

<x-slot name="header">
    <x-nav-projects></x-nav-projects>
</x-slot>

<div id="content" class="py-6 max-w-8xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div wire:key="modals">
        <x-modal name="create-project">
            <livewire:projects.items.form :$teams lazy />
        </x-modal>
        <x-modal name="download" focusable>
            <div class="p-6 flex flex-col gap-y-6">
                <div class="flex justify-between items-start">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        <i class="icon-download mr-2"></i>
                        {{ __('Unduh sebagai...') }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
                </div>
                <div x-data="{ download_as: @entangle('download_as') }">
                    <x-radio x-model="download_as" id="as-pjt_items" name="as-pjt_items" value="pjt_items">{{ __('Daftar proyek') }}</x-radio>
                </div>
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="download" x-on:click="$dispatch('close')">
                        <div class="relative">
                            <span wire:loading.class="opacity-0" wire:target="download"><i class="icon-download"></i><span class="ml-0 hidden md:ml-2 md:inline">{{ __('Unduh') }}</span></span>
                            <x-spinner wire:loading.class.remove="hidden" wire:target="download" class="hidden sm mono"></x-spinner>                
                        </div>  
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>
    </div>

    <div class="static lg:sticky top-0 z-10 py-6">
        <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2">
            <div class="flex gap-x-2 items-center px-8 py-2 lg:px-4 lg:py-0">
                <i wire:loading.remove class="icon-search w-4 {{ $q ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600' }}"></i>
                <i wire:loading class="w-4 relative">
                    <x-spinner class="sm mono"></x-spinner>
                </i>
                <div class="w-full md:w-32">
                    <x-text-input-t wire:model.live="q" id="proj-q" name="proj-q" class="h-9 py-1 placeholder-neutral-400 dark:placeholder-neutral-600"
                        type="search" placeholder="{{ __('Search...') }}" autofocus autocomplete="proj-q" />
                </div>
            </div>            

            <div class="flex justify-between px-8 lg:px-3 py-3 lg:py-0 divide-x divide-neutral-200 dark:divide-neutral-700">
                <div class="btn-group h-9 pr-3">
                    <x-checkbox-button-t title="{{ __('Aktif') }}" wire:model.live="statuses" value="active" name="statuses" id="status-active">
                        <div class="text-center my-auto"><i class="icon-play"></i></div>
                    </x-checkbox-button-t>
                    <x-checkbox-button-t title="{{ __('Nonaktif') }}" wire:model.live="statuses" value="inactive" name="statuses" id="status-inactive">
                        <div class="text-center my-auto"><i class="icon-pause"></i></div>
                    </x-checkbox-button-t>
                </div>
                <div class="pl-3">
                    <x-checkbox wire:model.live="my_projects_only" id="my_projects_only">
                        {{ __('My projects only') }}
                    </x-checkbox>
                </div>
            </div>

            <div class="grow flex items-center gap-x-4 p-4 lg:py-0">
                <x-dropdown align="left" width="48">
                    <x-slot name="trigger">
                        <x-text-button class="uppercase text-xs font-semibold">{{ __('Tim') }}<i class="icon-chevron-down ms-1"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">

                    </x-slot>
                </x-dropdown>
            </div>

            <div class="flex items-center justify-between gap-x-4 p-4 lg:py-0">
                <div>
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <x-text-button><i class="icon-ellipsis"></i></x-text-button>
                        </x-slot>
                        <x-slot name="content">
                            @if(Auth::user()->id === 1)
                                <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'create-project')">
                                    <i class="icon-plus me-2"></i>{{ __('New project')}}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                            @endif
                            <x-dropdown-link href="#" wire:click.prevent="resetQuery">
                                <i class="w-4 icon-rotate-cw me-2"></i>{{ __('Reset')}}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" />
                            <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'download')">
                                <i class="icon-download me-2"></i>{{ __('Unduh sebagai...') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>

    <div class="h-auto sm:h-12">
        <div class="flex items-center flex-col gap-y-6 sm:flex-row justify-between w-full h-full px-8">
            <div class="text-center sm:text-left">{{ $pjt_items->total() . ' ' . __('proyek ') }}</div>
            <div class="grow flex flex-col sm:flex-row gap-3 items-center justify-center sm:justify-end">
                <x-select wire:model.live="sort">
                    <option value="updated">{{ __('Last updated') }}</option>
                    <option value="created">{{ __('Recently created') }}</option>
                    <option value="name">{{ __('Name') }}</option>
                    <option value="team">{{ __('Tim') }}</option>
                </x-select>
                <div class="btn-group">
                    <x-radio-button wire:model.live="view" value="list" name="view" id="view-list"><i
                            class="icon-align-justify text-center m-auto"></i></x-radio-button>
                    <x-radio-button wire:model.live="view" value="grid" name="view" id="view-grid"><i
                            class="icon-layout-grid text-center m-auto"></i></x-radio-button>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-1">
        @if (!$pjt_items->count())
            @if (count($team_ids))
                <div wire:key="no-match" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-ghost"></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">
                        {{ __('Tak ada proyek yang cocok') }}
                    </div>
                </div>
            @else
                <div wire:key="no-team" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-users relative"><i
                                class="icon-circle-help absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih tim') }}
                    </div>
                </div>
            @endif
        @else
            @switch($view)
                @case('grid')
                    <div wire:key="grid"
                        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mt-6 px-3 sm:px-0">
                        @foreach ($pjt_items as $project)
                            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                                <div class="p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 truncate">
                                                <x-link href="{{ route('projects.items.show', $project->id) }}" wire:navigate>
                                                    {{ $project->name }}
                                                </x-link>
                                            </h3>
                                            <p class="text-sm text-neutral-500 line-clamp-2">{{ $project->desc }}</p>
                                        </div>
                                        <span class="ml-2 px-2 py-1 text-xs rounded-full {{ $project->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                            {{ ucfirst($project->status) }}
                                        </span>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="flex items-center text-sm text-neutral-600 dark:text-neutral-400">
                                            <i class="icon-users mr-2"></i>
                                            <span>{{ $project->pjt_team->name }}</span>
                                        </div>

                                        @if($project->location)
                                            <div class="flex items-center text-sm text-neutral-600 dark:text-neutral-400">
                                                <i class="icon-map-pin mr-2"></i>
                                                <span class="truncate">{{ $project->location }}</span>
                                            </div>
                                        @endif

                                        <div class="flex items-center text-sm text-neutral-600 dark:text-neutral-400">
                                            <i class="icon-user mr-2"></i>
                                            <span>{{ $project->user->name }}</span>
                                        </div>

                                        <!-- Progress bar -->
                                        @php $progress = $this->getProgressPercentage($project); @endphp
                                        <div>
                                            <div class="flex justify-between text-xs text-neutral-600 dark:text-neutral-400 mb-1">
                                                <span>{{ __('Progress') }}</span>
                                                <span>{{ $progress }}%</span>
                                            </div>
                                            <div class="w-full bg-neutral-200 rounded-full h-2 dark:bg-neutral-700">
                                                <div class="bg-caldy-500 h-2 rounded-full transition-all duration-300" 
                                                     style="width: {{ $progress }}%"></div>
                                            </div>
                                        </div>

                                        <!-- Team members avatars -->
                                        <div class="flex items-center justify-between">
                                            <div class="flex -space-x-2">
                                                @foreach($project->pjt_members->take(3) as $member)
                                                    <div class="w-6 h-6 bg-neutral-200 dark:bg-neutral-700 rounded-full border-2 border-white dark:border-neutral-800 overflow-hidden">
                                                        @if($member->user->photo)
                                                            <img class="w-full h-full object-cover" src="{{ '/storage/users/' . $member->user->photo }}" />
                                                        @else
                                                            <div class="w-full h-full flex items-center justify-center text-xs text-neutral-600 dark:text-neutral-400">
                                                                {{ substr($member->user->name, 0, 1) }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                @if($project->pjt_members->count() > 3)
                                                    <div class="w-6 h-6 bg-neutral-300 dark:bg-neutral-600 rounded-full border-2 border-white dark:border-neutral-800 flex items-center justify-center">
                                                        <span class="text-xs text-neutral-600 dark:text-neutral-300">+{{ $project->pjt_members->count() - 3 }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="text-xs text-neutral-500">
                                                {{ $this->getActiveTasksCount($project) }} {{ __('active tasks') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @break

                @case('list')
                    <div wire:key="list" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto mt-6">
                        <table class="text-neutral-600 dark:text-neutral-400 w-full table text-sm [&_th]:text-center [&_th]:px-2 [&_th]:py-3 [&_td]:px-2 [&_td]:py-1">
                            <tr class="uppercase text-xs">
                                <th>{{ __('Proyek') }}</th>
                                <th>{{ __('Tim') }}</th>
                                <th>{{ __('Pemilik') }}</th>
                                <th>{{ __('Anggota') }}</th>
                                <th>{{ __('Tugas') }}</th>
                                <th>{{ __('Progress') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Diperbarui') }}</th>
                            </tr>
                            @foreach($pjt_items as $project)
                                <tr class="text-nowrap hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="max-w-40 truncate font-bold">
                                        <x-link href="{{ route('projects.items.show', $project->id) }}" wire:navigate>
                                            {{ $project->name }}
                                        </x-link>
                                    </td>
                                    <td>{{ $project->pjt_team->name }}</td>
                                    <td>{{ $project->user->name }}</td>
                                    <td>{{ $project->pjt_members->count() }}</td>
                                    <td>{{ $this->getActiveTasksCount($project) }}/{{ $project->pjt_tasks->count() }}</td>
                                    <td>
                                        @php $progress = $this->getProgressPercentage($project); @endphp
                                        <div class="flex items-center">
                                            <div class="w-16 bg-neutral-200 rounded-full h-2 dark:bg-neutral-700 mr-2">
                                                <div class="bg-caldy-500 h-2 rounded-full" style="width: {{ $progress }}%"></div>
                                            </div>
                                            <span class="text-xs">{{ $progress }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="px-2 py-1 text-xs rounded-full {{ $project->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                            {{ ucfirst($project->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $project->updated_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @break
            @endswitch

            <div wire:key="observer" class="flex items-center relative h-16">
                @if (!$pjt_items->isEmpty())
                    @if ($pjt_items->hasMorePages())
                        <div wire:key="more" x-data="{
                            observe() {
                                const observer = new IntersectionObserver((projects) => {
                                    projects.forEach(project => {
                                        if (project.isIntersecting) {
                                            @this.loadMore()
                                        }
                                    })
                                })
                                observer.observe(this.$el)
                            }
                        }" x-init="observe"></div>
                        <x-spinner class="sm" />
                    @else
                        <div class="mx-auto">{{ __('Tak ada lagi') }}</div>
                    @endif
                @endif
            </div>
        @endif
    </div>
</div>