<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Carbon\Carbon;
use League\Csv\Writer;
use App\Models\InsRtcMetric;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Response;

new 
#[Layout('layouts.app')]
class extends Component 
{

    #[Url]
    public $view = 'summary';

    #[Url]
    public $misc = 'slideshows';

    #[Url]
    public $start_at;

    #[Url]
    public $end_at;

    #[Url]
    public $fline;

    #[Url]
    public $sline;
    public $olines = [];

    public $lineViews   = ['line-single'];
    public $dateViews   = ['raw', 'line-single'];
    public $rangeViews  = ['raw'];
    public $filterViews = ['raw', 'summary'];
    
    public $dataIntegrity = 0;
    public $dataAccuracy = 0;
    public $dayCount = 0;

    public $is_line;
    public $is_date;
    public $is_range;
    public $is_filter;

    public function mount()
    {
        $this->setToday();
        $this->olines = InsRtcMetric::join('ins_rtc_devices', 'ins_rtc_devices.id', '=', 'ins_rtc_metrics.ins_rtc_device_id')
            ->select('ins_rtc_devices.line')
            ->distinct()
            ->orderBy('ins_rtc_devices.line')
            ->get()
            ->pluck('line')
            ->toArray();
    }

    public function with(): array
    {
        $this->is_date      = in_array($this->view, $this->dateViews);
        $this->is_range     = in_array($this->view, $this->rangeViews);
        $this->is_line      = in_array($this->view, $this->lineViews);
        $this->is_filter    = in_array($this->view, $this->filterViews);

        return [
            'start_at'  => $this->start_at,
            'end_at'    => $this->end_at,
            'fline'     => $this->fline
        ];
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
        if ($this->view == 'raw')
        {
            $start  = Carbon::parse($this->start_at);
            $end    = Carbon::parse($this->end_at)->addDay();
    
            $metrics = InsRtcMetric::whereBetween('dt_client', [$start, $end])->orderBy('dt_client', 'DESC');
    
            $items = $metrics->get();
            
            // Create CSV file using league/csv
            $csv = Writer::createFromString('');
            $csv->insertOne([
                __('Waktu alat'), __('Line'), __('Tebal kiri'), __('Tebal kanan'),
            ]); // Add headers

            foreach ($items as $item) {
                $csv->insertOne(
                    [
                        $item->dt_client,
                        $item->line,
                        $item->thick_act_left,
                        $item->thick_act_right,
                    ]
                ); // Add data rows
            }
    
            // Generate CSV file and return as a download
            $fileName = __('Wawasan_RTC') . '_' . date('Y-m-d_Hs') . '.csv';
            $this->js('window.dispatchEvent(escKey)'); 
            $this->js('notyf.success("'.__('Pengunduhan dimulai...').'")'); 
    
            return Response::stream(
                function () use ($csv) {
                    echo $csv->toString();
                },
                200,
                [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                ]
            );
        }
    }

}; 

?>

<div class="flex flex-col gap-x-2 md:gap-x-4 sm:flex-row">
    <div>
        <div class="w-full sm:w-44 md:w-64 px-3 sm:px-0 mb-5">
            <div class="btn-group h-10 w-full">
                <x-radio-button wire:model.live="view" grow value="summary" name="view" id="view-summary">
                    <div class="text-center my-auto">
                        <i class="fa fa-fw fa-display text-center m-auto"></i>
                    </div>
                </x-radio-button>
                <x-radio-button wire:model.live="view" grow value="raw" name="view" id="view-raw">
                    <div class="text-center my-auto">
                        <i class="fa fa-fw fa-table text-center m-auto"></i>
                    </div>
                </x-radio-button>
                <x-radio-button wire:model.live="view" grow value="misc" name="view" id="view-misc">
                    <div class="text-center my-auto">
                        <i class="fa fa-fw fa-ellipsis-h text-center m-auto"></i>
                    </div>
                </x-radio-button>
            </div>
            <div class="mt-4 bg-white dark:bg-neutral-800 shadow rounded-lg py-5 px-4 {{ $is_line ? '' : 'hidden' }}">
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
            <div class="mt-4 bg-white dark:bg-neutral-800 shadow rounded-lg py-5 px-4 {{ $is_date ? '' : 'hidden' }}">
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
            @if ($view == 'raw')
                <div wire:key="raw-panel">
                    <div class="m-3">
                        <div class="py-4">
                            <x-text-button type="button" wire:click="download" class="text-sm"><i
                                    class="fa fa-fw mr-2 fa-download"></i>{{ __('Unduh CSV') }}</x-text-button>
                        </div>
                    </div>
                </div>
            @endif
            @if ($view == 'misc')
                <div wire:key="misc-panel">
                    <div class="btn-group-v w-full mt-4">
                        <x-radio-button-v wire:model.live="misc" grow value="slideshows" name="slideshows" id="misc-slideshows">
                            <div class="my-auto">
                                <i class="fa fa-fw fa-images"></i>
                                {{ __('Peragaan') }}
                            </div>
                        </x-radio-button-v>
                        <x-radio-button-v wire:model.live="misc" grow value="recipes" name="recipes" id="misc-recipes">
                            <div class="my-auto">
                                <i class="fa fa-fw fa-book"></i>
                                {{ __('Resep') }}
                            </div>
                        </x-radio-button-v>
                        <x-radio-button-v wire:model.live="misc" grow value="devices" name="devices" id="misc-devices">
                            <div class="my-auto">
                                <i class="fa fa-fw fa-microchip"></i>
                                {{ __('Perangkat') }}
                            </div>
                        </x-radio-button-v>
                        <x-radio-button-v wire:model.live="misc" grow value="auth" name="auth" id="misc-auth">
                            <div class="my-auto">
                                <i class="fa fa-fw fa-microchip"></i>
                                {{ __('Wewenang') }}
                            </div>
                        </x-radio-button-v>
                    </div>
                    {{-- <div class="m-3">
                        <div
                            class="w-full ">
                            <button type="button"
                                class="relative inline-flex items-center w-full px-4 py-2 text-sm font-medium border-b border-gray-200 rounded-t-lg hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-white dark:focus:ring-gray-500 dark:focus:text-white">
                                <i class="fa fa-fw mr-3 fa-images"></i>
                                {{ __('Pagelaran') }}
                            </button>
                            <button type="button"
                                class="relative inline-flex items-center w-full px-4 py-2 text-sm font-medium border-b border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-white dark:focus:ring-gray-500 dark:focus:text-white">
                                <i class="fa fa-fw mr-3 fa-book"></i>
                                {{ __('Resep') }}
                            </button>
                            <button type="button"
                                class="relative inline-flex items-center w-full px-4 py-2 text-sm font-medium border-b border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-white dark:focus:ring-gray-500 dark:focus:text-white">
                                <i class="fa fa-fw mr-3 fa-microchip"></i>
                                {{ __('Perangkat') }}
                            </button>
                        </div>
                    </div> --}}
                </div>
            @endif
        </div>
    </div>
    @switch($view)
        @case('summary')
            <livewire:insight\rtc\summary :$fline />
        @break

        @case('raw')
            <livewire:ins-rtc-raw :$start_at :$end_at :$fline />
        @break

        @case('slideshows')
            <livewire:ins-rtc-slideshows />
        @break

        @case('recipes')
            <livewire:ins-rtc-recipes />
        @break

        @case('devices')
            <livewire:ins-rtc-devices />
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


