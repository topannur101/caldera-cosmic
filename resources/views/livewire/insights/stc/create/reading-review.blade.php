<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\InsStc;

new class extends Component {
    public array $logs = [["taken_at" => "", "temp" => ""]];
    public array $sv_temp = [];

    #[On("reading-review")]
    public function dLogsLoad($logs, $sv_temps = "")
    {
        $this->logs = json_decode($logs, true);
        $this->sv_temps = json_decode($sv_temps, true);

        $this->js(
            "
            const options = " .
                json_encode(InsStc::getChartJsOptions($this->logs, $this->sv_temps)) .
                ";

            // Add tooltip configuration
            options.options.plugins.tooltip = {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y + '°C';
                    },
                    title: function(context) {
                        if (!context[0]) return '';
                        const date = new Date(context[0].parsed.x);
                        return date.toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                }
            };

            const parent = \$wire.\$el.querySelector('#chart-container');
            parent.innerHTML = '';

            const canvas = document.createElement('canvas');
            canvas.id = 'chart-main';
            parent.appendChild(canvas);

            new Chart(canvas, options);
        ",
        );
    }
};
?>

<div class="p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __("Tinjau data") }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
    </div>
    <div class="h-80 overflow-hidden my-8" id="chart-container" wire:key="chart-container" wire:ignore></div>
    <div class="grid grid-cols-2 gap-x-3">
        <div>
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Pembagian zona") }}
            </h2>
            <div class="mt-3">
                <div>{{ __("Preheat") }}</div>
                <div>{{ __("Zona 1") . ": 70-80°C" }}</div>
                <div>{{ __("Zona 2") . ": 60-70°C" }}</div>
                <div>{{ __("Zona 3") . ": 50-60°C" }}</div>
                <div>{{ __("Zona 4") . ": 40-50°C" }}</div>
                <div>{{ __("Postheat") }}</div>
            </div>
        </div>
        <div class="max-h-48 overflow-y-auto relative">
            <table class="table table-xs text-sm overflow-hidden">
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th>{{ __("No.") }}</th>
                        <th>{{ __("Diambil pada") }}</th>
                        <th>{{ __("Suhu") }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $index => $log)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $log["taken_at"] }}</td>
                            <td>{{ $log["temp"] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
