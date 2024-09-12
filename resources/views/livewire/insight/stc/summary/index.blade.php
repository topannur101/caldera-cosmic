<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Response;
use App\Models\InsStcDSums;

new #[Layout('layouts.app')] 
class extends Component {
    #[Url]
    public $view = 'd-sums';

    #[Url]
    public $start_at;

    #[Url]
    public $end_at;

    #[Url]
    public $fquery;

    #[Url]
    public $ftype = 'any';

    public $dateViews = ['d-sums'];
    public $rangeViews = ['d-sums'];
    public $filterViews = ['d-sums'];

    public $is_date;
    public $is_range;
    public $is_filter;

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
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

    public function filterByMe()
    {
        if (Auth::user()) {
            $this->ftype = 'emp_id';
            $this->fquery = Auth::user()->emp_id;
        }
    }

    public function download()
    {
        $this->js('alert("' . __('Fitur dalam pengembangan') . '")');
    }
};

?>

<x-slot name="title">{{ __('IP Stabilization Control') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-stc></x-nav-insights-stc>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 grid gap-1">
    @vite(['resources/js/apexcharts.js'])
    <div class="flex flex-col gap-x-2 md:gap-x-4 sm:flex-row min-w-0">
        <div>
            <div class="w-full sm:w-44 md:w-64 px-3 sm:px-0 mb-5">
                {{-- <div class="btn-group h-10 w-full">
                    <x-radio-button wire:model.live="view" grow value="raw" name="view" id="view-raw">
                        <div class="text-center my-auto">
                            <i class="fa fa-fw fa-table text-center m-auto"></i>
                        </div>
                    </x-radio-button>
                </div> --}}
                <div
                    class="bg-white dark:bg-neutral-800 shadow rounded-lg py-5 px-4 {{ $is_date ? '' : 'hidden' }}">
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
                                        {{ __('Bulan kemarin') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>
                    <div class="mt-5">
                        <x-text-input wire:model.live="start_at" id="inv-date-start" type="date"></x-text-input>
                        <x-text-input wire:model.live="end_at" id="inv-date-end" type="date"
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
                            <option value="emp_id">{{ __('Nomor karyawan') }}</option>
                        </x-select>                        
                    </div>
                    <div>
                        <x-text-input wire:model.live="fquery" class="mt-4" type="search"
                            placeholder="{{ __('Kata kunci') }}" name="fquery" />
                    </div>              
                </div>
                @if ($view == 'd-sums')
                    <div wire:key="d-sums-panel">
                        <div class="m-3">
                            <div class="py-4">
                                <x-text-button type="button" wire:click="download" class="text-sm"><i
                                    class="fa fa-fw mr-2 fa-download"></i>{{ __('Unduh CSV') }}</x-text-button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @switch($view)
            @case('d-sums')
                <livewire:insight.stc.summary.d-sums :$start_at :$end_at :$fquery :$ftype />
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
    <!-- Hidden printable area -->
    <div id="print-container" class="aspect-[297/210] bg-white text-neutral-900 p-8 w-[1200px] cal-offscreen">
        <div class="flex flex-col gap-6 w-full h-full">
            <div class="grow-0">
                <div id="print-container-header">
                    <div class="flex gap-x-6 justify-between">            
                        <div class="flex flex-col">
                            <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi pengukuran') }}</dt>
                            <dd>
                                <table>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-xs">
                                            {{ __('Pengukuran ke') }}
                                        </td>
                                        <td class="px-1">:</td>
                                        <td>
                                            -
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-xs">
                                            {{ __('Pengukur 1') }}
                                        </td>
                                        <td class="px-1">:</td>
                                        <td>
                                            -
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-xs">
                                            {{ __('Pengukur 2') }}
                                        </td>
                                        <td class="px-1">:</td>
                                        <td>
                                            -
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-xs">
                                            {{ __('Kode alat ukur') }}
                                        </td>
                                        <td class="px-1">:</td>
                                        <td>
                                            -
                                        </td>
                                    </tr>
                                </table>
                            </dd>
                        </div>            
                        <div class="flex flex-col">
                            <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi mesin') }}</dt>
                            <dd>
                                <table>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-xs">
                                            {{ __('Line') }}
                                        </td>
                                        <td class="px-1">:</td>
                                        <td>
                                            -
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-xs">
                                            {{ __('Mesin') }}
                                        </td>
                                        <td class="px-1">:</td>
                                        <td>
                                            -
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-xs">
                                            {{ __('Posisi') }}
                                        </td>
                                        <td class="px-1">:</td>
                                        <td>
                                            -
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-xs">
                                            {{ __('Kecepatan') }}
                                        </td>
                                        <td class="px-1">:</td>
                                        <td>
                                            -
                                        </td>
                                    </tr>
                                </table>
                            </dd>
                        </div> 
                        <div class="flex flex-col">
                            <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Suhu diatur') }}</dt>
                            <dd>
                                <div class="grid grid-cols-8 text-center gap-x-6">
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona') }}</div>
                                        <div>-</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona') }}</div>
                                        <div>-</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona') }}</div>
                                        <div>-</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona') }}</div>
                                        <div>-</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona') }}</div>
                                        <div>-</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona') }}</div>
                                        <div>-</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona') }}</div>
                                        <div>-</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona') }}</div>
                                        <div>-</div>
                                    </div>
                                </div>
                            </dd>
                        </div>
                    </div>
                </div>
            </div>
            <div class="grow border border-neutral-500 rounded-lg overflow-hidden">
                <div id="print-chart-container" wire:key="print-chart-container" wire:ignore></div>
            </div>
            <div class="grow-0">
                <div id="print-container-footer">
                    <div class="flex justify-between p-4">
                        <div>
                            <div>{{ __('Zona 1') . ': 70-80 °C' }}</div>
                            <div>{{ __('Zona 2') . ': 60-70 °C' }}</div>
                            <div>{{ __('Zona 3') . ': 50-60 °C' }}</div>
                            <div>{{ __('Zona 4') . ': 40-50 °C' }}</div>
                        </div>
                        <div class="flex gap-x-3">
                            <div>
                                <div class="text-center font-bold">CE</div>
                                <div class="flex justify-center">
                                    <div class="w-8 h-8 my-4 bg-neutral-200 rounded-full overflow-hidden">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800  opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                    </div>
                                </div>
                                <hr class="border-neutral-300 w-48">
                                <div class="text-center">
                                    <div class="text-sm">-</div>
                                    <div class="text-xs">-</div>
                                </div>
                            </div>    
                            <div>
                                <div class="text-center font-bold">TL</div>
                                <div class="grow">
                                    <div class="w-8 h-8 my-4"></div>
                                </div>
                                <hr class="border-neutral-300 w-48">
                                <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal')}}</div>
                            </div> 
                            <div>
                                <div class="text-center font-bold">GL</div>
                                <div><div class="w-8 h-8 my-4"></div></div>
                                <hr class="border-neutral-300 w-48">
                                <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal')}}</div>
                            </div> 
                            <div>
                                <div class="text-center font-bold">VSM</div>
                                <div><div class="w-8 h-8 my-4"></div></div>
                                <hr class="border-neutral-300 w-48">
                                <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal')}}</div>
                            </div>             
                        </div>
                    </div>
                </div> 
            </div>
        </div>
    </div>
</div>
@script
<script>
    Livewire.on('printChart', () => {
        const hdEl = document.getElementById('print-container-header-dynamic');
        const hsEl = document.getElementById('print-container-header');
        const fdEl = document.getElementById('print-container-footer-dynamic');
        const fsEl = document.getElementById('print-container-footer');

        if (!hdEl || !hsEl || !fdEl || !fsEl) {
            notyfError("{{ __('Terjadi galat ketika mencoba mencetak. Periksa console') }}");
            console.error('One of the elements not found. All 4 elements exist in the DOM for printing.');
        } else {
            // Transfer content then print
            hsEl.innerHTML = hdEl.innerHTML;
            fsEl.innerHTML = fdEl.innerHTML;
            window.print();      
        }          
    });
</script>
@endscript
