<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

new #[Layout('layouts.app')] 
class extends Component {
    
    #[Url]
    public $view = 'batch-count';
    public array $view_titles = [];
    public array $view_icons = [];

    public function mount()
    {
        $this->view_titles = [        
            'batch-count'   => __('Ringkasan batch'),
            'worker-perf'   => __('Performa pekerja'),
            'running-time'  => __('Waktu Jalan'),
            'metrics'       => __('Data mentah'),    
        ];

        $this->view_icons = [
            'batch-count'   => 'icon-layers',
            'worker-perf'   => 'icon-users',
            'running-time'  => 'icon-clock',
            'metrics'       => 'icon-database',
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

<x-slot name="title">{{ __('Open Mill Validation') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-omv></x-nav-insights-omv>
</x-slot>

<div id="content" class="relative py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    
    <div wire:key="omv-summary-index-nav" class="flex px-8 mb-6">
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
                {{-- <hr class="border-neutral-300 dark:border-neutral-600" /> --}}
            </x-slot>
        </x-dropdown>
    </div>
    <div wire:loading.class.remove="hidden" class="hidden h-96">
        <x-spinner></x-spinner>
    </div>
    <div wire:key="omv-summary-index-container" wire:loading.class="hidden">
        @switch($view)
            @case('batch-count')
            <livewire:insights.omv.data.batch-count />                       
                @break
            @case('worker-perf')
            <livewire:insights.omv.data.worker-perf />                       
                @break
            @case('running-time')
            <livewire:insights.omv.data.running-time />                       
                @break
            @case('line')
                <div class="w-full py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-hammer relative"><i
                                class="icon-triangle-alert absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Dalam tahap pengembangan') }}
                    </div>
                </div>            
                @break
            @case('team')
                <div class="w-full py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-hammer relative"><i
                                class="icon-triangle-alert absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Dalam tahap pengembangan') }}
                    </div>
                </div>                  
                @break
            @case('metrics')
                <livewire:insights.omv.data.metrics />
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
