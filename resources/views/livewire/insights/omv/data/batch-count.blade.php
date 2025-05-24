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

    // public int $batch_total = 0;
    // public float $duration_avg = 0;
    // public float $line_avg = 0;

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisWeek();
        }
    }

    #[On('update')]
    public function update()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $metrics = InsOmvMetric::query()
            ->when($this->line, function (Builder $query) {
                $query->where('line', $this->line);
            })
            ->when($this->team, function (Builder $query) {
                $query->where('team', $this->team);
            })
            ->whereBetween('start_at', [$start, $end]);

        $dataByTeam = $metrics->get()->groupBy('team')
            ->map(function ($teamMetrics) {
                return [
                    'too_soon'          => $teamMetrics->where('eval', 'too_soon')->count(),
                    'on_time'           => $teamMetrics->where('eval', 'on_time')->count(), 
                    'on_time_manual'    => $teamMetrics->where('eval', 'on_time_manual')->count(),
                    'too_late'          => $teamMetrics->where('eval', 'too_late')->count(),
                ];
            })
            ->sortBy(function ($value, $key) {
                return $key;
            });

        $this->js(
            "
            let options = " .
                json_encode(InsOmv::getBatchEvalChartOptions($dataByTeam, 'team')) .
                ";
                
            // Fix the formatters
            options.xaxis.labels.formatter = function(val) { 
                return val; 
            };

            options.plotOptions.bar.dataLabels.total.formatter = function(val) {
                return val.toFixed(0);
            }

            options.dataLabels.formatter = function(val) {
                return val.toFixed(1) + '%';            
            };

            options.tooltip.y.formatter = function(val) {
                return val + ' " . __('batch') . "';       
            };

            // Render chart
            const chartContainer = \$wire.\$el.querySelector('#omv-summary-batch-count-by-team-chart-container');
            chartContainer.innerHTML = '<div class=\"chart\"></div>';
            let chart = new ApexCharts(chartContainer.querySelector('.chart'), options);
            chart.render();
            ",
        );

        $dataByIdentity = $metrics->get()->groupBy('team')
            ->map(function ($teamMetrics) {
                return [
                    'with_code'     => $teamMetrics->whereNotNull('ins_rubber_batch_id')->count(),
                    'without_code'  => $teamMetrics->whereNull('ins_rubber_batch_id')->count(),
                ];
            })
            ->sortBy(function ($value, $key) {
                return $key;
            });

        $this->js(
            "
            let options = " .
                json_encode(InsOmv::getBatchIdentityChartOptions($dataByIdentity, 'team')) .
                ";
                
            // Fix the formatters
            options.xaxis.labels.formatter = function(val) { 
                return val; 
            };

            options.plotOptions.bar.dataLabels.total.formatter = function(val) {
                return val.toFixed(0);
            }

            options.dataLabels.formatter = function(val) {
                return val.toFixed(1) + '%';            
            };

            options.tooltip.y.formatter = function(val) {
                return val + ' " . __('batch') . "';       
            };

            // Render chart
            const chartContainer = \$wire.\$el.querySelector('#omv-summary-batch-count-by-identity-chart-container');
            chartContainer.innerHTML = '<div class=\"chart\"></div>';
            let chart = new ApexCharts(chartContainer.querySelector('.chart'), options);
            chart.render();
            ",
        );

        $dataByLine = $metrics->get()->groupBy('line')
            ->map(function ($lineMetrics) {
                return [
                    'too_soon'          => $lineMetrics->where('eval', 'too_soon')->count(),
                    'on_time'           => $lineMetrics->where('eval', 'on_time')->count(), 
                    'on_time_manual'    => $lineMetrics->where('eval', 'on_time_manual')->count(),
                    'too_late'          => $lineMetrics->where('eval', 'too_late')->count(),
                ];
            })
            ->sortByDesc(function ($value, $key) {
                return $key;
            });

        $this->js(
            "
            let options = " .
                json_encode(InsOmv::getBatchEvalChartOptions($dataByLine, 'line')) .
                ";
                
            // Fix the formatters
            options.xaxis.labels.formatter = function(val) { 
                return val; 
            };
            
            options.plotOptions.bar.dataLabels.total.formatter = function(val) {
                return val;
            };

            options.dataLabels.formatter = function(val) {
                return val;             
            };

            options.tooltip.y.formatter = function(val) {
                return val + ' " . __('batch') . "';       
            };

            // Render chart
            const chartContainer = \$wire.\$el.querySelector('#omv-summary-batch-count-by-line-chart-container');
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
                                <x-text-button class="uppercase ml-3">{{ __('Rentang') }}<i class="icon-chevron-down ms-1"></i></x-text-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setToday">
                                    {{ __('Hari ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                    {{ __('Kemarin') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                    {{ __('Minggu ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                    {{ __('Minggu lalu') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                    {{ __('Bulan ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                    {{ __('Bulan lalu') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at"  id="cal-date-end" type="date"></x-text-input>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex gap-3">
                <div class="w-full lg:w-28">
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
                <div class="w-full lg:w-28">
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
                        <x-spinner class="sm mono"></x-spinner>
                    </div>
                    <div>
                        {{ __('Memuat...') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div wire:key="modals"> 

    </div>
    <div class="hidden sm:grid grid-cols-2 mb-2">
        <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
            {{ __('Berdasarkan tim') }}</h1>
        <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
            {{ __('Berdasarkan line') }}</h1>
    </div>
    <div wire:key="omv-summary-batch-count" class="grid grid-cols-1 grid-rows-3 sm:grid-cols-2 sm:grid-rows-2 gap-3 h-[96rem] sm:h-[32rem]">
        <div wire:key="omv-summary-batch-count-by-team-chart" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 sm:p-6 overflow-hidden"
            id="omv-summary-batch-count-by-team-chart-container" wire:key="omv-summary-batch-count-by-team-chart-container" wire:ignore> 
        </div>
        <div wire:key="omv-summary-batch-count-by-line-chart" class="row-span-1 sm:row-span-2 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 sm:p-6 overflow-hidden"
            id="omv-summary-batch-count-by-line-chart-container" wire:key="omv-summary-batch-count-by-line-chart-container" wire:ignore>
        </div> 
        <div wire:key="omv-summary-batch-count-by-identity-chart" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 sm:p-6 overflow-hidden"
            id="omv-summary-batch-count-by-identity-chart-container" wire:key="omv-summary-batch-count-by-identity-chart-container" wire:ignore> 
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript
