<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Carbon\Carbon;
use App\Models\InsRtcMetric;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Response;

new #[Layout('layouts.app')] class extends Component {
    #[Url]
    public $view = 'clumps';

    #[Url]
    public $start_at;

    #[Url]
    public $end_at;

    #[Url]
    public $fline;

    #[Url]
    public $sline;
    public $olines = [];

    public $dateViews = ['raw', 'daily', 'clumps'];
    public $rangeViews = ['raw'];
    public $filterViews = ['raw', 'clumps'];

    public $dataIntegrity = 0;
    public $dataAccuracy = 0;
    public $dayCount = 0;

    public $is_line;
    public $is_date;
    public $is_range;
    public $is_filter;

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setToday();
        }
        
        $this->olines = InsRtcMetric::join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')
            ->join('ins_rtc_devices', 'ins_rtc_devices.id', '=', 'ins_rtc_clumps.ins_rtc_device_id')
            ->select('ins_rtc_devices.line')
            ->distinct()
            ->orderBy('ins_rtc_devices.line')
            ->get()
            ->pluck('line')
            ->toArray();
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
        $this->reset('fline');
    }

    public function download()
    {
        switch ($this->view) {
            case 'raw':
                $this->redirectRoute('download.ins-rtc-metrics', ['start_at' => $this->start_at, 'end_at' => $this->end_at]);
                $this->js('$dispatch("close")');
                $this->js('notyfSuccess("' . __('Pengunduhan dimulai...') . '")');
                break;
            case 'clumps':
                $this->redirectRoute('download.ins-rtc-clumps', ['start_at' => $this->start_at, 'end_at' => $this->end_at]);
                $this->js('$dispatch("close")');
                $this->js('notyfSuccess("' . __('Pengunduhan dimulai...') . '")');
                break;
        }
    }
};

?>

<x-slot name="title">{{ __('Rubber Thickness Control') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-rtc></x-nav-insights-rtc>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 grid gap-1">
    <div class="flex flex-col gap-x-2 md:gap-x-4 sm:flex-row min-w-0">
        <div>
            <div class="w-full sm:w-44 md:w-64 px-3 sm:px-0 mb-5">
                <div class="btn-group h-10 w-full">
                    <x-radio-button wire:model.live="view" grow value="daily" name="view" id="view-daily">
                        <div class="text-center my-auto">
                            <i class="fa fa-fw fa-calendar-day text-center m-auto"></i>
                        </div>
                    </x-radio-button>
                    <x-radio-button wire:model.live="view" grow value="clumps" name="view" id="view-clumps">
                        <div class="text-center my-auto">
                            <i class="fa fa-fw fa-toilet-paper text-center m-auto"></i>
                        </div>
                    </x-radio-button>
                    <x-radio-button wire:model.live="view" grow value="raw" name="view" id="view-raw">
                        <div class="text-center my-auto">
                            <i class="fa fa-fw fa-table text-center m-auto"></i>
                        </div>
                    </x-radio-button>
                </div>
                <div
                    class="mt-4 bg-white dark:bg-neutral-800 shadow rounded-lg py-5 px-4 {{ $is_line ? '' : 'hidden' }}">
                    <div class="flex items-start justify-between">
                        <div><i class="fa fa-ruler-horizontal mr-3"></i>{{ __('Line') }}</div>
                    </div>
                    <div class="mt-5">
                        <x-select wire:model.live="sline">
                            <option value=""></option>
                            @foreach ($olines as $oline)
                                <option value="{{ $oline }}">{{ $oline }}</option>
                            @endforeach
                        </x-select>
                    </div>
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
                        <x-text-input wire:model.live="start_at" id="inv-date-start" type="date"></x-text-input>
                        <x-text-input wire:model.live="end_at"  id="inv-date-end" type="date"
                            class="mt-3 mb-1 {{ $is_range ? '' : 'hidden' }}"></x-text-input>
                    </div>
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
                                    <x-dropdown-link href="#" wire:click.prevent="resetFilter">
                                        {{ __('Kosongkan filter') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>
                    <div>
                        <x-text-input wire:model.live="fline" class="mt-4" type="search"
                            placeholder="{{ __('Line') }}" name="fline" />
                    </div>
                </div>
                @if ($view == 'raw' || $view == 'clumps')
                    <div wire:key="raw-panel">
                        <div class="m-3">
                            @can('download', InsRtcMetric::class)
                                <div class="py-4">
                                    <x-text-button type="button" wire:click="download" class="text-sm"><i
                                        class="fa fa-fw mr-2 fa-download"></i>{{ __('Unduh CSV') }}</x-text-button>
                                </div>
                            @endcan
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @switch($view)
            @case('daily')
                <livewire:insight.rtc.daily :$start_at :$fline />
            @break

            @case('clumps')
                <livewire:insight.rtc.clumps :$start_at :$fline />
            @break

            @case('raw')
                <livewire:insight.rtc.raw :$start_at :$end_at :$fline />
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
