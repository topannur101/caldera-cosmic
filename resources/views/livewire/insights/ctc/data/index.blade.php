<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

new #[Layout('layouts.app')] 
class extends Component {
    
    #[Url]
    public $view = 'recents';
    
    public array $view_titles = [];

    public function mount()
    {
        $this->view_titles = [   
            'recents'   => __('Batch terkini'),
            // 'realtime'  => __('Waktu nyata'),
            'quality'   => __('Analisis kualitas'),
            'metrics'   => __('Data mentah'),
        ];
    }

    public function getViewTitle(): string
    {
        return $this->view_titles[$this->view] ?? '';
    }
};

?>

<x-slot name="title">{{ __('Data - Kendali tebal calendar') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-ctc></x-nav-insights-ctc>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div wire:key="ctc-data-nav" class="flex px-8 mb-6">
        <x-dropdown align="left">
            <x-slot name="trigger">
                <x-text-button type="button" class="flex gap-2 items-center ml-1">
                    <div class="text-2xl">{{ $this->getViewTitle() }}</div>
                    <i class="icon-chevron-down"></i>
                </x-text-button>
            </x-slot>
            <x-slot name="content">
                @foreach ($view_titles as $view_key => $view_title)
                    <x-dropdown-link href="#" wire:click.prevent="$set('view', '{{ $view_key }}')">{{ $view_title }}</x-dropdown-link>
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
                @break

            @case('realtime')
                <livewire:insights.ctc.data.realtime />
                @break

            @case('quality')
                <livewire:insights.ctc.data.quality />
                @break

            @case('metrics')
                <livewire:insights.ctc.data.metrics />
                @break

            @default
                <div wire:key="no-view" class="w-full py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-tv-minimal relative"><i
                                class="icon-circle-help absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih tampilan') }}
                    </div>
                </div>                
        @endswitch
    </div>
</div>