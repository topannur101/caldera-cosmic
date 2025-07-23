<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    public string $view = 'overview';
    
    public array $view_titles = [
        'overview' => 'Ikhtisar',
    ];

    public array $view_icons = [
        'overview' => 'icon-chart-line',
    ];

    public function getViewTitle(): string
    {
        return $this->view_titles[$this->view] ?? '';
    }

    public function getViewIcon(): string
    {
        return $this->view_icons[$this->view] ?? '';
    }
};

?>

<x-slot name="title">{{ __('Dasbor') . ' — ' . __('Tugas') }}</x-slot>

<x-slot name="header">
    <x-nav-task></x-nav-task>
</x-slot>

<div id="content" class="relative py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    
    <div wire:key="task-dashboard-nav" class="flex px-8 mb-6">
        <x-dropdown align="left">
            <x-slot name="trigger">
                <x-text-button type="button" class="flex gap-2 items-center ml-1">
                    <i class="{{ $this->getViewIcon() }}"></i>
                    <div class="text-2xl">{{ $this->getViewTitle() }}</div>
                    <i class="icon-chevron-down"></i>
                </x-text-button>
            </x-slot>
            <x-slot name="content">
                @foreach ($view_titles as $view_key => $view_title)
                <x-dropdown-link href="#" wire:click.prevent="$set('view', '{{ $view_key }}')" class="flex items-center gap-2">
                    <i class="{{ $view_icons[$view_key] }}"></i>
                    <span>{{ $view_title }}</span>
                    @if($view === $view_key)
                        <div class="ml-auto w-2 h-2 bg-caldy-500 rounded-full"></div>
                    @endif
                </x-dropdown-link>
                @endforeach
            </x-slot>
        </x-dropdown>
    </div>

    <div wire:loading.class.remove="hidden" class="hidden h-96">
        <x-spinner></x-spinner>
    </div>

    <div wire:key="task-dashboard-container" wire:loading.class="hidden">
        @switch($view)
            @case('overview')
                <livewire:tasks.dashboard.overview />
                @break

            @default
                <div wire:key="no-view" class="w-full py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-600">
                        <i class="icon-alert-circle text-6xl mb-4"></i>
                        <p>{{ __('Tampilan tidak ditemukan') }}</p>
                    </div>
                </div>
        @endswitch
    </div>
</div>