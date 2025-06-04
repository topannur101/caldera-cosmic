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
    public function updated()
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

        // Sort by stability (highest first)
        uasort($stats, function($a, $b) {
            return $b['overall']['stability'] <=> $a['overall']['stability'];
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
        
        $stabilityValues = array_column($metricData, 'stability');
        $accuracyValues = array_column($metricData, 'accuracy');
        $consistencyValues = array_column($metricData, 'consistency');
        $adjustmentValues = array_column($metricData, 'adjustment_rate');

        $chartData = [
            'labels' => $lines,
            'datasets' => [
                [
                    'label' => __('Stabilitas'),
                    'data' => $stabilityValues,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)'
                ],
                [
                    'label' => __('Akurasi'),
                    'data' => $accuracyValues,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)'
                ],
                [
                    'label' => __('Konsistensi'),
                    'data' => $consistencyValues,
                    'backgroundColor' => 'rgba(234, 179, 8, 0.8)'
                ],
                [
                    'label' => __('Penyetelan'),
                    'data' => $adjustmentValues,
                    'backgroundColor' => 'rgba(139, 69, 19, 0.8)'
                ]
            ]
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
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        ");
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
            <div class="grow flex justify-between gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div wire:loading.class="hidden">{{ count($machineStats) . ' ' . __('mesin') }}</div>
                        <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                            <div class="relative w-3"><x-spinner class="sm mono"></x-spinner></div>
                            <div>{{ __('Memuat...') }}</div>
                        </div>
                    </div>
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <x-text-button><i class="icon-ellipsis-vertical"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-slide-over', 'metrics-info')">
                            <i class="icon-info me-2"></i>{{ __('Penjelasan Metrik') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>

    <div wire:key="modals">
        <x-slide-over name="metrics-info">
            <div class="p-6">
                <div class="flex justify-between items-start mb-6">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Penjelasan Metrik Performa Mesin') }}
                    </h2>
                    <x-text-button type="button" x-on:click="window.dispatchEvent(escKey)">
                        <i class="icon-x"></i>
                    </x-text-button>
                </div>
                
                <div class="space-y-6 text-sm text-neutral-600 dark:text-neutral-400">
                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Stabilitas') }}</h3>
                        <p class="mb-2">{{ __('Mengukur seberapa konsisten suhu yang dihasilkan mesin.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Rumus: 100 - (StdDev × 10)') }}<br>
                            {{ __('StdDev rendah = skor tinggi = lebih stabil') }}<br>
                            {{ __('Contoh: StdDev 2°C = Skor 80%') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Akurasi') }}</h3>
                        <p class="mb-2">{{ __('Mengukur seberapa dekat suhu aktual dengan target yang ditetapkan.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Rumus: 100 - (AvgDeviation × 10)') }}<br>
                            {{ __('Deviasi rendah = skor tinggi = lebih akurat') }}<br>
                            {{ __('Contoh: Deviasi 1.5°C = Skor 85%') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Konsistensi') }}</h3>
                        <p class="mb-2">{{ __('Mengukur koefisien variasi suhu relatif terhadap rata-rata.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Rumus: 100 - ((StdDev / Mean) × 100)') }}<br>
                            {{ __('CV rendah = skor tinggi = lebih konsisten') }}<br>
                            {{ __('Contoh: StdDev 2°C, Mean 60°C = CV 3.3% = Skor 96.7%') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Tingkat Penyetelan') }}</h3>
                        <p class="mb-2">{{ __('Persentase pengukuran yang menggunakan penyetelan otomatis.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Rumus: (Jumlah penyetelan / Total pengukuran) × 100') }}<br>
                            {{ __('Tinggi = sering butuh penyetelan') }}<br>
                            {{ __('Rendah = mesin sudah stabil') }}
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Indikator Warna') }}</h3>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                                <span>{{ __('Hijau: ≥80% (Sangat Baik)') }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-yellow-500 rounded mr-2"></div>
                                <span>{{ __('Kuning: 60-79% (Baik)') }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-red-500 rounded mr-2"></div>
                                <span>{{ __('Merah: <60% (Perlu Perhatian)') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slide-over>
    </div>

    <!-- Charts and Table Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Performance Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-full">
                <canvas id="performance-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Performance Table -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden col-span-2">
            <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                <h3 class="text-lg font-medium">{{ __('Peringkat Performa') }}</h3>
            </div>
            <div>
                <table class="table table-sm text-sm w-full">
                    <thead class="sticky top-0 bg-neutral-50 dark:bg-neutral-700">
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
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript