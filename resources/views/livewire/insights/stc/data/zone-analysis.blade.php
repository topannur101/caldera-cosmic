<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsStc;
use App\Models\InsStcDSum;
use App\Models\InsStcDLog;
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
    public $line;

    #[Url]
    public string $position = '';

    public array $lines = [];
    public array $zone_stats = [];
    public array $uniformity_metrics = [];

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisWeek();
        }

        $this->lines = InsStcMachine::orderBy('line')->get()->pluck('line')->toArray();
    }

    #[On('update')]
    public function update()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsStcDSum::with(['ins_stc_machine', 'ins_stc_d_logs'])
            ->whereBetween('created_at', [$start, $end]);

        if ($this->line) {
            $query->whereHas('ins_stc_machine', function (Builder $query) {
                $query->where('line', $this->line);
            });
        }

        if ($this->position) {
            $query->where('position', $this->position);
        }

        $dSums = $query->get();
        
        $this->calculateZoneStats($dSums);
        $this->generateCharts($dSums);
    }

    private function calculateZoneStats($dSums)
    {
        $sectionData = [
            'section_1' => [], 'section_2' => [], 'section_3' => [], 'section_4' => [],
            'section_5' => [], 'section_6' => [], 'section_7' => [], 'section_8' => []
        ];

        foreach ($dSums as $dSum) {
            if ($dSum->ins_stc_d_logs->count() > 0) {
                $temps = $dSum->ins_stc_d_logs->pluck('temp')->toArray();
                $sections = InsStc::groupValuesBySection($temps);
                
                foreach ($sections as $sectionName => $values) {
                    if (isset($sectionData[$sectionName]) && !empty($values)) {
                        $sectionData[$sectionName] = array_merge($sectionData[$sectionName], $values);
                    }
                }
            }
        }

        // Calculate statistics for each section
        $this->zone_stats = [];
        $targetValues = InsStc::$target_values;
        
        foreach ($sectionData as $section => $temps) {
            if (!empty($temps)) {
                $sectionIndex = (int)str_replace('section_', '', $section) - 1;
                $target = $targetValues[$sectionIndex] ?? 0;
                
                sort($temps);
                $count = count($temps);
                $mean = array_sum($temps) / $count;
                $median = $count % 2 ? $temps[intval($count/2)] : ($temps[intval($count/2)-1] + $temps[intval($count/2)]) / 2;
                $min = min($temps);
                $max = max($temps);
                $range = $max - $min;
                
                // Calculate standard deviation
                $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $temps)) / $count;
                $stdDev = sqrt($variance);
                
                // Calculate target compliance
                $withinSpec = array_filter($temps, function($temp) use ($target) {
                    return abs($temp - $target) <= 5; // ±5°C tolerance
                });
                $complianceRate = (count($withinSpec) / $count) * 100;
                
                $this->zone_stats[$section] = [
                    'count' => $count,
                    'mean' => round($mean, 1),
                    'median' => round($median, 1),
                    'min' => $min,
                    'max' => $max,
                    'range' => $range,
                    'std_dev' => round($stdDev, 2),
                    'target' => $target,
                    'compliance_rate' => round($complianceRate, 1),
                    'mean_deviation' => round(abs($mean - $target), 1)
                ];
            }
        }

        // Calculate overall uniformity metrics
        if (!empty($this->zone_stats)) {
            $means = array_column($this->zone_stats, 'mean');
            $stdDevs = array_column($this->zone_stats, 'std_dev');
            
            $this->uniformity_metrics = [
                'mean_of_means' => round(array_sum($means) / count($means), 1),
                'range_of_means' => round(max($means) - min($means), 1),
                'avg_std_dev' => round(array_sum($stdDevs) / count($stdDevs), 2),
                'uniformity_index' => round(100 - ((max($means) - min($means)) / array_sum($means) * 100), 1)
            ];
        }
    }

    private function generateCharts($dSums)
    {
        // Prepare data for zone comparison chart
        $zoneData = [];
        foreach ($this->zone_stats as $section => $stats) {
            $zoneNumber = (int)str_replace('section_', '', $section);
            $zoneData[] = [
                'x' => "Zone $zoneNumber",
                'y' => $stats['mean'],
                'target' => $stats['target'],
                'min' => $stats['min'],
                'max' => $stats['max']
            ];
        }

        // Prepare data for uniformity trend chart
        $d_sums_grouped = InsStcDLog::whereIn('ins_stc_d_sum_id', $dSums->pluck('id'))
            ->get()
            ->groupBy('ins_stc_d_sum_id');

            $this->js("
            (function() {
                // Zone comparison chart
                const zoneComparisonOptions = {
                    chart: {
                        type: 'line',
                        height: 350,
                        background: 'transparent'
                    },
                    theme: {
                        mode: '" . session('bg', 'light') . "'
                    },
                    series: [
                        {
                            name: '" . __('Rata-rata suhu') . "',
                            data: " . json_encode(array_column($zoneData, 'y')) . ",
                            color: '#D64550'
                        },
                        {
                            name: '" . __('Target') . "',
                            data: " . json_encode(array_column($zoneData, 'target')) . ",
                            color: '#10B981'
                        }
                    ],
                    xaxis: {
                        categories: " . json_encode(array_column($zoneData, 'x')) . "
                    },
                    yaxis: {
                        title: { text: '°C' }
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 2
                    },
                    markers: {
                        size: 6
                    },
                    tooltip: {
                        shared: true
                    }
                };
                const zoneChart = \$wire.\$el.querySelector('#zone-comparison-chart');
                zoneChart.innerHTML = '<div id=\"zone-chart\"></div>';
                new ApexCharts(zoneChart.querySelector('#zone-chart'), zoneComparisonOptions).render();
                
                // Uniformity heatmap
                const uniformityOptions = " . json_encode(InsStc::getStandardZoneChartOptions($d_sums_grouped, 100, 100)) . ";
               
                const uniformityChart = \$wire.\$el.querySelector('#uniformity-heatmap');
                uniformityChart.innerHTML = '<div id=\"uniformity-chart\"></div>';
                new ApexCharts(uniformityChart.querySelector('#uniformity-chart'), uniformityOptions).render();
            })();
        ");
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
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex gap-3">
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
                    <label for="analysis-position"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                    <x-select id="analysis-position" wire:model.live="position">
                        <option value=""></option>
                        <option value="upper">{{ __('Atas') }}</option>
                        <option value="lower">{{ __('Bawah') }}</option>
                    </x-select>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                    <div class="relative w-3">
                        <x-spinner class="sm mono"></x-spinner>
                    </div>
                    <div>
                        {{ __('Memuat...') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Uniformity Metrics Overview -->
    @if(!empty($uniformity_metrics))
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Rata-rata keseluruhan') }}</div>
            <div class="text-2xl font-semibold">{{ $uniformity_metrics['mean_of_means'] }}°C</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Rentang zona') }}</div>
            <div class="text-2xl font-semibold">{{ $uniformity_metrics['range_of_means'] }}°C</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Standar deviasi rata-rata') }}</div>
            <div class="text-2xl font-semibold">{{ $uniformity_metrics['avg_std_dev'] }}°C</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Indeks keseragaman') }}</div>
            <div class="text-2xl font-semibold">{{ $uniformity_metrics['uniformity_index'] }}%</div>
        </div>
    </div>
    @endif

    <!-- Zone Statistics Table -->
    @if(!empty($zone_stats))
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg mb-6 overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium">{{ __('Statistik per zona') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-neutral-50 dark:bg-neutral-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase">{{ __('Zona') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase">{{ __('Jumlah data') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase">{{ __('Rata-rata') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase">{{ __('Target') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase">{{ __('Deviasi dari target') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase">{{ __('Std Dev') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase">{{ __('Rentang') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase">{{ __('Kepatuhan') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach($zone_stats as $section => $stats)
                    <tr>
                        <td class="px-6 py-4 font-medium">{{ __('Zona') }} {{ str_replace('section_', '', $section) }}</td>
                        <td class="px-6 py-4">{{ number_format($stats['count']) }}</td>
                        <td class="px-6 py-4">{{ $stats['mean'] }}°C</td>
                        <td class="px-6 py-4">{{ $stats['target'] }}°C</td>
                        <td class="px-6 py-4">
                            <span class="{{ $stats['mean_deviation'] > 5 ? 'text-red-500' : 'text-green-500' }}">
                                {{ $stats['mean_deviation'] }}°C
                            </span>
                        </td>
                        <td class="px-6 py-4">{{ $stats['std_dev'] }}°C</td>
                        <td class="px-6 py-4">{{ $stats['range'] }}°C</td>
                        <td class="px-6 py-4">
                            <span class="{{ $stats['compliance_rate'] < 95 ? 'text-red-500' : 'text-green-500' }}">
                                {{ $stats['compliance_rate'] }}%
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('Perbandingan zona') }}</h3>
            <div id="zone-comparison-chart" class="h-80" wire:ignore></div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('Peta keseragaman') }}</h3>
            <div id="uniformity-heatmap" class="h-80" wire:ignore></div>
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript