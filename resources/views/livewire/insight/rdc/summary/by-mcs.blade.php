<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\InsRdcTest;
use App\InsRdc;

new #[Layout('layouts.app')] 
class extends Component {

    #[On('by-mcs-apply')]
    public function apply()
    {
        $this->js(
                "
                let options = " .
                    json_encode(InsRdc::getChartOptions($logs, $xzones, $yzones, $ymax, $ymin, 100)) .
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
        <div class="h-96 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden my-8"
            id="chart-container" wire:key="chart-container" wire:ignore>
        </div>        
    </div>
</div>
