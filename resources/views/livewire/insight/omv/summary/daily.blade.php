<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsOmv;
use App\Models\InsOmvMetric;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] 
class extends Component {

    use HasDateRangeFilter;

    #[Url]
    public $start_at;
    #[Url]
    public $end_at;
    #[Url]
    public $line;
    #[Url]
    public $team;

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setToday();
        }
    }

    #[On('update')]
    public function update()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $data = InsOmvMetric::query()
            ->when($this->line, function (Builder $query) {
                $query->where('line', $this->line);
            })
            ->when($this->team, function (Builder $query) {
                $query->where('team', $this->team);
            })
            ->whereBetween('start_at', [$start, $end])
            ->get()
            ->groupBy('line')
            ->map(function ($lineMetrics) {
                return [
                    'too_soon' => $lineMetrics->where('eval', 'too_soon')
                        ->sum(function ($metric) {
                            return round(Carbon::parse($metric->start_at)->diffInMinutes(Carbon::parse($metric->end_at)) / 60, 1);
                        }),
                    'on_time' => $lineMetrics->where('eval', 'on_time')
                        ->sum(function ($metric) {
                            return round(Carbon::parse($metric->start_at)->diffInMinutes(Carbon::parse($metric->end_at)) / 60, 1);
                        }),
                    'on_time_manual' => $lineMetrics->where('eval', 'on_time_manual')
                        ->sum(function ($metric) {
                            return round(Carbon::parse($metric->start_at)->diffInMinutes(Carbon::parse($metric->end_at)) / 60, 1);
                        }),
                    'too_late' => $lineMetrics->where('eval', 'too_late')
                        ->sum(function ($metric) {
                            return round(Carbon::parse($metric->start_at)->diffInMinutes(Carbon::parse($metric->end_at)) / 60, 1);
                        })
                ];
            })
            ->sortByDesc(function ($value, $key) {
                return $key;
            });

        $this->js(
            "
            let options = " .
                json_encode(InsOmv::getDailyChartOptions($data)) .
                ";
                
            // Fix the formatters
            options.xaxis.labels.formatter = function(val) { 
                return val.toFixed(0); 
            };
            
            options.plotOptions.bar.dataLabels.total.formatter = function(val) {
                return val.toFixed(1) + ' jam';
            };

            options.dataLabels.formatter = function(val) {
                return val.toFixed(1);             
            };

            options.tooltip.y.formatter = function(val) {
                return val.toFixed(1) + ' jam';             
            };

            // Render chart
            const chartContainer = \$wire.\$el.querySelector('#omv-summary-daily-chart-container');
            chartContainer.innerHTML = '<div class=\"chart\"></div>';
            let chart = new ApexCharts(chartContainer.querySelector('.chart'), options);
            chart.render();
            ",
        );
    }

    public function updated()
    {
        $this->update();
    }
};

?>

<div>
    <div class="p-0 sm:p-1 mb-6">
        <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div>
                <div class="flex mb-2 text-xs text-neutral-500">
                    <div class="flex">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <x-text-button class="uppercase ml-3">{{ __('Rentang') }}<i class="fa fa-fw fa-chevron-down ms-1"></i></x-text-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setToday">
                                    {{ __('Hari ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                    {{ __('Kemarin') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                    {{ __('Bulan ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                    {{ __('Bulan kemarin') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" id="inv-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at"  id="inv-date-end" type="date"></x-text-input>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex gap-3">
                <div class="w-full lg:w-32">
                    <label for="metrics-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="metrics-line" wire:model.live="line" type="number" list="metrics-lines" step="1" />
                    <datalist id="metrics-lines">
                        <option value="1"></option>
                        <option value="2"></option>
                        <option value="3"></option>
                        <option value="4"></option>
                        <option value="5"></option>
                        <option value="6"></option>
                        <option value="7"></option>
                        <option value="8"></option>
                        <option value="9"></option>
                    </datalist>
                </div>
                <div class="w-full lg:w-32">
                    <label for="metrics-team"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                    <x-text-input id="metrics-team" wire:model.live="team" type="text" list="metrics-teams" />
                    <datalist id="metrics-teams">
                        <option value="A"></option>
                        <option value="B"></option>
                        <option value="C"></option>
                    </datalist>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                    <div class="relative w-3">
                        <x-spinner class="sm"></x-spinner>
                    </div>
                    <div class="text-neutral-500">
                        {{ __('Memuat...') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div wire:key="modals"> 

    </div>
    <div wire:key="omv-summary-daily" class="grid grid-cols-4 gap-3">
        <div>
            <h2 class="text-xl px-6 mb-6">{{ __('Target') }}</h2>
            <div class="flex flex-col gap-y-3">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Produksi/hari') }}</label>
                    <div class="flex items-end gap-x-2">
                        <div class="text-2xl">000/000</div>
                        <div>{{ __('batch') }}</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Performa qty') }}</label>
                    <div class="flex items-end gap-x-2">
                        <div class="text-2xl">0</div>
                        <div>%</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-3">
            <h2 class="text-xl px-6 mb-6">{{ __('Waktu operasi mesin') }}</h2>
            <div wire:key="omv-summary-daily-chart" class="h-96 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden"
                id="omv-summary-daily-chart-container" wire:key="omv-summary-daily-chart-container" wire:ignore>
            </div>  
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript
