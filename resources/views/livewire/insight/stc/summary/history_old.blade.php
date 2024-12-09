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

    private int $limit = 100;

    public array $lines = [];

    #[Url]
    public $line;

    #[Url]
    public string $position = '';
    
    #[Url]
    public string $mode = 'recents';

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public int $count;

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisWeek();
        }

        $this->lines = InsStcMachine::orderBy('line')->get()->pluck('line')->toArray();
    }

    private function getDLogsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsStcDLog::join('ins_stc_d_sums', 'ins_stc_d_logs.ins_stc_d_sum_id', '=', 'ins_stc_d_sums.id')
            ->join('ins_stc_machines', 'ins_stc_d_sums.ins_stc_machine_id', '=', 'ins_stc_machines.id')
            ->join('users as user1', 'ins_stc_d_sums.user_1_id', '=', 'user1.id')
            ->leftjoin('users as user2', 'ins_stc_d_sums.user_2_id', '=', 'user2.id')
            ->select(
                'ins_stc_d_logs.*',
                'ins_stc_d_sums.*',
                'ins_stc_d_sums.id as d_sum_id',
                'ins_stc_d_sums.created_at as d_sum_created_at',
                'ins_stc_machines.line as machine_line',
                'user1.emp_id as user1_emp_id',
                'user1.name as user1_name',
                'user2.emp_id as user2_emp_id',
                'user2.name as user2_name',
                'ins_stc_d_logs.taken_at as d_log_taken_at',
                'ins_stc_d_logs.temp as d_log_temp',
            )
            ->whereBetween('ins_stc_d_sums.created_at', [$start, $end]);

            if ($this->line)
            {
                $query->where('ins_stc_machines.line', $this->line);
            }

            if ($this->position)
            {
                $query->where('ins_stc_d_sums.position', $this->position);
            }

        return $query->limit($this->limit)->orderBy('ins_stc_d_logs.taken_at', 'DESC');
    }

    public function getData()
    {
        $logs = $this->getDlogsQuery()->get();

        if ($logs->isEmpty()) {
            return ['data' => []];
        }

        // Group logs by their d_sum_id to separate different measurement cycles
        $groupedLogs = $logs->groupBy('d_sum_id');

        $seriesData = $groupedLogs->map(function($cycleGroup) {
            // Sort logs within each cycle by taken_at
            $sortedCycleLogs = $cycleGroup->sortBy('taken_at');

            // Get the first timestamp of this cycle as the reference point
            $firstTimestamp = $sortedCycleLogs->first()->taken_at;

            return $sortedCycleLogs->map(function($log) use ($firstTimestamp) {
                $relativeSeconds = Carbon::parse($log->taken_at)
                    ->diffInSeconds(Carbon::parse($firstTimestamp));

                return [
                    'x' => $relativeSeconds * 1000, // Convert to milliseconds, starting from 0
                    'y' => $log->temp,
                    'realTime' => $log->taken_at
                ];
            })->values()->toArray();
        })->values()->toArray();

        return ['data' => $seriesData];
    }

    #[On('update')]
    public function update()
    {
        $data = $this->getData();

        // dd($data);

        $this->js(
            "
            let options = " .
                json_encode(InsStc::getHistoryChartOptions($data)) .
                ";

            // Render chart
            const chartContainer = \$wire.\$el.querySelector('#stc-summary-history-chart-container');
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
                <label for="device-line"
                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                <x-select id="device-line" wire:model.live="line">
                    <option value=""></option>
                    @foreach($lines as $line)
                    <option value="{{ $line }}">{{ $line }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <label for="history-position"
                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                <x-select id="history-position" wire:model.live="position">
                    <option value=""></option>
                    <option value="upper">{{ __('Atas') }}</option>
                    <option value="lower">{{ __('Bawah') }}</option>
                </x-select>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div>
                <label for="history-mode"
                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Modus') }}</label>
                <x-select id="history-mode" wire:model.live="mode">
                    <option value="recents">{{ __('Pengukuran terakhir') }}</option>
                    <option value="range">{{ __('Rentang tanggal') }}</option>
                </x-select>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            @switch($mode)
                @case('recents')
                    <div>
                        <label for="history-count"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Jumlah') }}</label>
                        <x-select id="history-count" wire:model.live="count">
                            <option value="10">10</option>
                            <option value="10">50</option>
                            <option value="100">100</option>
                        </x-select>
                    </div>
                    @break
                @case('range')
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
                            <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                            <x-text-input wire:model.live="end_at"  id="cal-date-end" type="date"></x-text-input>
                        </div>
                    </div>
                    @break
                    
            @endswitch
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
    <div wire:key="stc-summary-history" class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-x-3">
        <div>
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                {{ __('Ikhtisar') }}</h1>
            <div class="flex flex-col gap-y-3 pb-6">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Jumlah data') }}</label>
                    <div class="flex items-end gap-x-1">
                        <div class="text-2xl">{{ 0 }}</div>
                        <div>{{ 'Unit' }}</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Pembacaan alat terakhir') }}</label>
                    <div class="flex items-end gap-x-1">
                        <div class="text-2xl">{{ 0 }}</div>
                        <div>{{ 'Unit' }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="sm:col-span-2 lg:col-span-3">
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                {{ __('Grafik') }}</h1>
            <div wire:key="stc-summary-history-chart" class="h-[32rem] bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 sm:p-6 overflow-hidden"
                id="stc-summary-history-chart-container" wire:key="stc-summary-history-chart-container" wire:ignore>
            </div>  
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript
