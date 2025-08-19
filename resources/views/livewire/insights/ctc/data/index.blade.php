<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

new #[Layout('layouts.app')]
class extends Component {
   
    #[Url]
    public $view = 'recents';
   
    public array $view_titles = [];
    public array $view_icons = [];

    public function mount()
    {
        $this->view_titles = [
            'recents'               => __('Batch terkini'),
            'evaluation'            => __('Evaluasi'), // quality compared with production
            'batch-analytics'       => __('Analitik Batch'),
            'correction-analytics'  => __('Analitik Koreksi'), // correction efficiency
            'machine-performance'   => __('Kinerja Mesin'),
            'metrics'               => __('Data mentah'),
            // 'recipe-analytics'      => __('Analitik Resep'),
        ];

        $this->view_icons = [
            'recents'               => 'icon-clock',
            'evaluation'            => 'icon-shield-check',
            'batch-analytics'       => 'icon-layers',
            'correction-analytics'  => 'icon-file-sliders',
            'machine-performance'   => 'icon-zap',
            'metrics'               => 'icon-database',
            'recipe-analytics'      => 'icon-book',
        ];
    }

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

<x-slot name="title">{{ __('Data - Kendali tebal calendar') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-ctc></x-nav-insights-ctc>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    
    <div wire:key="ctc-data-nav" class="flex px-8 mb-6">
        <x-dropdown align="left" width="60">
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
   
    <div wire:key="ctc-data-container" wire:loading.class="hidden">
        @switch($view)
            @case('recents')
                <livewire:insights.ctc.data.recents />
                @break
            @case('metrics')
                <livewire:insights.ctc.data.metrics />
                @break
            @case('batch-analytics')
                <livewire:insights.ctc.data.batch-analytics />
                @break
            @case('machine-performance')
                <livewire:insights.ctc.data.machine-performance />
                @break
            @case('recipe-analytics')
                <livewire:insights.ctc.data.recipe-analytics />
                @break
            @case('correction-analytics')
                <livewire:insights.ctc.data.correction-analytics />
                @break
            @case('evaluation')
                <livewire:insights.ctc.data.evaluation />
                @break
            @default
                <div wire:key="no-view" class="w-full py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-tv-minimal relative"><i class="icon-circle-help absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih tampilan') }}</div>
                </div>                
        @endswitch
    </div>
</div>
