<?php

use App\Models\InsDwpAuth;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

new #[Layout("layouts.app")] class extends Component {
    use WithPagination;

    #[Url]
    public $q = "";
    public $perPage = 10;

    #[On("updated")]
    public function with(): array
    {
        $query = InsDwpAuth::with('user')
            ->when($this->q, function ($query) {
                $query->whereHas('user', function ($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->q . '%')
                             ->orWhere('emp_id', 'like', '%' . $this->q . '%');
                });
            });

        return [
            "auths" => $query->paginate($this->perPage),
            "available_actions" => InsDwpAuth::availableActions(),
        ];
    }

    public function updated($property)
    {
        if ($property == "q") {
            $this->resetPage();
        }
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    public function deleteAuth($authId)
    {
        try {
            InsDwpAuth::findOrFail($authId)->delete();
            session()->flash('message', 'Authorization deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting authorization: ' . $e->getMessage());
        }
    }
};

?>

<x-slot name="title">{{ __("Wewenang") . " — " . __("Pemantauan proses DWP") }}</x-slot>
<x-slot name="header">
    <x-nav-insights-dwp-sub />
</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <!-- Flash Messages -->
        @if (session()->has('message'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __("Wewenang") }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can("superuser")
                    <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'auth-create')">
                        <i class="icon-plus"></i>
                    </x-secondary-button>
                @endcan

                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open">
                    <i class="icon-search"></i>
                </x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input wire:model.live.debounce.300ms="q" x-ref="search" 
                                  x-on:blur="open = false" x-on:keydown.escape.window="open = false" 
                                  placeholder="{{ __('Cari...') }}" class="w-full" />
                </div>
            </div>
        </div>

        <div class="my-8">
            @if($auths->count() > 0)
                @foreach($auths as $auth)
                    <div class="bg-white dark:bg-neutral-800 shadow rounded-lg mb-4 p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center space-x-4">
                                @if($auth->user->photo)
                                    <img class="h-12 w-12 rounded-full object-cover" 
                                         src="{{ Storage::url('public/user-photos/' . $auth->user->photo) }}" 
                                         alt="{{ $auth->user->name }}">
                                @else
                                    <div class="h-12 w-12 rounded-full bg-neutral-300 dark:bg-neutral-600 flex items-center justify-center">
                                        <i class="icon-user text-neutral-500 dark:text-neutral-400"></i>
                                    </div>
                                @endif
                                
                                <div>
                                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ $auth->user->name }}
                                    </h3>
                                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ $auth->user->emp_id }}
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex space-x-2">
                                @can("superuser")
                                    <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'auth-edit-{{ $auth->id }}')">
                                        <i class="icon-edit"></i>
                                    </x-secondary-button>
                                    <x-danger-button type="button" 
                                                   wire:click="deleteAuth({{ $auth->id }})" 
                                                   onclick="return confirm('{{ __('Are you sure you want to delete this authorization?') }}')">
                                        <i class="icon-trash"></i>
                                    </x-danger-button>
                                @endcan
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h4 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                                {{ __("Permissions") }} ({{ count($auth->actions ?? []) }})
                            </h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach($auth->actions ?? [] as $action)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {{ $available_actions[$action] ?? $action }}
                                    </span>
                                @endforeach
                                @if(empty($auth->actions))
                                    <span class="text-sm text-neutral-500 dark:text-neutral-400 italic">
                                        {{ __("No permissions assigned") }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                <!-- Load More -->
                @if($auths->hasMorePages())
                    <div class="text-center mt-6">
                        <x-secondary-button wire:click="loadMore" type="button">
                            {{ __("Load more") }}
                        </x-secondary-button>
                    </div>
                @endif
            @else
                <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-8 text-center">
                    <div class="text-neutral-400 dark:text-neutral-600 text-4xl mb-4">
                        <i class="icon-users"></i>
                    </div>
                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-2">
                        {{ __("No authorizations found") }}
                    </h3>
                    <p class="text-neutral-500 dark:text-neutral-400">
                        @if($this->q)
                            {{ __("No authorizations match your search.") }}
                        @else
                            {{ __("No user authorizations have been created yet.") }}
                        @endif
                    </p>
                </div>
            @endif
        </div>

        <!-- Create Modal would go here -->
        @can("superuser")
            <!-- Create and Edit modals would be implemented here following the same pattern as CTC -->
        @endcan
    </div>
</div>