<?php

use App\Models\InvCeRecipe;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new #[Layout("layouts.app")] class extends Component {
    public $recipes = [];

    public function mount(): void
    {
        $this->loadRecipes();
    }

    #[On('updated')]
    public function loadRecipes(): void
    {
        $this->recipes = InvCeRecipe::with('chemical', 'hardener')->orderBy('line')->orderBy('model')->get();
    }
};
?>

<x-slot name="title">{{ __("Resep") . " — " . __("Ce Mixing") }}</x-slot>
<x-slot name="header">
    <x-nav-insights-ce-mix />
</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __("Resep Mixing") }}</h1>
            <div class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'recipe-create')">
                        <i class="icon-plus"></i>
                    </x-secondary-button>
                @endcan
            </div>
        </div>

        <div wire:key="recipe-create-modal">
            <x-modal name="recipe-create" maxWidth="xl">
                <livewire:insights.ce.mixing.manage.recipe-create wire:key="ce-recipe-create-modal" lazy />
            </x-modal>
        </div>
        <div wire:key="recipe-edit-modal">
            <x-modal name="recipe-edit" maxWidth="xl">
                <livewire:insights.ce.mixing.manage.recipe-edit wire:key="ce-recipe-edit-modal" lazy />
            </x-modal>
        </div>

        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="recipes-table" class="table">
                        <tr>
                            <th>{{ __("ID") }}</th>
                            <th>{{ __("Line") }}</th>
                            <th>{{ __("Model") }}</th>
                            <th>{{ __("Chemical Base") }}</th>
                            <th>{{ __("Hardener") }}</th>
                            <th>{{ __("Ratio") }}</th>
                            <th>{{ __("Output Code") }}</th>
                            <th>{{ __("Potlife") }}</th>
                            <th>{{ __("Target Weight") }}</th>
                            <th>{{ __("Status") }}</th>
                        </tr>
                        @foreach ($recipes as $recipe)
                            @php
                                $additional_settings = $recipe->additional_settings;
                            @endphp
                            <tr
                                wire:key="recipe-tr-{{ $recipe->id . $loop->index }}"
                                tabindex="0"
                                x-on:click="
                                    $dispatch('open-modal', 'recipe-edit')
                                    $dispatch('recipe-edit', { id: {{ $recipe->id }} })
                                "
                            >
                                <td>{{ $recipe->id }}</td>
                                <td>{{ $recipe->line }}</td>
                                <td>{{ $recipe->model }}</td>
                                <td>{{ $recipe->chemical->name ?? 'N/A' }}</td>
                                <td>{{ $recipe->hardener->name ?? 'N/A' }}</td>
                                <td>{{ $recipe->hardener_ratio }}</td>
                                <td class="font-mono">{{ $recipe->output_code }}</td>
                                <td>{{ $recipe->potlife }} h</td>
                                <td>{{ $additional_settings['target_weight'] ?? 'N/A' }} kg</td>
                                <td>
                                    @if ($recipe->is_active)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            {{ __("Aktif") }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            {{ __("Nonaktif") }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="recipes-none">
                        @if (!$recipes->count())
                            <div class="text-center py-12">
                                {{ __("Tak ada resep ditemukan") }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
