<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsStc;
use App\Models\InsStcDSum;
use App\Models\InsStcMachine;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

new class extends Component {
    use HasDateRangeFilter;

    #[Url]
    public $start_at;

    #[Url]
    public $end_at;

    #[Url]
    public $line;

    public array $machine_lines = [];

    public function mount()
    {
        if (! $this->start_at || ! $this->end_at) {
            $this->setThisWeek();
        }

        $machines = InsStcMachine::all();
        $this->machine_lines = $machines->pluck("line")->toArray();
    }

    #[On("update")]
    public function update()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $dSums = InsStcDSum::with(["ins_stc_machine"])
            ->when($this->line, function (Builder $query) {
                $query->whereHas("ins_stc_machine", function (Builder $query) {
                    $query->where("line", $this->line);
                });
            })
            ->whereBetween("created_at", [$start, $end])
            ->get()
            ->toArray();

        $this->js(
            "
            const options = " .
                json_encode(InsStc::getAdjustmentChartJsOptions($dSums)) .
                ";

            // Render chart
            const adjustmentChartContainer = \$wire.\$el.querySelector('#stc-data-adjustment-chart-container');
            adjustmentChartContainer.innerHTML = '';
            const adjustmentCanvas = document.createElement('canvas');
            adjustmentCanvas.id = 'adjustment-chart';
            adjustmentChartContainer.appendChild(adjustmentCanvas);
            new Chart(adjustmentCanvas, options);
        ",
        );

        $this->js(
            "
            const options = " .
                json_encode(InsStc::getIntegrityChartJsOptions($dSums)) .
                ";

            // Render chart
            const integrityChartContainer = \$wire.\$el.querySelector('#stc-data-integrity-chart-container');
            integrityChartContainer.innerHTML = '';
            const integrityCanvas = document.createElement('canvas');
            integrityCanvas.id = 'integrity-chart';
            integrityChartContainer.appendChild(integrityCanvas);
            new Chart(integrityCanvas, options);
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
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex gap-3">
                <div class="w-full lg:w-28">
                    <label for="machine-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
                    <x-text-input id="machine-line" wire:model.live="line" type="number" list="machine-lines" step="1" />
                    <datalist id="machine-lines">
                        @foreach ($machine_lines as $machine_line)
                            <option value="{{ $machine_line }}"></option>
                        @endforeach
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
                        {{ __("Memuat...") }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div wire:key="modals"></div>
    <div class="hidden sm:grid grid-cols-2 mb-2">
        <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">{{ __("Penyetelan") }}</h1>
        <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">{{ __("Integritas") }}</h1>
    </div>
    <div wire:key="stc-data-batch-count" class="grid grid-cols-1 sm:grid-cols-2 gap-3 h-[32rem]">
        <div
            wire:key="stc-data-adjustment-chart"
            class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 sm:p-6 overflow-hidden"
            id="stc-data-adjustment-chart-container"
            wire:key="stc-data-adjustment-chart-container"
            wire:ignore
        ></div>
        <div
            wire:key="stc-data-integrity-chart"
            class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 sm:p-6 overflow-hidden"
            id="stc-data-integrity-chart-container"
            wire:key="stc-data-integrity-chart-container"
            wire:ignore
        ></div>
    </div>
</div>

@script
    <script>
        $wire.$dispatch('update');
    </script>
@endscript
