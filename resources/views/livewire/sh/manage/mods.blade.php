<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\ShMod;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] 
class extends Component {
    use WithPagination;

    #[Url]
    public $q = '';

    public $perPage = 20;

    #[On('updated')]
    public function with(): array
    {
        $q = trim($this->q);
        $mods = ShMod::where(function (Builder $query) use ($q) {
            $query
                ->orWhere('name', 'LIKE', '%' . $q . '%');
            })
            ->orderBy('id')
            ->paginate($this->perPage);

        return [
            'mods' => $mods,
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
<x-slot name="title">{{ __('Model') . ' — ' . __('Admin') }}</x-slot>
<x-slot name="header">
    <x-nav-sh-sub />
</x-slot>
<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Model') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'mod-create')"><i class="fa fa-plus fa-fw"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="fa fa-search fa-fw"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="inv-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="mod-create">
            <x-modal name="mod-create">
                <livewire:sh.manage.mod-create />
            </x-modal>
        </div>
        <div wire:key="mod-edit">   
            <x-modal name="mod-edit">
                <livewire:sh.manage.mod-edit />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="mods-table" class="table">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Terakhir digunakan') }}</th>
                        </tr>
                        @foreach ($mods as $mod)
                            <tr wire:key="mod-tr-{{ $mod->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'mod-edit'); $dispatch('mod-edit', { id: {{ $mod->id }} })">
                                <td>
                                    {{ $mod->id }}
                                </td>
                                <td>
                                    {{ $mod->name }}
                                </td>
                                <td>
                                    {{ $mod->is_active ? __('Aktif') : __('Nonaktif') }}
                                </td>
                                <td>
                                    {{ $mod->updated_at }}                                    
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="mods-none">
                        @if (!$mods->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada model ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$mods->isEmpty())
                @if ($mods->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((mods) => {
                                mods.forEach(mod => {
                                    if (mod.isIntersecting) {
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
