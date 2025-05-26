<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\InvOrderBudget;
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
        $budgets = InvOrderBudget::with(['inv_area', 'inv_curr'])
            ->orderBy('id', 'desc');

        if ($q) {
            $budgets->where(function (Builder $query) use ($q) {
                $query
                    ->orWhere('name', 'LIKE', '%' . $q . '%')
                    ->orWhereHas('inv_area', function (Builder $areaQuery) use ($q) {
                        $areaQuery->where('name', 'LIKE', '%' . $q . '%');
                    });
            });
        }

        return [
            'budgets' => $budgets->paginate($this->perPage),
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
<x-slot name="title">{{ __('Inventaris') . ' — ' . __('Admin') }}</x-slot>

<x-slot name="header">
    <x-nav-admin>{{ __('Inventaris') }}</x-nav-admin>
</x-slot>

<div id="content" class="py-12 max-w-5xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Kelola anggaran') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'budget-create')"><i class="icon-plus"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="icon-search"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="budget-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="budget-create">
            <x-modal name="budget-create">
                <livewire:admin.inventory.budget-create />
            </x-modal>
        </div>
        <div wire:key="budget-edit">
            <x-modal name="budget-edit">
                <livewire:admin.inventory.budget-edit />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="budgets-table" class="table">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Saldo') }}</th>
                            <th>{{ __('Mata uang') }}</th>
                            <th>{{ __('Area') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                        @foreach ($budgets as $budget)
                            <tr wire:key="budget-tr-{{ $budget->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'budget-edit'); $dispatch('budget-edit', { id: {{ $budget->id }} })">
                                <td>
                                    {{ $budget->id }}
                                </td>
                                <td>
                                    {{ $budget->name }}
                                </td>
                                <td>
                                    {{ number_format($budget->balance, 2) }}
                                </td>
                                <td>
                                    {{ $budget->inv_curr->name }}
                                </td>
                                <td>
                                    {{ $budget->inv_area->name }}
                                </td>
                                <td>
                                    {{ $budget->is_active ? __('Aktif') : __('Nonaktif') }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="budgets-none">
                        @if (!$budgets->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada anggaran ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$budgets->isEmpty())
                @if ($budgets->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((budgets) => {
                                budgets.forEach(budget => {
                                    if (budget.isIntersecting) {
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