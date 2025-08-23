<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsStc;
use App\Models\InsStcDSum;
use App\Models\InsStcDLog;
use App\Models\InsStcMachine;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use HasDateRangeFilter;

    private int $limit = 50;

    public array $lines = [];

    #[Url]
    public $line;

    #[Url]
    public string $position = "";

    #[Url]
    public string $selection_mode = "recents";
    public string $present_mode = "standard_zone";

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public int $count = 5;

    public int $d_sum_total = 0;
    public string $d_sum_latest_date = "";

    public function mount()
    {
        if (! $this->start_at || ! $this->end_at) {
            $this->setThisWeek();
        }

        $this->lines = InsStcMachine::orderBy("line")
            ->get()
            ->pluck("line")
            ->toArray();
    }

    #[On("update")]
    public function update()
    {
        $query = InsStcDLog::query();
        $dSumQuery = InsStcDSum::latest("created_at");

        if ($this->selection_mode === "recents") {
            $dSumQuery->limit($this->count);
        } elseif ($this->selection_mode === "range") {
            $start = Carbon::parse($this->start_at);
            $end = Carbon::parse($this->end_at)->endOfDay();

            $dSumQuery->limit($this->limit)->whereBetween("created_at", [$start, $end]);
        }

        // Apply additional filters for line and position
        if ($this->line) {
            $dSumQuery->whereHas("ins_stc_machine", function ($query) {
                $query->where("line", $this->line);
            });
        }

        if ($this->position) {
            $dSumQuery->where("position", $this->position);
        }

        // Get the filtered d_sum IDs
        $dSumIds = $dSumQuery->pluck("id");

        $this->d_sum_total = $dSumQuery->get()->count();
        $dSumLatest = $dSumQuery->latest()->first();
        $this->d_sum_latest_date = $dSumLatest ? $dSumLatest->created_at : "";

        // Now filter d_log using these d_sum IDs
        $d_sums = InsStcDLog::whereIn("ins_stc_d_sum_id", $dSumIds)
            ->get()
            ->groupBy("ins_stc_d_sum_id");

        switch ($this->present_mode) {
            case "standard_zone":
                $chartOptions = InsStc::getStandardZoneChartOptions($d_sums, 100, 100);
                break;

            case "boxplot":
                $chartOptions = InsStc::getBoxplotChartOptions($d_sums, 100, 100);
                break;
        }

        // Rest of your existing code remains the same
        $this->js(
            "
            let recentsOptions = " .
                json_encode($chartOptions) .
                ";

            // Render recents chart
            const recentsChartContainer = \$wire.\$el.querySelector('#stc-data-history-chart-container');
            recentsChartContainer.innerHTML = '<div id=\"stc-data-history-chart\"></div>';
            let recentsChart = new ApexCharts(recentsChartContainer.querySelector('#stc-data-history-chart'), recentsOptions);
            recentsChart.render();
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
                <label for="device-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
                <x-select id="device-line" wire:model.live="line">
                    <option value=""></option>
                    @foreach ($lines as $line)
                        <option value="{{ $line }}">{{ $line }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <label for="history-position" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Posisi") }}</label>
                <x-select id="history-position" wire:model.live="position">
                    <option value=""></option>
                    <option value="upper">{{ __("Atas") }}</option>
                    <option value="lower">{{ __("Bawah") }}</option>
                </x-select>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div>
                <label for="history-selection-mode" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Seleksi") }}</label>
                <x-select id="history-selection-mode" wire:model.live="selection_mode">
                    <option value="recents">{{ __("Data terakhir") }}</option>
                    <option value="range">{{ __("Rentang tanggal") }}</option>
                </x-select>
            </div>
            <div>
                <label for="history-selection-mode" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Presentasi") }}</label>
                <x-select id="history-selection-mode" wire:model.live="present_mode">
                    <option value="standard_zone">{{ __("Zona standar") }}</option>
                    <option value="boxplot">{{ __("Boxplot") }}</option>
                </x-select>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>

            @switch($selection_mode)
                @case("recents")
                    <div class="w-full lg:w-28">
                        <label for="history-count" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Batas") }}</label>
                        <x-text-input id="history-count" wire:model.live="count" type="number" step="1" list="history-counts" />
                        <datalist id="history-counts">
                            <option value="1"></option>
                            <option value="5"></option>
                            <option value="10"></option>
                            <option value="50"></option>
                        </datalist>
                    </div>

                    @break
                @case("range")
                    <div>
                        <div class="flex mb-2 text-xs text-neutral-500">
                            <div class="flex">
                                <x-dropdown align="left" width="48">
                                    <x-slot name="trigger">
                                        <x-text-button class="uppercase ml-3">
                                            {{ __("Rentang") }}
                                            <i class="icon-chevron-down ms-1"></i>
                                        </x-text-button>
                                    </x-slot>
                                    <x-slot name="content">
                                        <x-dropdown-link href="#" wire:click.prevent="setToday">
                                            {{ __("Hari ini") }}
                                        </x-dropdown-link>
                                        <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                            {{ __("Kemarin") }}
                                        </x-dropdown-link>
                                        <hr class="border-neutral-300 dark:border-neutral-600" />
                                        <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                            {{ __("Minggu ini") }}
                                        </x-dropdown-link>
                                        <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                            {{ __("Minggu lalu") }}
                                        </x-dropdown-link>
                                        <hr class="border-neutral-300 dark:border-neutral-600" />
                                        <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                            {{ __("Bulan ini") }}
                                        </x-dropdown-link>
                                        <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                            {{ __("Bulan lalu") }}
                                        </x-dropdown-link>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                            <x-text-input wire:model.live="end_at" id="cal-date-end" type="date"></x-text-input>
                        </div>
                    </div>

                    @break
            @endswitch
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                    <div class="relative w-3">
                        <x-spinner class="sm mono"></x-spinner>
                    </div>
                    <div>
                        {{ __("Memuat...") }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div wire:key="modals"></div>
    <div wire:key="stc-data-history" class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-x-3">
        <div>
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">{{ __("Ikhtisar") }}</h1>
            <div class="flex flex-col gap-y-3 pb-6">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __("Jumlah pengukuran") }}</label>
                    <div class="flex items-end gap-x-1">
                        <div class="text-2xl">{{ $d_sum_total }}</div>
                        <div>{{ __("data") }}</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __("Terakhir diukur") }}</label>
                    <div class="flex items-end gap-x-1">
                        <div class="text-2xl">{{ $d_sum_latest_date ? Carbon::parse($d_sum_latest_date)->diffForHumans() : __("Tidak ada") }}</div>
                        {{-- <div>{{ __('menit') .'/' . __('batch')}}</div> --}}
                    </div>
                </div>
                {{--
                    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                    <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Rerata waktu jalan') }}</label>
                    <div class="flex items-end gap-x-1">
                    <div class="text-2xl">{{ $line_avg }}</div>
                    <div>{{ __('jam') .'/' . __('line')}}</div>
                    </div>
                    </div>
                --}}
            </div>
        </div>
        <div class="sm:col-span-2 lg:col-span-3">
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">{{ __("Grafik") }}</h1>
            <div
                wire:key="stc-data-history-chart"
                class="h-96 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 sm:p-6 overflow-hidden"
                id="stc-data-history-chart-container"
                wire:key="stc-data-history-chart-container"
                wire:ignore
            ></div>
        </div>
    </div>
</div>

@script
    <script>
        $wire.$dispatch('update');
    </script>
@endscript
