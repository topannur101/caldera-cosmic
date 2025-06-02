<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsLdc;
use App\Models\InsLdcHide;
use App\Models\InsLdcGroup;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
    public string $shift = '';

    #[Url]
    public string $style = '';

    #[Url]
    public string $material = '';

    public array $lineStats = [];
    public array $shiftStats = [];
    public array $dailyStats = [];
    public array $styleStats = [];
    public array $materialStats = [];
    public array $summaryKpis = [];

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
        $this->calculateAnalytics();
        $this->renderCharts();
    }

    private function calculateAnalytics()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $baseQuery = InsLdcHide::join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
            ->when($this->line, fn($q) => $q->where('ins_ldc_groups.line', $this->line))
            ->when($this->shift, fn($q) => $q->where('ins_ldc_hides.shift', $this->shift))
            ->when($this->style, fn($q) => $q->where('ins_ldc_groups.style', 'LIKE', '%' . $this->style . '%'))
            ->when($this->material, fn($q) => $q->where('ins_ldc_groups.material', 'LIKE', '%' . $this->material . '%'));

        if (!$this->is_workdate) {
            $baseQuery->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $baseQuery->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }

        $this->calculateLineStats($baseQuery);
        $this->calculateShiftStats($baseQuery);
        $this->calculateDailyStats($baseQuery);
        $this->calculateStyleStats($baseQuery);
        $this->calculateMaterialStats($baseQuery);
        $this->calculateSummaryKpis($baseQuery);
    }

    private function calculateLineStats($baseQuery)
    {
        $lineData = (clone $baseQuery)
            ->select([
                'ins_ldc_groups.line',
                DB::raw('COUNT(*) as total_hides'),
                DB::raw('AVG(ins_ldc_hides.area_vn) as avg_area_vn'),
                DB::raw('AVG(ins_ldc_hides.area_ab) as avg_area_ab'),
                DB::raw('AVG(ins_ldc_hides.area_qt) as avg_area_qt'),
                DB::raw('AVG((ins_ldc_hides.area_vn - ins_ldc_hides.area_qt) / ins_ldc_hides.area_vn * 100) as avg_defect_rate'),
                DB::raw('AVG(ABS(ins_ldc_hides.area_vn - ins_ldc_hides.area_ab) / ins_ldc_hides.area_vn * 100) as avg_difference_rate'),
                DB::raw('AVG(ins_ldc_hides.area_qt / ins_ldc_hides.area_vn * 100) as avg_utilization'),
            ])
            ->groupBy('ins_ldc_groups.line')
            ->orderBy('ins_ldc_groups.line')
            ->get();

        $this->lineStats = $lineData->keyBy('line')->map(function($item) {
            return [
                'total_hides' => (int) $item->total_hides,
                'avg_area_vn' => round($item->avg_area_vn, 2),
                'avg_area_ab' => round($item->avg_area_ab, 2),
                'avg_area_qt' => round($item->avg_area_qt, 2),
                'avg_defect_rate' => round($item->avg_defect_rate, 2),
                'avg_difference_rate' => round($item->avg_difference_rate, 2),
                'avg_utilization' => round($item->avg_utilization, 2),
            ];
        })->toArray();
    }

    private function calculateShiftStats($baseQuery)
    {
        $shiftData = (clone $baseQuery)
            ->select([
                'ins_ldc_hides.shift',
                DB::raw('COUNT(*) as total_hides'),
                DB::raw('AVG((ins_ldc_hides.area_vn - ins_ldc_hides.area_qt) / ins_ldc_hides.area_vn * 100) as avg_defect_rate'),
                DB::raw('AVG(ABS(ins_ldc_hides.area_vn - ins_ldc_hides.area_ab) / ins_ldc_hides.area_vn * 100) as avg_difference_rate'),
                DB::raw('AVG(ins_ldc_hides.area_qt / ins_ldc_hides.area_vn * 100) as avg_utilization'),
            ])
            ->groupBy('ins_ldc_hides.shift')
            ->orderBy('ins_ldc_hides.shift')
            ->get();

        $this->shiftStats = $shiftData->keyBy('shift')->map(function($item) {
            return [
                'total_hides' => (int) $item->total_hides,
                'avg_defect_rate' => round($item->avg_defect_rate, 2),
                'avg_difference_rate' => round($item->avg_difference_rate, 2),
                'avg_utilization' => round($item->avg_utilization, 2),
            ];
        })->toArray();
    }

    private function calculateDailyStats($baseQuery)
    {
        $dateField = $this->is_workdate ? 'ins_ldc_groups.workdate' : 'DATE(ins_ldc_hides.updated_at)';
        
        $dailyData = (clone $baseQuery)
            ->select([
                DB::raw("$dateField as date"),
                DB::raw('COUNT(*) as total_hides'),
                DB::raw('AVG((ins_ldc_hides.area_vn - ins_ldc_hides.area_qt) / ins_ldc_hides.area_vn * 100) as avg_defect_rate'),
                DB::raw('AVG(ABS(ins_ldc_hides.area_vn - ins_ldc_hides.area_ab) / ins_ldc_hides.area_vn * 100) as avg_difference_rate'),
            ])
            ->groupBy(DB::raw($dateField))
            ->orderBy('date')
            ->get();

        $this->dailyStats = $dailyData->keyBy('date')->map(function($item) {
            return [
                'total_hides' => (int) $item->total_hides,
                'avg_defect_rate' => round($item->avg_defect_rate, 2),
                'avg_difference_rate' => round($item->avg_difference_rate, 2),
            ];
        })->toArray();
    }

    private function calculateStyleStats($baseQuery)
    {
        $styleData = (clone $baseQuery)
            ->select([
                'ins_ldc_groups.style',
                DB::raw('COUNT(*) as total_hides'),
                DB::raw('AVG((ins_ldc_hides.area_vn - ins_ldc_hides.area_qt) / ins_ldc_hides.area_vn * 100) as avg_defect_rate'),
                DB::raw('AVG(ins_ldc_hides.area_qt / ins_ldc_hides.area_vn * 100) as avg_utilization'),
            ])
            ->groupBy('ins_ldc_groups.style')
            ->havingRaw('COUNT(*) >= 5') // Only styles with at least 5 hides
            ->orderByDesc('total_hides')
            ->limit(20)
            ->get();

        $this->styleStats = $styleData->keyBy('style')->map(function($item) {
            return [
                'total_hides' => (int) $item->total_hides,
                'avg_defect_rate' => round($item->avg_defect_rate, 2),
                'avg_utilization' => round($item->avg_utilization, 2),
            ];
        })->toArray();
    }

    private function calculateMaterialStats($baseQuery)
    {
        $materialData = (clone $baseQuery)
            ->select([
                'ins_ldc_groups.material',
                DB::raw('COUNT(*) as total_hides'),
                DB::raw('AVG((ins_ldc_hides.area_vn - ins_ldc_hides.area_qt) / ins_ldc_hides.area_vn * 100) as avg_defect_rate'),
                DB::raw('AVG(ins_ldc_hides.area_qt / ins_ldc_hides.area_vn * 100) as avg_utilization'),
                DB::raw('AVG((ins_ldc_hides.area_vn - ins_ldc_hides.area_ab) / ins_ldc_hides.area_vn * 100) as avg_waste_rate'),
            ])
            ->whereNotNull('ins_ldc_groups.material')
            ->where('ins_ldc_groups.material', '!=', '')
            ->groupBy('ins_ldc_groups.material')
            ->havingRaw('COUNT(*) >= 3') // Only materials with at least 3 hides
            ->orderByDesc('total_hides')
            ->limit(15)
            ->get();

        $this->materialStats = $materialData->keyBy('material')->map(function($item) {
            return [
                'total_hides' => (int) $item->total_hides,
                'avg_defect_rate' => round($item->avg_defect_rate, 2),
                'avg_utilization' => round($item->avg_utilization, 2),
                'avg_waste_rate' => round($item->avg_waste_rate, 2),
            ];
        })->toArray();
    }

    private function calculateSummaryKpis($baseQuery)
    {
        $summary = (clone $baseQuery)
            ->select([
                DB::raw('COUNT(*) as total_hides'),
                DB::raw('SUM(ins_ldc_hides.area_vn) as total_area_vn'),
                DB::raw('SUM(ins_ldc_hides.area_ab) as total_area_ab'),
                DB::raw('SUM(ins_ldc_hides.area_qt) as total_area_qt'),
                DB::raw('AVG((ins_ldc_hides.area_vn - ins_ldc_hides.area_qt) / ins_ldc_hides.area_vn * 100) as avg_defect_rate'),
                DB::raw('AVG(ABS(ins_ldc_hides.area_vn - ins_ldc_hides.area_ab) / ins_ldc_hides.area_vn * 100) as avg_difference_rate'),
                DB::raw('COUNT(DISTINCT ins_ldc_groups.line) as total_lines'),
                DB::raw('COUNT(DISTINCT ins_ldc_groups.style) as total_styles'),
            ])
            ->first();

        $this->summaryKpis = [
            'total_hides' => (int) ($summary->total_hides ?? 0),
            'total_area_vn' => round($summary->total_area_vn ?? 0, 2),
            'total_area_ab' => round($summary->total_area_ab ?? 0, 2),
            'total_area_qt' => round($summary->total_area_qt ?? 0, 2),
            'avg_defect_rate' => round($summary->avg_defect_rate ?? 0, 2),
            'avg_difference_rate' => round($summary->avg_difference_rate ?? 0, 2),
            'total_lines' => (int) ($summary->total_lines ?? 0),
            'total_styles' => (int) ($summary->total_styles ?? 0),
            'overall_utilization' => $summary->total_area_vn > 0 ? round(($summary->total_area_qt / $summary->total_area_vn) * 100, 2) : 0,
        ];
    }

    private function renderCharts()
    {
        $lineChartOptions = InsLdc::getLinePerformanceChartOptions($this->lineStats);
        $shiftChartOptions = InsLdc::getShiftPerformanceChartOptions($this->shiftStats);
        $trendChartOptions = InsLdc::getThroughputTrendChartOptions($this->dailyStats);
        $styleChartOptions = InsLdc::getStyleAnalysisChartOptions($this->styleStats);
        $materialChartOptions = InsLdc::getMaterialAnalysisChartOptions($this->materialStats);

        $this->js("
            (function() {
                // Line Performance Chart
                const lineCtx = document.getElementById('line-performance-chart');
                if (window.linePerformanceChart) window.linePerformanceChart.destroy();
                window.linePerformanceChart = new Chart(lineCtx, " . json_encode($lineChartOptions) . ");

                // Shift Performance Chart
                const shiftCtx = document.getElementById('shift-performance-chart');
                if (window.shiftPerformanceChart) window.shiftPerformanceChart.destroy();
                window.shiftPerformanceChart = new Chart(shiftCtx, " . json_encode($shiftChartOptions) . ");

                // Throughput Trend Chart
                const trendCtx = document.getElementById('throughput-trend-chart');
                if (window.throughputTrendChart) window.throughputTrendChart.destroy();
                window.throughputTrendChart = new Chart(trendCtx, " . json_encode($trendChartOptions) . ");

                // Style Analysis Chart
                const styleCtx = document.getElementById('style-analysis-chart');
                if (window.styleAnalysisChart) window.styleAnalysisChart.destroy();
                window.styleAnalysisChart = new Chart(styleCtx, " . json_encode($styleChartOptions) . ");

                // Material Analysis Chart
                const materialCtx = document.getElementById('material-analysis-chart');
                if (window.materialAnalysisChart) window.materialAnalysisChart.destroy();
                window.materialAnalysisChart = new Chart(materialCtx, " . json_encode($materialChartOptions) . ");
            })();
        ");
    }

    public function showLineDetails($line)
    {
        $this->dispatch('line-details', line: $line);
    }

    public function showStyleDetails($style)
    {
        $this->dispatch('style-details', style: $style);
    }

    public function showMaterialDetails($material)
    {
        $this->dispatch('material-details', material: $material);
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
                <div class="grid gap-3">
                    <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at" id="cal-date-end" type="date"></x-text-input>
                    <div class="px-3">
                        <x-checkbox id="analytics_is_workdate" wire:model.live="is_workdate"
                            value="is_workdate"><span class="uppercase text-xs">{{ __('Workdate') }}</span></x-checkbox>
                    </div>
                </div>
            </div>
            <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-6 lg:my-0"></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="w-full lg:w-32">
                    <label for="analytics-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="analytics-line" wire:model.live="line" type="text" />
                </div>
                <div class="w-full lg:w-32">
                    <label for="analytics-shift" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Shift') }}</label>
                    <x-select class="w-full" id="analytics-shift" wire:model.live="shift">
                        <option value="0"></option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </x-select>
                </div>
                <div class="w-full lg:w-32">
                    <label for="analytics-style" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Style') }}</label>
                    <x-text-input id="analytics-style" wire:model.live="style" type="text" />
                </div>
                <div class="w-full lg:w-32">
                    <label for="analytics-material" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Material') }}</label>
                    <x-text-input id="analytics-material" wire:model.live="material" type="text" />
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

    <!-- Summary KPIs -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-1">{{ __('Total Kulit') }}</div>
            <div class="text-xl font-bold">{{ number_format($summaryKpis['total_hides'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-1">{{ __('Total VN (SF)') }}</div>
            <div class="text-xl font-bold">{{ number_format($summaryKpis['total_area_vn'] ?? 0, 1) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-1">{{ __('Total QT (SF)') }}</div>
            <div class="text-xl font-bold">{{ number_format($summaryKpis['total_area_qt'] ?? 0, 1) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-1">{{ __('Kelayakan (%)') }}</div>
            <div class="text-xl font-bold {{ ($summaryKpis['overall_utilization'] ?? 0) >= 85 ? 'text-green-500' : (($summaryKpis['overall_utilization'] ?? 0) >= 75 ? 'text-yellow-500' : 'text-red-500') }}">
                {{ ($summaryKpis['overall_utilization'] ?? 0) }}%
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-1">{{ __('Defect (%)') }}</div>
            <div class="text-xl font-bold {{ ($summaryKpis['avg_defect_rate'] ?? 0) <= 10 ? 'text-green-500' : (($summaryKpis['avg_defect_rate'] ?? 0) <= 20 ? 'text-yellow-500' : 'text-red-500') }}">
                {{ ($summaryKpis['avg_defect_rate'] ?? 0) }}%
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-1">{{ __('Selisih (%)') }}</div>
            <div class="text-xl font-bold {{ ($summaryKpis['avg_difference_rate'] ?? 0) <= 5 ? 'text-green-500' : (($summaryKpis['avg_difference_rate'] ?? 0) <= 10 ? 'text-yellow-500' : 'text-red-500') }}">
                {{ ($summaryKpis['avg_difference_rate'] ?? 0) }}%
            </div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="space-y-8">
        <!-- 1. Daily Production Trends (Full Width) -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="throughput-trend-chart" wire:ignore></canvas>
            </div>
        </div>

        <!-- 2. Line Performance: Chart + Table -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div class="h-full">
                    <canvas id="line-performance-chart" wire:ignore></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-700">
                    <h3 class="text-lg font-medium">{{ __('Performa Line') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-sm text-sm w-full">
                        <thead>
                            <tr class="text-xs uppercase text-neutral-500 border-b">
                                <th class="px-4 py-3">{{ __('Line') }}</th>
                                <th class="px-4 py-3">{{ __('Kulit') }}</th>
                                <th class="px-4 py-3">{{ __('Kelayakan (%)') }}</th>
                                <th class="px-4 py-3">{{ __('Defect (%)') }}</th>
                                <th class="px-4 py-3">{{ __('Selisih (%)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lineStats as $line => $stats)
                            <tr class="border-b border-neutral-100 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 cursor-pointer"
                                wire:click="showLineDetails('{{ $line }}')">
                                <td class="px-4 py-3 font-mono font-bold">{{ $line }}</td>
                                <td class="px-4 py-3">{{ number_format($stats['total_hides']) }}</td>
                                <td class="px-4 py-3">
                                    <span class="{{ $stats['avg_utilization'] >= 85 ? 'text-green-500' : ($stats['avg_utilization'] >= 75 ? 'text-yellow-500' : 'text-red-500') }}">
                                        {{ number_format($stats['avg_utilization'], 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="{{ $stats['avg_defect_rate'] <= 10 ? 'text-green-500' : ($stats['avg_defect_rate'] <= 20 ? 'text-yellow-500' : 'text-red-500') }}">
                                        {{ number_format($stats['avg_defect_rate'], 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="{{ $stats['avg_difference_rate'] <= 5 ? 'text-green-500' : ($stats['avg_difference_rate'] <= 10 ? 'text-yellow-500' : 'text-red-500') }}">
                                        {{ number_format($stats['avg_difference_rate'], 1) }}%
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. Material & Style Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div class="h-full">
                    <canvas id="material-analysis-chart" wire:ignore></canvas>
                </div>
            </div>
            @if(count($styleStats) > 0)
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-700">
                    <h3 class="text-lg font-medium">{{ __('Performa Style Tertinggi') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-sm text-sm w-full">
                        <thead>
                            <tr class="text-xs uppercase text-neutral-500 border-b">
                                <th class="px-4 py-3">{{ __('Style') }}</th>
                                <th class="px-4 py-3">{{ __('Volume') }}</th>
                                <th class="px-4 py-3">{{ __('Kelayakan (%)') }}</th>
                                <th class="px-4 py-3">{{ __('Defect (%)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($styleStats as $style => $stats)
                            <tr class="border-b border-neutral-100 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 cursor-pointer"
                                wire:click="showStyleDetails('{{ $style }}')">
                                <td class="px-4 py-3 font-mono">{{ $style }}</td>
                                <td class="px-4 py-3">{{ number_format($stats['total_hides']) }}</td>
                                <td class="px-4 py-3">
                                    <span class="{{ $stats['avg_utilization'] >= 85 ? 'text-green-500' : ($stats['avg_utilization'] >= 75 ? 'text-yellow-500' : 'text-red-500') }}">
                                        {{ number_format($stats['avg_utilization'], 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="{{ $stats['avg_defect_rate'] <= 10 ? 'text-green-500' : ($stats['avg_defect_rate'] <= 20 ? 'text-yellow-500' : 'text-red-500') }}">
                                        {{ number_format($stats['avg_defect_rate'], 1) }}%
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @else
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 flex items-center justify-center">
                <div class="text-center text-neutral-500">
                    <div class="text-4xl mb-2"><i class="icon-info"></i></div>
                    <div>{{ __('Tidak ada data style dengan volume cukup') }}</div>
                </div>
            </div>
            @endif
        </div>

        <!-- 4. Style & Shift Analysis -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div class="h-80">
                    <canvas id="style-analysis-chart" wire:ignore></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div class="h-80">
                    <canvas id="shift-performance-chart" wire:ignore></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals for Detail Views -->
    <div wire:key="modals">
        <!-- Line Details Modal -->
        <x-modal name="line-details" maxWidth="4xl">
            <div class="p-6">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">
                    {{ __('Detail Performa Line') }}
                </h2>
                <!-- Line detail content will be implemented separately -->
                <div class="text-center py-8 text-neutral-500">
                    {{ __('Detail analytics untuk line akan ditampilkan di sini') }}
                </div>
                <div class="mt-6 flex justify-end">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Tutup') }}
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>

        <!-- Style Details Modal -->
        <x-modal name="style-details" maxWidth="4xl">
            <div class="p-6">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">
                    {{ __('Detail Style Performance') }}
                </h2>
                <!-- Style detail content will be implemented separately -->
                <div class="text-center py-8 text-neutral-500">
                    {{ __('Detail analytics untuk style akan ditampilkan di sini') }}
                </div>
                <div class="mt-6 flex justify-end">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Tutup') }}
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>

        <!-- Material Details Modal -->
        <x-modal name="material-details" maxWidth="4xl">
            <div class="p-6">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">
                    {{ __('Detail Material Performance') }}
                </h2>
                <!-- Material detail content will be implemented separately -->
                <div class="text-center py-8 text-neutral-500">
                    {{ __('Detail analytics untuk material akan ditampilkan di sini') }}
                </div>
                <div class="mt-6 flex justify-end">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Tutup') }}
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript