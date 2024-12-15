<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\InsRdcTag;
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
        $tags = InsRdcTag::where(function (Builder $query) use ($q) {
            $query
                ->orWhere('name', 'LIKE', '%' . $q . '%');
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        return [
            'tags' => $tags,
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
<x-slot name="title">{{ __('Tag') . ' — ' . __('Sistem data rheometer') }}</x-slot>
<x-slot name="header">
    <x-nav-insights-rdc-sub />
</x-slot>
<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Tag') }}</h1>
            <div x-data="{ open: false }" class="flex justify-end gap-x-2">
                @can('superuser')
                    <x-secondary-button type="button" 
                        x-on:click.prevent="$dispatch('open-modal', 'tag-create')"><i class="fa fa-plus fa-fw"></i></x-secondary-button>
                @endcan
                <x-secondary-button type="button" x-on:click="open = true; setTimeout(() => $refs.search.focus(), 100)" x-show="!open"><i class="fa fa-search fa-fw"></i></x-secondary-button>
                <div class="w-40" x-show="open" x-cloak>
                    <x-text-input-search wire:model.live="q" id="inv-q" x-ref="search"
                        placeholder="{{ __('CARI') }}"></x-text-input-search>
                </div>
            </div>
        </div>
        <div wire:key="tag-create">
            <x-modal name="tag-create">
                <livewire:insight.rdc.manage.tag-create />
            </x-modal>
        </div>
        <div wire:key="tag-edit">   
            <x-modal name="tag-edit">
                <livewire:insight.rdc.manage.tag-edit />
            </x-modal>
        </div>
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="tags-table" class="table">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Nama') }}</th>
                        </tr>
                        @foreach ($tags as $tag)
                            <tr wire:key="tag-tr-{{ $tag->id . $loop->index }}" tabindex="0"
                                x-on:click="$dispatch('open-modal', 'tag-edit'); $dispatch('tag-edit', { id: {{ $tag->id }} })">
                                <td>
                                    {{ $tag->id }}
                                </td>
                                <td>
                                    {{ $tag->name }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div wire:key="tags-none">
                        @if (!$tags->count())
                            <div class="text-center py-12">
                                {{ __('Tak ada tag ditemukan') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div wire:key="observer" class="flex items-center relative h-16">
            @if (!$tags->isEmpty())
                @if ($tags->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((tags) => {
                                tags.forEach(tag => {
                                    if (tag.isIntersecting) {
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
