<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\InsRtcRecipe;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public $q = '';

    public $perPage = 20;

    #[On('updated')]
    public function with(): array
    {
        $q = trim($this->q);
        $recipes = InsRtcRecipe::where(function (Builder $query) use ($q) {
            $query
                ->orWhere('name', 'LIKE', '%' . $q . '%')
                ->orWhere('og_rs', 'LIKE', '%' . $q . '%')
                ->orWhere('std_min', 'LIKE', '%' . $q . '%')
                ->orWhere('std_max', 'LIKE', '%' . $q . '%')
                ->orWhere('std_mid', 'LIKE', '%' . $q . '%')
                ->orWhere('scale', 'LIKE', '%' . $q . '%')
                ->orWhere('pfc_min', 'LIKE', '%' . $q . '%')
                ->orWhere('pfc_max', 'LIKE', '%' . $q . '%');
        })
            ->orderBy('id')
            ->paginate($this->perPage);

        return [
            'recipes' => $recipes,
        ];
    }

    public function updating($property)
    {
        if ($property == 'q') {
            $this->reset('perPage');
        }
    }

    public function loadMore()
    {
        $this->perPage += 20;
    }
};
?>
<x-slot name="title">{{ __('Resep') . ' — ' . __('Kendali tebal calendar') }}</x-slot>
<x-slot name="header">
    <x-nav-insights-rtc-sub />
</x-slot>
<div id="content" class="py-12 max-w-5xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Resep') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'recipe-create')"><i class="fa fa-plus fa-fw"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="fa fa-search fa-fw"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="inv-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="recipe-create">
            <x-modal name="recipe-create" maxWidth="lg">
                <livewire:insights.rtc.manage.recipe-create />
            </x-modal>
        </div>
        <div wire:key="recipe-edit">   
            <x-modal name="recipe-edit">
                <livewire:insights.rtc.manage.recipe-edit />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="recipes-table" class="table">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('OG/RS') }}</th>
                            <th>{{ __('Min') }}</th>
                            <th>{{ __('Maks') }}</th>
                            <th>{{ __('Tengah') }}</th>
                            <th>{{ __('Skala') }}</th>
                            <th>{{ __('Min (PFC)') }}</th>
                            <th>{{ __('Maks (PFC)') }}</th>
                        </tr>
                        @foreach ($recipes as $recipe)
                            <tr wire:key="recipe-tr-{{ $recipe->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'recipe-edit'); $dispatch('recipe-edit', { id: {{ $recipe->id }} })">
                                <td>
                                    {{ $recipe->id }}
                                </td>
                                <td>
                                    {{ $recipe->name }}
                                </td>
                                <td>
                                    {{ $recipe->og_rs }}
                                </td>
                                <td>
                                    {{ $recipe->std_min }}
                                </td>
                                <td>
                                    {{ $recipe->std_max }}
                                </td>
                                <td>
                                    {{ $recipe->std_mid }}
                                </td>
                                <td>
                                    {{ $recipe->scale }}
                                </td>
                                <td>
                                    {{ $recipe->pfc_min }}
                                </td>
                                <td>
                                    {{ $recipe->pfc_max }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="recipes-none">
                        @if (!$recipes->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada resep ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$recipes->isEmpty())
                @if ($recipes->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((recipes) => {
                                recipes.forEach(recipe => {
                                    if (recipe.isIntersecting) {
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
