<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

new #[Layout('layouts.app')] 
class extends Component {
    
    #[Url]
    public $view = 'summary-readings';
    public array $view_titles = [];

    public function mount()
    {
        $this->view_titles = [   
            'summary-readings'      => __('Ringkasan pembacaan'),
            'summary-operational'   => __('Ringkasan operasional'),
            'readings'              => __('Pembacaan'),   
            'history'               => __('Riwayat'), 
            'comparison'            => __('Perbandingan'),
        ];
    }

    public function getViewTitle(): string
    {
        return $this->view_titles[$this->view] ?? '';
    }
};

?>

<x-slot name="title">{{ __('Kendali chamber IP') }}</x-slot>

<x-slot name="header">
    <link href="/print-landscape.css" type="text/css" rel="stylesheet" media="print">
    <x-nav-insights-stc></x-nav-insights-stc>
</x-slot>

<x-slot name="printable">
    <livewire:insights.stc.data.d-sum-print />
</x-slot>

<div id="content" class="relative py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @vite(['resources/js/apexcharts.js'])
    <div wire:key="stc-data-index-nav" class="flex px-8 mb-6">
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
    <div wire:key="stc-data-index-container" wire:loading.class="hidden">
        @switch($view)

            @case('summary-readings')
            <livewire:insights.stc.data.summary-readings />
                @break

            @case('summary-operational')
            <livewire:insights.stc.data.summary-operational />
                @break
                
            @case('readings')
            <livewire:insights.stc.data.readings />                       
                @break

            @case('history')
            <livewire:insights.stc.data.history />                       
                @break

            @case('comparison')
            <livewire:insights.stc.data.comparison />
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
