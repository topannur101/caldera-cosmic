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
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public string $metric = 'stability';

    public array $machineStats = [];
    public array $performanceRanking = [];

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
        $this->calculateMachinePerformance();
        $this->renderCharts();
    }

    private function calculateMachinePerformance()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();
        $targets = InsStc::$target_values;

        $machines = InsStcMachine::with(['ins_stc_d_sums' => function($query) use ($start, $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }])->get();

        $stats = [];

        foreach ($machines as $machine) {
            $upperSums = $machine->ins_stc_d_sums->where('position', 'upper');
            $lowerSums = $machine->ins_stc_d_sums->where('position', 'lower');

            $stats[$machine->line] = [
                'line' => $machine->line,
                'name' => $machine->name,
                'code' => $machine->code,
                'is_auto' => strpos($machine->ip_address, '127.') !== 0,
                'upper' => $this->calculatePositionStats($upperSums, $targets),
                'lower' => $this->calculatePositionStats($lowerSums, $targets),
            ];

            // Calculate overall performance
            $stats[$machine->line]['overall'] = $this->calculateOverallStats(
                $stats[$machine->line]['upper'], 
                $stats[$machine->line]['lower']
            );
        }

        // Sort by selected metric
        uasort($stats, function($a, $b) {
            return $b['overall'][$this->metric] <=> $a['overall'][$this->metric];
        });

        $this->machineStats = $stats;
        $this->performanceRanking = array_values($stats);
    }

    private function calculatePositionStats($dSums, $targets)
    {
        if ($dSums->isEmpty()) {
            return [
                'count' => 0,
                'avg_temp' => 0,
                'stability' => 0,
                'accuracy' => 0,
                'consistency' => 0,
                'adjustment_rate' => 0
            ];
        }

        $allTemps = [];
        $deviations = [];
        $adjustments = 0;

        foreach ($dSums as $dSum) {
            $hbValues = json_decode($dSum->hb_values, true) ?? [];
            
            if ($dSum->is_applied) {
                $adjustments++;
            }

            for ($i = 0; $i < 8; $i++) {
                if (isset($hbValues[$i]) && isset($targets[$i])) {
                    $allTemps[] = $hbValues[$i];
                    $deviations[] = abs($hbValues[$i] - $targets[$i]);
                }
            }
        }

        $count = $dSums->count();
        $avgTemp = count($allTemps) > 0 ? array_sum($allTemps) / count($allTemps) : 0;
        $avgDeviation = count($deviations) > 0 ? array_sum($deviations) / count($deviations) : 0;
        
        // Calculate stability (lower standard deviation = more stable)
        $stability = count($allTemps) > 1 ? $this->calculateStandardDeviation($allTemps) : 0;
        $stabilityScore = max(0, 100 - ($stability * 10)); // Convert to score

        // Calculate accuracy (closer to target = better)
        $accuracyScore = max(0, 100 - ($avgDeviation * 10));

        // Calculate consistency (coefficient of variation)
        $consistency = $avgTemp > 0 ? (($stability / $avgTemp) * 100) : 0;
        $consistencyScore = max(0, 100 - $consistency);

        return [
            'count' => $count,
            'avg_temp' => round($avgTemp, 1),
            'stability' => round($stabilityScore, 1),
            'accuracy' => round($accuracyScore, 1),
            'consistency' => round($consistencyScore, 1),
            'adjustment_rate' => $count > 0 ? round(($adjustments / $count) * 100, 1) : 0
        ];
    }

    private function calculateOverallStats($upper, $lower)
    {
        $totalCount = $upper['count'] + $lower['count'];
        
        if ($totalCount === 0) {
            return [
                'count' => 0,
                'avg_temp' => 0,
                'stability' => 0,
                'accuracy' => 0,
                'consistency' => 0,
                'adjustment_rate' => 0
            ];
        }

        $upperWeight = $upper['count'] / $totalCount;
        $lowerWeight = $lower['count'] / $totalCount;

        return [
            'count' => $totalCount,
            'avg_temp' => round(($upper['avg_temp'] * $upperWeight) + ($lower['avg_temp'] * $lowerWeight), 1),
            'stability' => round(($upper['stability'] * $upperWeight) + ($lower['stability'] * $lowerWeight), 1),
            'accuracy' => round(($upper['accuracy'] * $upperWeight) + ($lower['accuracy'] * $lowerWeight), 1),
            'consistency' => round(($upper['consistency'] * $upperWeight) + ($lower['consistency'] * $lowerWeight), 1),
            'adjustment_rate' => round(($upper['adjustment_rate'] * $upperWeight) + ($lower['adjustment_rate'] * $lowerWeight), 1)
        ];
    }

    private function calculateStandardDeviation($values)
    {
        $count = count($values);
        if ($count < 2) return 0;
        
        $mean = array_sum($values) / $count;
        $squareDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return sqrt(array_sum($squareDiffs) / ($count - 1));
    }

    private function renderCharts()
    {
        $lines = array_map(fn($stat) => "Line " . sprintf('%02d', $stat['line']), $this->performanceRanking);
        $metricData = array_column($this->performanceRanking, 'overall');
        $selectedMetricValues = array_column($metricData, $this->metric);

        $chartData = [
            'labels' => $lines,
            'datasets' => [[
                'label' => $this->getMetricLabel($this->metric),
                'data' => $selectedMetricValues,
                'backgroundColor' => array_map(function($value) {
                    if ($value >= 80) return 'rgba(34, 197, 94, 0.8)';
                    if ($value >= 60) return 'rgba(234, 179, 8, 0.8)';
                    return 'rgba(239, 68, 68, 0.8)';
                }, $selectedMetricValues)
            ]]
        ];

        $this->js("
            const ctx = document.getElementById('performance-chart');
            if (window.performanceChart) window.performanceChart.destroy();
            window.performanceChart = new Chart(ctx, {
                type: 'bar',
                data: " . json_encode($chartData) . ",
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        title: {
                            display: true,
                            text: '" . $this->getMetricLabel($this->metric) . " " . __('by Machine') . "'
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + ('" . $this->metric . "' === 'avg_temp' ? '°C' : '%');
                                }
                            }
                        }
                    }
                }
            });
        ");
    }

    private function getMetricLabel($metric)
    {
        return match($metric) {
            'stability' => __('Stabilitas'),
            'accuracy' => __('Akurasi'),
            'consistency' => __('Konsistensi'),
            'adjustment_rate' => __('Tingkat Penyetelan'),
            'avg_temp' => __('Rata-rata Suhu'),
            default => __('Metrik')
        };
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
                                <x-dropdown-link href="#" wire:click.prevent="setToday">{{ __('Hari ini') }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">{{ __('Kemarin') }}</x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">{{ __('Minggu ini') }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">{{ __('Minggu lalu') }}</x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">{{ __('Bulan ini') }}</x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">{{ __('Bulan lalu') }}</x-dropdown-link>
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
            <div>
                <label for="metric-select" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Metrik') }}</label>
                <x-select id="metric-select" wire:model.live="metric">
                    <option value="stability">{{ __('Stabilitas') }}</option>
                    <option value="accuracy">{{ __('Akurasi') }}</option>
                    <option value="consistency">{{ __('Konsistensi') }}</option>
                    <option value="adjustment_rate">{{ __('Tingkat Penyetelan') }}</option>
                </x-select>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                    <div class="relative w-3"><x-spinner class="sm mono"></x-spinner></div>
                    <div>{{ __('Memuat...') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Chart -->
    <div class="mb-8 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
        <div class="h-96">
            <canvas id="performance-chart"></canvas>
        </div>
    </div>

    <!-- Performance Table -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <table class="table table-sm text-sm w-full">
            <thead>
                <tr class="text-xs uppercase text-neutral-500 border-b">
                    <th class="px-4 py-3">{{ __('Rank') }}</th>
                    <th class="px-4 py-3">{{ __('Line') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Pengukuran') }}</th>
                    <th class="px-4 py-3">{{ __('Stabilitas') }}</th>
                    <th class="px-4 py-3">{{ __('Akurasi') }}</th>
                    <th class="px-4 py-3">{{ __('Konsistensi') }}</th>
                    <th class="px-4 py-3">{{ __('Penyetelan') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($performanceRanking as $index => $machine)
                <tr class="border-b border-neutral-100 dark:border-neutral-700">
                    <td class="px-4 py-3">
                        @if($index === 0)
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                🥇 #{{ $index + 1 }}
                            </span>
                        @elseif($index === 1)
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                🥈 #{{ $index + 1 }}
                            </span>
                        @elseif($index === 2)
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">
                                🥉 #{{ $index + 1 }}
                            </span>
                        @else
                            <span class="text-neutral-500">#{{ $index + 1 }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="font-mono font-bold">{{ sprintf('%02d', $machine['line']) }}</span>
                            @if($machine['is_auto'])
                                <i class="icon-badge-check text-caldy-500 ml-2" title="{{ __('Kontrol otomatis') }}"></i>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs {{ (substr($machine['code'], 0, 3) == 'OLD' ) ? 'text-caldy-500' : 'text-neutral-500' }}">
                            {{ substr($machine['code'], 0, 3) == 'OLD' ? __('Lama') : __('Baru') }}
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ number_format($machine['overall']['count']) }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="text-sm {{ $machine['overall']['stability'] >= 80 ? 'text-green-600' : ($machine['overall']['stability'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($machine['overall']['stability'], 1) }}%
                            </span>
                            <div class="ml-2 w-16 bg-neutral-200 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $machine['overall']['stability'] >= 80 ? 'bg-green-500' : ($machine['overall']['stability'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                     style="width: {{ $machine['overall']['stability'] }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="text-sm {{ $machine['overall']['accuracy'] >= 80 ? 'text-green-600' : ($machine['overall']['accuracy'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($machine['overall']['accuracy'], 1) }}%
                            </span>
                            <div class="ml-2 w-16 bg-neutral-200 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $machine['overall']['accuracy'] >= 80 ? 'bg-green-500' : ($machine['overall']['accuracy'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                     style="width: {{ $machine['overall']['accuracy'] }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="text-sm {{ $machine['overall']['consistency'] >= 80 ? 'text-green-600' : ($machine['overall']['consistency'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($machine['overall']['consistency'], 1) }}%
                            </span>
                            <div class="ml-2 w-16 bg-neutral-200 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $machine['overall']['consistency'] >= 80 ? 'bg-green-500' : ($machine['overall']['consistency'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                     style="width: {{ $machine['overall']['consistency'] }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm {{ $machine['overall']['adjustment_rate'] >= 80 ? 'text-green-600' : ($machine['overall']['adjustment_rate'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ number_format($machine['overall']['adjustment_rate'], 1) }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Legend -->
    <div class="mt-6 p-4 bg-neutral-50 dark:bg-neutral-900 rounded-lg">
        <h3 class="text-sm font-medium mb-3">{{ __('Penjelasan Metrik:') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <strong>{{ __('Stabilitas:') }}</strong> {{ __('Seberapa konsisten suhu (semakin rendah deviasi standar, semakin baik)') }}
            </div>
            <div>
                <strong>{{ __('Akurasi:') }}</strong> {{ __('Seberapa dekat suhu dengan target (semakin kecil deviasi, semakin baik)') }}
            </div>
            <div>
                <strong>{{ __('Konsistensi:') }}</strong> {{ __('Koefisien variasi suhu (semakin rendah, semakin konsisten)') }}
            </div>
            <div>
                <strong>{{ __('Tingkat Penyetelan:') }}</strong> {{ __('Persentase pengukuran yang menggunakan penyetelan otomatis') }}
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript