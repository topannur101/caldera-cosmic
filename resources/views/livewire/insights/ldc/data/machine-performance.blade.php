<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsLdc;
use App\Models\InsLdcHide;
use App\Models\InsLdcGroup;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {

    use HasDateRangeFilter;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public bool $is_workdate = false;

    #[Url]
    public string $line = '';

    #[Url]
    public string $style = '';

    #[Url]
    public string $material = '';

    public array $machineStats = [];
    public array $machineAccuracyTrend = [];

    public function mount()
    {
        if(!$this->start_at || !$this->end_at) {
            $this->setThisWeek();
        }
    }

    private function getHidesQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsLdcHide::join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
            ->select(
                'ins_ldc_hides.*',
                'ins_ldc_hides.updated_at as hide_updated_at',
                'ins_ldc_groups.workdate as group_workdate',
                'ins_ldc_groups.style as group_style',
                'ins_ldc_groups.line as group_line',
                'ins_ldc_groups.material as group_material'
            );

        if (!$this->is_workdate) {
            $query->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $query->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }

        if ($this->line) {
            $query->where('ins_ldc_groups.line', $this->line);
        }

        if ($this->style) {
            $query->where('ins_ldc_groups.style', 'LIKE', '%' . $this->style . '%');
        }

        if ($this->material) {
            $query->where('ins_ldc_groups.material', 'LIKE', '%' . $this->material . '%');
        }

        return $query;
    }

    private function calculateMachineStats()
    {
        $hides = $this->getHidesQuery()->get();
        $machineStats = [];

        foreach ($hides as $hide) {
            // Extract machine code from hide code (e.g., XA from XA12345)
            $machineCode = substr($hide->code, 0, 2);
            
            if (!isset($machineStats[$machineCode])) {
                $machineStats[$machineCode] = [
                    'total_hides' => 0,
                    'total_vn' => 0,
                    'total_ab' => 0,
                    'total_qt' => 0,
                    'variances' => [],
                    'qt_percentages' => []
                ];
            }

            $variance = abs($hide->area_vn - $hide->area_ab);
            $qtPercentage = $hide->area_vn > 0 ? ($hide->area_qt / $hide->area_vn) * 100 : 0;

            $machineStats[$machineCode]['total_hides']++;
            $machineStats[$machineCode]['total_vn'] += $hide->area_vn;
            $machineStats[$machineCode]['total_ab'] += $hide->area_ab;
            $machineStats[$machineCode]['total_qt'] += $hide->area_qt;
            $machineStats[$machineCode]['variances'][] = $variance;
            $machineStats[$machineCode]['qt_percentages'][] = $qtPercentage;
        }

        // Calculate averages
        foreach ($machineStats as $machine => &$stats) {
            $stats['avg_variance'] = count($stats['variances']) > 0 ? 
                round(array_sum($stats['variances']) / count($stats['variances']), 2) : 0;
            $stats['avg_qt_percentage'] = count($stats['qt_percentages']) > 0 ? 
                round(array_sum($stats['qt_percentages']) / count($stats['qt_percentages']), 2) : 0;
            $stats['avg_vn'] = $stats['total_hides'] > 0 ? 
                round($stats['total_vn'] / $stats['total_hides'], 2) : 0;
            $stats['avg_ab'] = $stats['total_hides'] > 0 ? 
                round($stats['total_ab'] / $stats['total_hides'], 2) : 0;
        }

        $this->machineStats = $machineStats;
    }

    private function calculateAccuracyTrend()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();
        
        // Group by date and machine
        $dateField = $this->is_workdate ? 'ins_ldc_groups.workdate' : 'ins_ldc_hides.updated_at';
        
        $trendData = DB::table('ins_ldc_hides')
            ->join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
            ->selectRaw("
                DATE({$dateField}) as date,
                LEFT(ins_ldc_hides.code, 2) as machine,
                AVG(ABS(ins_ldc_hides.area_vn - ins_ldc_hides.area_ab)) as variance
            ")
            ->when(!$this->is_workdate, function ($query) use ($start, $end) {
                $query->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
            })
            ->when($this->is_workdate, function ($query) use ($start, $end) {
                $query->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
            })
            ->when($this->line, function ($query) {
                $query->where('ins_ldc_groups.line', $this->line);
            })
            ->when($this->style, function ($query) {
                $query->where('ins_ldc_groups.style', 'LIKE', '%' . $this->style . '%');
            })
            ->when($this->material, function ($query) {
                $query->where('ins_ldc_groups.material', 'LIKE', '%' . $this->material . '%');
            })
            ->groupBy(DB::raw("DATE({$dateField})"), DB::raw("LEFT(ins_ldc_hides.code, 2)"))
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'machine' => $item->machine,
                    'variance' => round($item->variance, 2)
                ];
            })
            ->toArray();

        $this->machineAccuracyTrend = $trendData;
    }

    #[On('update')]
    public function updated()
    {
        $this->calculateMachineStats();
        $this->calculateAccuracyTrend();
        $this->renderCharts();
    }

    private function renderCharts()
    {
        $accuracyOptions = InsLdc::getMachineAccuracyChartOptions($this->machineStats);
        $utilizationOptions = InsLdc::getMachineUtilizationChartOptions($this->machineStats);
        $qualityOptions = InsLdc::getMachineQualityChartOptions($this->machineStats);
        $trendOptions = InsLdc::getAccuracyTrendChartOptions($this->machineAccuracyTrend);

        $this->js("
            (function() {
                // Destroy existing charts
                if (window.accuracyChart) window.accuracyChart.destroy();
                if (window.utilizationChart) window.utilizationChart.destroy();
                if (window.qualityChart) window.qualityChart.destroy();
                if (window.trendChart) window.trendChart.destroy();

                // Machine Accuracy Chart
                const accuracyCtx = document.getElementById('accuracy-chart');
                window.accuracyChart = new Chart(accuracyCtx, " . json_encode($accuracyOptions) . ");

                // Machine Utilization Chart
                const utilizationCtx = document.getElementById('utilization-chart');
                window.utilizationChart = new Chart(utilizationCtx, " . json_encode($utilizationOptions) . ");

                // Machine Quality Chart
                const qualityCtx = document.getElementById('quality-chart');
                window.qualityChart = new Chart(qualityCtx, " . json_encode($qualityOptions) . ");

                // Accuracy Trend Chart
                const trendCtx = document.getElementById('trend-chart');
                window.trendChart = new Chart(trendCtx, " . json_encode($trendOptions) . ");
            })();
        ");
    }

    public function with(): array
    {
        return [
            'totalHides' => array_sum(array_column($this->machineStats, 'total_hides')),
            'totalVnArea' => array_sum(array_column($this->machineStats, 'total_vn')),
            'totalAbArea' => array_sum(array_column($this->machineStats, 'total_ab')),
            'totalQtArea' => array_sum(array_column($this->machineStats, 'total_qt')),
        ];
    }
};

?>

<div>
    <!-- Date and Filter Controls -->
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
                <div class="grid gap-3">
                    <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at" id="cal-date-end" type="date"></x-text-input>
                    <div class="px-3">
                        <x-checkbox id="machine_is_workdate" wire:model.live="is_workdate"
                            value="is_workdate"><span class="uppercase text-xs">{{ __('Workdate') }}</span></x-checkbox>
                    </div>
                </div>
            </div>
            <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-6 lg:my-0"></div>
            <div class="grid grid-cols-2 gap-3 w-full lg:w-64">
                <div class="w-full">
                    <label for="machine-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="machine-line" wire:model.live="line" type="text" />
                </div>
                <div class="w-full">
                    <label for="machine-style" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Style') }}</label>
                    <x-text-input id="machine-style" wire:model.live="style" type="text" />
                </div>
                <div class="w-full col-span-2">
                    <label for="machine-material" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Material') }}</label>
                    <x-text-input id="machine-material" wire:model.live="material" type="text" />
                </div>
            </div>
            <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-6 lg:my-0"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                    <div class="relative w-3">
                        <x-spinner class="sm mono"></x-spinner>
                    </div>
                    <div>{{ __('Memuat...') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Total Kulit') }}</div>
            <div class="text-2xl font-bold">{{ number_format($totalHides) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Total VN') }}</div>
            <div class="text-2xl font-bold">{{ number_format($totalVnArea, 1) }} SF</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Total AB') }}</div>
            <div class="text-2xl font-bold">{{ number_format($totalAbArea, 1) }} SF</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Total QT') }}</div>
            <div class="text-2xl font-bold">{{ number_format($totalQtArea, 1) }} SF</div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Machine Accuracy Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="accuracy-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Machine Utilization Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="utilization-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Machine Quality Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="quality-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Accuracy Trend Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="trend-chart" wire:ignore></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Machine Statistics Table -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium">{{ __('Statistik Detail per Mesin') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-sm text-sm w-full">
                <thead>
                    <tr class="text-xs uppercase text-neutral-500 border-b">
                        <th class="px-4 py-3">{{ __('Mesin') }}</th>
                        <th class="px-4 py-3">{{ __('Total Kulit') }}</th>
                        <th class="px-4 py-3">{{ __('Rata-rata VN') }}</th>
                        <th class="px-4 py-3">{{ __('Rata-rata AB') }}</th>
                        <th class="px-4 py-3">{{ __('Varians Rata-rata') }}</th>
                        <th class="px-4 py-3">{{ __('QT Rata-rata (%)') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($machineStats as $machine => $stats)
                    <tr class="border-b border-neutral-100 dark:border-neutral-700">
                        <td class="px-4 py-3 font-mono font-bold">{{ $machine }}</td>
                        <td class="px-4 py-3">{{ number_format($stats['total_hides']) }}</td>
                        <td class="px-4 py-3">{{ number_format($stats['avg_vn'], 2) }} SF</td>
                        <td class="px-4 py-3">{{ number_format($stats['avg_ab'], 2) }} SF</td>
                        <td class="px-4 py-3">
                            <span class="{{ $stats['avg_variance'] > 2.0 ? 'text-red-500' : ($stats['avg_variance'] > 1.0 ? 'text-yellow-500' : 'text-green-500') }}">
                                {{ number_format($stats['avg_variance'], 2) }} SF
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ number_format($stats['avg_qt_percentage'], 1) }}%</td>
                        <td class="px-4 py-3">
                            @if($stats['avg_variance'] <= 1.0)
                                <span class="text-green-500">{{ __('Baik') }}</span>
                            @elseif($stats['avg_variance'] <= 2.0)
                                <span class="text-yellow-500">{{ __('Perlu Perhatian') }}</span>
                            @else
                                <span class="text-red-500">{{ __('Perlu Kalibrasi') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if(empty($machineStats))
            <div class="text-center py-12">
                <div class="text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="icon-ghost"></i>
                </div>
                <div class="text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada data untuk periode yang dipilih') }}</div>
            </div>
            @endif
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript