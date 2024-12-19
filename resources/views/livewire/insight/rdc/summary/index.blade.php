<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    #[Url]
    public $view = 'tests';

    #[Url]
    public $start_at;

    #[Url]
    public $end_at;

    #[Url]
    public $fquery;

    #[Url]
    public $ftype = 'any';

    public $dateViews = ['tests', 'by-mcs'];

    public $rangeViews = ['tests', 'by-mcs'];

    public $filterViews = ['tests'];

    public $is_date;

    public $is_range;

    public $is_filter;

    public function mount()
    {
        if (! $this->start_at || ! $this->end_at) {
            $this->setToday();
        }
    }

    public function with(): array
    {
        $this->is_date = in_array($this->view, $this->dateViews);
        $this->is_range = in_array($this->view, $this->rangeViews);
        $this->is_filter = in_array($this->view, $this->filterViews);

        return [];
    }

    public function setToday()
    {
        $this->start_at = Carbon::now()->startOfDay()->format('Y-m-d');
        $this->end_at = Carbon::now()->endOfDay()->format('Y-m-d');
    }

    public function setYesterday()
    {
        $this->start_at = Carbon::yesterday()->startOfDay()->format('Y-m-d');
        $this->end_at = Carbon::yesterday()->endOfDay()->format('Y-m-d');
    }

    public function setThisMonth()
    {
        $this->start_at = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->end_at = Carbon::now()->endOfMonth()->format('Y-m-d');
    }

    public function setLastMonth()
    {
        $this->start_at = Carbon::now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
        $this->end_at = Carbon::now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d');
    }

    public function resetFilter()
    {
        $this->reset('fquery', 'ftype');
    }

    public function filterPass()
    {
        $this->ftype = 'eval';
        $this->fquery = 'pass';
    }

    public function filterFail()
    {
        $this->ftype = 'eval';
        $this->fquery = 'fail';
    }

    public function filterByMe()
    {
        if (Auth::user()) {
            $this->ftype = 'emp_id';
            $this->fquery = Auth::user()->emp_id;
        }
    }
};

?>



<x-slot name="title">{{ __('Sistem data rheometer') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-rdc></x-nav-insights-rdc>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 grid gap-1">
    @vite(['resources/js/apexcharts.js'])
    <div class="flex flex-col gap-x-2 md:gap-x-4 sm:flex-row min-w-0">
        <div>
            <div class="w-full sm:w-44 md:w-64 px-3 sm:px-0 mb-5">
                <div class="btn-group h-10 w-full">
                    <x-radio-button wire:model.live="view" grow value="by-mcs" name="view" id="view-by-mcs">
                        <div class="text-center my-auto">
                            <i class="fa fa-fw fa-line-chart text-center m-auto"></i>
                        </div>
                    </x-radio-button>
                    <x-radio-button wire:model.live="view" grow value="tests" name="view" id="view-tests">
                        <div class="text-center my-auto">
                            <i class="fa fa-fw fa-table text-center m-auto"></i>
                        </div>
                    </x-radio-button>
                </div>
                <div
                    class="mt-4 bg-white dark:bg-neutral-800 shadow rounded-lg py-5 px-4 {{ $is_date ? '' : 'hidden' }}">
                    <div class="flex items-start justify-between">
                        <div><i class="fa fa-calendar mr-3"></i>{{ $is_range ? __('Rentang') : __('Tanggal') }}</div>
                        <div class="flex items-center">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link href="#" wire:click.prevent="setToday">
                                        {{ __('Hari ini') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                        {{ __('Kemarin') }}
                                    </x-dropdown-link>
                                    <hr
                                        class="border-neutral-300 dark:border-neutral-600 {{ $is_range ? '' : 'hidden' }}" />
                                    <x-dropdown-link href="#" wire:click.prevent="setThisMonth"
                                        class="{{ $is_range ? '' : 'hidden' }}">
                                        {{ __('Bulan ini') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link href="#" wire:click.prevent="setLastMonth"
                                        class="{{ $is_range ? '' : 'hidden' }}">
                                        {{ __('Bulan lalu') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>
                    <div class="mt-5">
                        <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                        <x-text-input wire:model.live="end_at" id="cal-date-end" type="date"
                            class="mt-3 mb-1 {{ $is_range ? '' : 'hidden' }}"></x-text-input>
                    </div>
                    {{-- <div class="mt-5">
                        <x-radio id="is_workdate_false" wire:model.live="is_workdate" name="is_workdate"
                        :checked="!$is_workdate" value="">{{ __('Tanggal catat') }}</x-radio>
                        <x-radio id="is_workdate_true" wire:model.live="is_workdate" name="is_workdate" :checked="$is_workdate"
                            value="true">{{ __('Tanggal WO') }}</x-radio>
                    </div> --}}
                </div>
                <div
                    class="mt-4 bg-white dark:bg-neutral-800 shadow rounded-lg py-5 px-4 {{ $is_filter ? '' : 'hidden' }}">
                    <div class="flex items-start justify-between">
                        <div><i class="fa fa-filter mr-3"></i>{{ __('Filter') }}</div>
                        <div class="flex items-center">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link href="#" wire:click.prevent="filterPass">
                                        <x-pill class="uppercase" color="green">{{ __('PASS') }}</x-pill>
                                    </x-dropdown-link>
                                    <x-dropdown-link href="#" wire:click.prevent="filterFail">
                                        <x-pill class="uppercase" color="red">{{ __('FAIL') }}</x-pill>
                                    </x-dropdown-link>
                                    @if(Auth::user())
                                    <x-dropdown-link href="#" wire:click.prevent="filterByMe">
                                        {{ __('Oleh aku') }}
                                    </x-dropdown-link>
                                    <hr
                                    class="border-neutral-300 dark:border-neutral-600 {{ $is_range ? '' : 'hidden' }}" />
                                    @endif
                                    <x-dropdown-link href="#" wire:click.prevent="resetFilter">
                                        {{ __('Kosongkan filter') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>
                    <div class="mt-3">
                        <x-select class="w-full" id="hides-ftype" wire:model.live="ftype">
                            <option value="any">{{ __('Apapun') }}</option>
                            <option value="code">{{ __('Kode') }}</option>
                            <option value="model">{{ __('Model') }}</option>
                            <option value="warna">{{ __('Warna') }}</option>
                            <option value="mcs">{{ __('MCS') }}</option>
                            <option value="eval">{{ __('Hasil') }}</option>
                            <option value="emp_id">{{ __('Nomor karyawan') }}</option>
                        </x-select>                        
                    </div>
                    <div>
                        <x-text-input wire:model.live="fquery" class="mt-4" type="search"
                            placeholder="{{ __('Kata kunci') }}" name="fquery" />
                    </div>              
                </div>
                @if ($view == 'by-mcs')
                    <div wire:key="by-mcs-panel">
                        <div class="m-3">
                            <div class="py-4">
                                <x-secondary-button type="button" x-on:click="$dispatch('by-mcs-update', { start_at: '{{ $start_at }}', end_at: '{{ $end_at }}' })">{{ __('Perbarui') }}</x-secondary-button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @switch($view)
            @case('tests')
                <livewire:insight.rdc.summary.tests :$start_at :$end_at :$fquery :$ftype />
            @break

            @case('by-mcs')
                <livewire:insight.rdc.summary.by-mcs :$start_at :$end_at />
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

