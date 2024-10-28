<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsStc;
use App\Models\InsStcDLog;
use App\Models\InsStcDSum;
use App\Models\InsStcMachine;
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

    public $lines;
    
    const MAX_SERIES = 500; // Limit to prevent overcrowding

    // #[Url]
    // public $team;

    // public int $batch_total = 0;
    // public float $duration_avg = 0;
    // public float $line_avg = 0;

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisMonth();
        }

        $this->lines = InsStcMachine::orderBy('line')->get()->pluck('line')->toArray();
    }

    protected function getData()
    {
        // Start with sums query
        $sumsQuery = InsStcDSum::query()
            ->whereBetween('start_time', [
                Carbon::parse($this->start_at)->startOfDay(),
                Carbon::parse($this->end_at)->endOfDay()
            ])
            ->when($this->line, function($query) {
                return $query->where('line', $this->line);
            })
            ->latest('start_time')
            ->limit(self::MAX_SERIES)
            ->get();

        // Get all relevant logs
        $logsData = [];
        foreach ($sumsQuery as $sum) {
            $logs = InsStcDLog::where('ins_stc_d_sum_id', $sum->id)
                ->orderBy('taken_at')
                ->get();

            if ($logs->isEmpty()) continue;

            // Get the first timestamp as reference point
            $firstTimestamp = $logs->first()->taken_at;
            
            // Transform the data with relative time
            $seriesData = $logs->map(function($log) use ($firstTimestamp) {
                $relativeMinutes = Carbon::parse($log->taken_at)->diffInSeconds(Carbon::parse($firstTimestamp)) / 60;
                return [
                    'x' => $relativeMinutes * 60 * 1000, // Convert to milliseconds for ApexCharts
                    'y' => $log->temp,
                    'realTime' => $log->taken_at // Keep real timestamp for tooltip
                ];
            })->toArray();

            $logsData[] = [
                'name' => "Batch " . $sum->id . " (" . Carbon::parse($sum->start_time)->format('d/m H:i') . ")",
                'data' => $seriesData
            ];
        }

        return $logsData;
    }

    #[On('update')]
    public function update()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $data = $this->getData();

        $this->js(
            "
            let options = " .
                json_encode(InsStc::getDLogsChartOptions($data)) .
                ";

            // Render chart
            const chartContainer = \$wire.\$el.querySelector('#stc-summary-d-logs-sum-chart-container');
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
                    <x-text-input wire:model.live="start_at" id="inv-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at"  id="inv-date-end" type="date"></x-text-input>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex gap-3">
                <div class="w-full lg:w-32">
                    <label for="d_logs-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="d_logs-line" wire:model.live="line" type="number" list="d_logs-lines" step="1" />
                    <datalist id="d_logs-lines">

                    </datalist>
                </div>
                {{-- <div class="w-full lg:w-32">
                    <label for="d_logs-team"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                    <x-text-input id="d_logs-team" wire:model.live="team" type="text" list="d_logs-teams" />
                    <datalist id="d_logs-teams">
                        <option value="A"></option>
                        <option value="B"></option>
                        <option value="C"></option>
                    </datalist>
                </div> --}}
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                    <div class="relative w-3">
                        <x-spinner class="sm white"></x-spinner>
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
    <div wire:key="stc-summary-d-logs-sum" class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-x-3">
        <div>
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                {{ __('Ikhtisar') }}</h1>
            <div class="flex flex-col gap-y-3 pb-6">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Statistik') . 'A' }}</label>
                    <div class="flex items-end gap-x-1">
                        <div class="text-2xl">{{ 0 }}</div>
                        <div>{{ 'Unit' }}</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Statistik') . 'B' }}</label>
                    <div class="flex items-end gap-x-1">
                        <div class="text-2xl">{{ 0 }}</div>
                        <div>{{ 'Unit' }}</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Statistik') . 'C' }}</label>
                    <div class="flex items-end gap-x-1">
                        <div class="text-2xl">{{ 0 }}</div>
                        <div>{{ 'Unit' }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="sm:col-span-2 lg:col-span-3">
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                {{ __('Waktu jalan') }}</h1>
            <div wire:key="stc-summary-d-logs-sum-chart" class="h-[32rem] bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 sm:p-6 overflow-hidden"
                id="stc-summary-d-logs-sum-chart-container" wire:key="stc-summary-d-logs-sum-chart-container" wire:ignore>
            </div>  
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript
