<?php

use Carbon\Carbon;
use App\Models\InsRtcDevice;
use App\Models\InsRtcMetric;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Asantibanez\LivewireCharts\Models\LineChartModel;
use Illuminate\Database\Eloquent\Builder;

new class extends Component {
    #[Url]
    public $sline;
    
    public $min;
    public $max;

    public $dataLeft;
    public $dataRight;

    public $latest_thick_act_left;
    public $latest_thick_act_right;

    public $lines;

    public function mount(): void
    {
        $this->lines = InsRtcDevice::all()->pluck('line');
        $this->min = 3.50;
        $this->max = 2.50;
        $this->updateCharts();
    }

    public function updateCharts(): void
    {

        $start_at = Carbon::now()->subSeconds(40);
        $end_at = Carbon::now();

        // Get data from the database
        $metrics = InsRtcMetric::whereHas('ins_rtc_device', function (Builder $query) {
            $query->where('line', $this->sline);
        })
            ->whereBetween('dt_client', [$start_at, $end_at])
            ->get();

        // Transform data into required format
        $dataLeft = [];
        $dataRight = [];

        if ($metrics->count()) {
            foreach ($metrics as $metric) {
                $dt = $metric->dt_client;
                $dataLeft['thick_act'][$dt->toIso8601String()] = $metric->thick_act_left;
                $dataLeft['thick_std_min'][$dt->toIso8601String()] = $this->min;
                $dataLeft['thick_std_max'][$dt->toIso8601String()] = $this->max;

                $dataRight['thick_act'][$dt->toIso8601String()] = $metric->thick_act_right;
                $dataRight['thick_std_min'][$dt->toIso8601String()] = $this->min;
                $dataRight['thick_std_max'][$dt->toIso8601String()] = $this->max;

            }
        } else {
            $dt = $end_at;
            $dataLeft['thick_act'][$dt->toIso8601String()] = 0;
            $dataLeft['thick_std_min'][$dt->toIso8601String()] = $this->min;
            $dataLeft['thick_std_max'][$dt->toIso8601String()] = $this->max;

            $dataRight['thick_act'][$dt->toIso8601String()] = 0;
            $dataRight['thick_std_min'][$dt->toIso8601String()] = $this->min;
            $dataRight['thick_std_max'][$dt->toIso8601String()] = $this->max;
        }
        
        $this->latest_thick_act_left = end($dataLeft["thick_act"]);
        $this->latest_thick_act_right = end($dataRight["thick_act"]);

        $this->dataLeft = $dataLeft;
        $this->dataRight = $dataRight;        
    }

    public function with(): array
    {

        $chartLeft = (new LineChartModel())
            ->multiLine()
            ->withLegend()
            ->setJsonConfig([
                'legend.show' => false,
                'markers.size' => "['2', '0', '0']",
                'markers.colors' => "['#525252']",
                'markers.strokeWidth' => 0,
                'xaxis.type' => "'datetime'",
                'xaxis.labels.datetimeUTC' => false,
                'yaxis.max' => 6,
                'yaxis.min' => 1,
                'colors' => "['#7f63cc','#cccccc','#cccccc']",
                'tooltip.x.format' => "'HH:mm:ss'",
            ]);

        $chartRight = (new LineChartModel())
            ->multiLine()
            ->withLegend()
            ->setJsonConfig([
                'legend.show' => false,
                'markers.size' => "['2', '0', '0']",
                'markers.colors' => "['#525252']",
                'markers.strokeWidth' => 0,
                'xaxis.type' => "'datetime'",
                'xaxis.labels.datetimeUTC' => false,
                'yaxis.max' => 6,
                'yaxis.min' => 1,
                'colors' => "['#7f63cc','#cccccc','#cccccc']",
                'tooltip.x.format' => "'HH:mm:ss'",
            ]);

        $dict = [
            'thick_act'     => __('Tebal'),
            'thick_std_min' => __('Minimum'),
            'thick_std_max' => __('Maksimum')
        ];

        foreach ($this->dataLeft as $seriesName => $seriesData) {
            $displayName = isset($dict[$seriesName]) ? $dict[$seriesName] : $seriesName;

            foreach ($seriesData as $time => $value) {
                $chartLeft->addSeriesPoint($displayName, $time, $value);
            }
        }

        foreach ($this->dataRight as $seriesName => $seriesData) {
            $displayName = isset($dict[$seriesName]) ? $dict[$seriesName] : $seriesName;

            foreach ($seriesData as $time => $value) {
                $chartRight->addSeriesPoint($displayName, $time, $value);
            }
        }

        return [
            'chartLeft' => $chartLeft,
            'chartRight' => $chartRight,
        ];
    }
}; ?>

<div class="w-full h-screen p-4">
    <div wire:poll.20s="updateCharts" class="h-1/2 px-4 py-8">
        <div class="grid grid-cols-6 grid-rows-1 gap-4">
            <div class="col-span-2">
                <div>
                    <div class="text-xl uppercase mb-3 mx-1">{{ __('Model') }}</div>
                    <div class="text-6xl">Nama model semu</div>
                </div>
            </div>
            <div class="col-start-3">
                <div>
                    <div class="text-xl uppercase mb-3 mx-1">{{ __('OG/RS') }}</div>
                    <div class="text-7xl py-3">000</div>
                </div>
            </div>
            <div class="col-start-4">
                <div>
                    <div class="text-xl uppercase mb-3 mx-1">{{ __('Min') }}</div>
                    <div class="text-7xl py-3">{{ number_format($min, 2) }}</div>
                </div>
            </div>
            <div class="col-start-5">
                <div>
                    <div class="text-xl uppercase mb-3 mx-1">{{ __('Maks') }}</div>
                    <div class="text-7xl py-3">{{ number_format($max, 2) }}</div>
                </div>
            </div>
            <div class="col-start-6">
                <div>
                    <div class="text-xl uppercase mb-3 mx-1">{{ __('Line') }}</div>
                    <x-select wire:model.live="sline" class="text-7xl">
                        <option value=""></option>
                        @foreach ($lines as $line)
                            <option value="{{ $line }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
        </div>
    </div>
    <div wire:key="chart-container" class="h-1/2 grid grid-cols-2 gap-4">
        <div
            class="w-full h-full flex flex-col px-8 py-6 bg-white dark:bg-neutral-800 shadow-md overflow-hidden sm:rounded-lg">
            <div class="flex-none w-full flex justify-between">
                <div class="text-xl uppercase">{{ __('Kiri') }}</div>
                <div class="text-7xl">{{ number_format($latest_thick_act_left, 2) }}</div>
            </div>
            <div class="flex-1">
                <livewire:livewire-line-chart wire:key="chart-left"
                    :line-chart-model="$chartLeft" />
            </div>
        </div>

        <div
            class="w-full h-full flex flex-col px-8 py-6 bg-white dark:bg-neutral-800 shadow-md overflow-hidden sm:rounded-lg">
            <div class="flex-none w-full flex justify-between">
                <div class="text-xl uppercase">{{ __('Kanan') }}</div>
                <div class="text-7xl">{{ number_format($latest_thick_act_right, 2) }}</div>
            </div>
            <div class="flex-1">
                <livewire:livewire-line-chart wire:key="chart-right"
                    :line-chart-model="$chartRight" />
            </div>
        </div>

    </div>
</div>