<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Database\Eloquent\Builder;

new #[Layout("layouts.app")] class extends Component {
    use WithPagination;

    #[Url]
    public $q = "";

    public $perPage = 20;

    #[On("updated")]
    public function with(): array
    {
        $q = trim($this->q);

        // Mock recipes data for development
        $mockRecipes = [
            [
                "id" => 1,
                "name" => "AF1 GS (ONE COLOR)",
                "og_rs" => "GS",
                "std_min" => 3.0,
                "std_max" => 3.1,
                "std_mid" => 3.05,
                "scale" => 1.0,
                "pfc_min" => 3.4,
                "pfc_max" => 3.6,
                "recommended_for_models" => ["AF1"],
                "priority" => 1,
                "is_active" => true,
            ],
            [
                "id" => 2,
                "name" => "AF1 WS (TWO COLOR)",
                "og_rs" => "WS",
                "std_min" => 3.0,
                "std_max" => 3.1,
                "std_mid" => 3.05,
                "scale" => 1.0,
                "pfc_min" => 3.2,
                "pfc_max" => 3.4,
                "recommended_for_models" => ["AF1"],
                "priority" => 2,
                "is_active" => true,
            ],
            [
                "id" => 3,
                "name" => "AM 270 (CENTER)",
                "og_rs" => "RS",
                "std_min" => 2.7,
                "std_max" => 2.9,
                "std_mid" => 2.8,
                "scale" => 1.0,
                "pfc_min" => 2.7,
                "pfc_max" => 2.9,
                "recommended_for_models" => ["AM270"],
                "priority" => 1,
                "is_active" => true,
            ],
            [
                "id" => 4,
                "name" => "AM 95 (HEEL)",
                "og_rs" => "RS",
                "std_min" => 2.8,
                "std_max" => 3.0,
                "std_mid" => 2.9,
                "scale" => 1.0,
                "pfc_min" => 2.8,
                "pfc_max" => 3.0,
                "recommended_for_models" => ["AM95"],
                "priority" => 1,
                "is_active" => true,
            ],
            [
                "id" => 5,
                "name" => "ALPHA 5",
                "og_rs" => "RS",
                "std_min" => 3.2,
                "std_max" => 3.4,
                "std_mid" => 3.3,
                "scale" => 1.0,
                "pfc_min" => 3.2,
                "pfc_max" => 3.4,
                "recommended_for_models" => ["ALPHA"],
                "priority" => 1,
                "is_active" => false,
            ],
        ];

        // Apply search filter if provided
        if ($q) {
            $mockRecipes = array_filter($mockRecipes, function ($recipe) use ($q) {
                return stripos($recipe["name"], $q) !== false || stripos($recipe["og_rs"], $q) !== false || in_array($q, $recipe["recommended_for_models"]);
            });
        }

        // Apply pagination
        $recipes = collect($mockRecipes)->take($this->perPage);

        return [
            "recipes" => $recipes,
        ];
    }

    public function updating($property)
    {
        if ($property == "q") {
            $this->reset("perPage");
        }
    }

    public function loadMore()
    {
        $this->perPage += 20;
    }
};
?>

<x-slot name="title">{{ __("Resep") . " — " . __("Kendali tebal calendar") }}</x-slot>
<x-slot name="header">
    <x-nav-insights-ctc-sub />
</x-slot>
<div id="content" class="py-12 max-w-5xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __("Resep") }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can("superuser")
                    <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'recipe-create')"><i class="icon-plus"></i></x-secondary-button>
                @endcan

                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open">
                    <i class="icon-search"></i>
                </x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="inv-q" x-ref="search" placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="recipe-create">
            <x-modal name="recipe-create" maxWidth="lg">
                <livewire:insights.ctc.manage.recipe-create />
            </x-modal>
        </div>
        <div wire:key="recipe-edit">
            <x-modal name="recipe-edit">
                <livewire:insights.ctc.manage.recipe-edit />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="recipes-table" class="table">
                        <tr>
                            <th>{{ __("ID") }}</th>
                            <th>{{ __("Nama") }}</th>
                            <th>{{ __("OG/RS") }}</th>
                            <th>{{ __("Min") }}</th>
                            <th>{{ __("Maks") }}</th>
                            <th>{{ __("Tengah") }}</th>
                            <th>{{ __("Prioritas") }}</th>
                            <th>{{ __("Model") }}</th>
                            <th>{{ __("Status") }}</th>
                        </tr>
                        @foreach ($recipes as $recipe)
                            <tr
                                wire:key="recipe-tr-{{ $recipe["id"] . $loop->index }}"
                                tabindex="0"
                                x-on:click="
                                    $dispatch('open-modal', 'recipe-edit')
                                    $dispatch('recipe-edit', { id: {{ $recipe["id"] }} })
                                "
                            >
                                <td>{{ $recipe["id"] }}</td>
                                <td>{{ $recipe["name"] }}</td>
                                <td>{{ $recipe["og_rs"] }}</td>
                                <td>{{ $recipe["std_min"] }}</td>
                                <td>{{ $recipe["std_max"] }}</td>
                                <td>{{ $recipe["std_mid"] }}</td>
                                <td>{{ $recipe["priority"] }}</td>
                                <td>{{ implode(", ", $recipe["recommended_for_models"]) }}</td>
                                <td>
                                    @if ($recipe["is_active"])
                                        <span class="inline-flex px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">{{ __("Aktif") }}</span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">{{ __("Nonaktif") }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="recipes-none">
                        @if (! $recipes->count())
                            <div class="text-center py-12">
                                {{ __("Tak ada resep ditemukan") }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (! $recipes->isEmpty())
                @if ($recipes->count() >= $this->perPage)
                    <div
                        wire:key="more"
                        x-data="{
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
                    }"
                        x-init="observe"
                    ></div>
                    <x-spinner class="sm" />
                @else
                    <div class="mx-auto">{{ __("Tidak ada lagi") }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
