<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\InsRdcTest;
use App\InsRdc;

new #[Layout('layouts.app')] 
class extends Component {

    #[Reactive]
    public $start_at;
    
    #[Reactive]
    public $end_at;

    public $tests;

    #[On('by-mcs-update')]
    public function update($start_at, $end_at)
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $this->tests = InsRdcTest::whereBetween('updated_at', [$start, $end])->get();

        // each model contains tc10 and tc90 which is numeric.
        $this->js(
                "
                let options = " .
                    json_encode(InsRdc::getChartOptions($this->tests, 100)) .
                    ";

                // Render chart
                const chartContainer = \$wire.\$el.querySelector('#chart-container');
                chartContainer.innerHTML = '<div id=\"chart\"></div>';
                let chart = new ApexCharts(chartContainer.querySelector('#chart'), options);
                chart.render();
            ",
        );
    }
};

?>

<div class="overflow-auto w-full">
    <div>
        <div class="flex justify-between items-center mb-6 px-5 py-1">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">
                {{ __('Tren Hasil Uji') }}</h1>
            <div class="flex gap-x-2 items-center">
                <x-secondary-button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')"><i class="fa fa-fw fa-question"></i></x-secondary-button>
            </div>
        </div>
        <div wire:key="modals"> 
            <x-modal name="raw-stats-info">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Statistik hasil uji') }}
                    </h2>
                    <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Belum ada informasi statistik yang tersedia.') }}
                    </p>
                    <div class="mt-6 flex justify-end">
                        <x-primary-button type="button" x-on:click="$dispatch('close')">
                            {{ __('Paham') }}
                        </x-primary-button>
                    </div>
                </div>
            </x-modal>  
            <x-modal name="test-show">
                <livewire:insight.rdc.summary.test-show />
            </x-modal>
        </div>
        @if(!$tests)
        {{ $visible = false }}  
            <div wire:key="no-range" class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="fa fa-calendar relative"><i
                            class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                </div>
                <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Tentukan rentang tanggal') }}
                </div>
            </div>
        @elseif (!$tests->count())
        {{ $visible = false }}  
            <div wire:key="no-match" class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="fa fa-ghost"></i>
                </div>
                <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
                </div>
            </div>
        @else
        <div class="hidden">{{ $visible = true }}</div>
        @endif
        <div @if(!$visible) class="hidden" @endif>
            <div wire:key="tests-chart" class="h-96 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden my-8"
                id="chart-container" wire:key="chart-container" wire:ignore>
            </div>  
        </div> 
    </div>
</div>
