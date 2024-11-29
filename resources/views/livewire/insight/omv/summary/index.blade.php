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

    public function mount()
    {
        $this->view_titles = [        
            'batch-count'   => __('Jumlah batch'),
            'worker-perf'   => __('Performa pekerja'),
            'running-time'  => __('Waktu Jalan'),
            'metrics'       => __('Data mentah'),    
        ];
    }

    public function getViewTitle(): string
    {
        return $this->view_titles[$this->view] ?? '';
    }
};

?>

<x-slot name="title">{{ __('Open Mill Validation') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-omv></x-nav-insights-omv>
</x-slot>

<div id="content" class="relative py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @vite(['resources/js/apexcharts.js'])
    <div wire:key="omv-summary-index-nav" class="flex px-8 mb-6">
        <x-dropdown align="left">
            <x-slot name="trigger">
                <x-text-button type="button" class="flex gap-2 items-center ml-1"><div class="text-2xl">{{ $this->getViewTitle() }}</div><i class="fa fa-fw fa-chevron-down"></i></x-text-button>
            </x-slot>
            <x-slot name="content">
                @foreach ($view_titles as $view_key => $view_title)
                <x-dropdown-link href="#" wire:click.prevent="$set('view', '{{ $view_key }}')">{{ $view_title }}</x-dropdown-link>
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
            <livewire:insight.omv.summary.batch-count />                       
                @break
            @case('worker-perf')
            <livewire:insight.omv.summary.worker-perf />                       
                @break
            @case('running-time')
            <livewire:insight.omv.summary.running-time />                       
                @break
            @case('line')
                <div class="w-full py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="fa fa-hammer relative"><i
                                class="fa fa-exclamation-triangle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Dalam tahap pengembangan') }}
                    </div>
                </div>            
                @break
            @case('team')
                <div class="w-full py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="fa fa-hammer relative"><i
                                class="fa fa-exclamation-triangle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Dalam tahap pengembangan') }}
                    </div>
                </div>                  
                @break
            @case('metrics')
                <livewire:insight.omv.summary.metrics />
                @break
            @default
                <div wire:key="no-view" class="w-full py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="fa fa-tv relative"><i
                                class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih tampilan') }}
                    </div>
                </div>                
        @endswitch
    </div>
</div>
