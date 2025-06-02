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

new class extends Component {
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public bool $is_workdate = true;

    #[Url]
    public string $material = '';

    #[Url]
    public string $line = '';

    #[Url]
    public ?int $grade = null;

    public array $materials = [];
    public array $lines = [];
    public array $qualityMetrics = [];
    public string $selectedMaterial = '';
    public array $materialDetails = [];

    public function mount()
    {
        if(!$this->start_at || !$this->end_at) {
            $this->setThisWeek();
        }

        // Get available filters
        $this->materials = InsLdcGroup::distinct()
            ->whereNotNull('material')
            ->where('material', '!=', '')
            ->pluck('material')
            ->sort()
            ->values()
            ->toArray();

        $this->lines = InsLdcGroup::distinct()
            ->whereNotNull('line')
            ->orderBy('line')
            ->pluck('line')
            ->toArray();
    }

    private function getHidesQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsLdcHide::join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
            ->join('users', 'ins_ldc_hides.user_id', '=', 'users.id')
            ->select(
                'ins_ldc_hides.*',
                'ins_ldc_hides.updated_at as hide_updated_at',
                'ins_ldc_groups.workdate as group_workdate',
                'ins_ldc_groups.style as group_style',
                'ins_ldc_groups.line as group_line',
                'ins_ldc_groups.material as group_material',
                'users.emp_id as user_emp_id',
                'users.name as user_name'
            );

        if (!$this->is_workdate) {
            $query->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $query->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }

        if ($this->material) {
            $query->where('ins_ldc_groups.material', 'LIKE', '%' . $this->material . '%');
        }

        if ($this->line) {
            $query->where('ins_ldc_groups.line', $this->line);
        }

        if ($this->grade) {
            $query->where('ins_ldc_hides.grade', $this->grade);
        }

        return $query;
    }

    #[On('update')]
    public function updated()
    {
        $hides = $this->getHidesQuery()->get()->toArray();
        $this->calculateQualityMetrics($hides);
        $this->renderCharts($hides);
    }

    private function calculateQualityMetrics(array $hides)
    {
        $totalHides = count($hides);
        $totalVN = array_sum(array_column($hides, 'area_vn'));
        $totalAB = array_sum(array_column($hides, 'area_ab'));
        $totalQT = array_sum(array_column($hides, 'area_qt'));

        $overallDefectRate = $totalVN > 0 ? (($totalVN - $totalQT) / $totalVN) * 100 : 0;
        $overallDifferenceRate = $totalVN > 0 ? (($totalVN - $totalAB) / $totalVN) * 100 : 0;

        // Grade statistics
        $gradeStats = [];
        $grades = [1, 2, 3, 4, 5];
        foreach ($grades as $grade) {
            $gradeHides = array_filter($hides, fn($hide) => $hide['grade'] == $grade);
            $gradeVN = array_sum(array_column($gradeHides, 'area_vn'));
            $gradeQT = array_sum(array_column($gradeHides, 'area_qt'));
            
            $gradeStats[$grade] = [
                'count' => count($gradeHides),
                'avg_qt_percent' => $gradeVN > 0 ? ($gradeQT / $gradeVN) * 100 : 0
            ];
        }

        // Material performance
        $materialStats = [];
        $materials = array_unique(array_column($hides, 'group_material'));
        foreach ($materials as $material) {
            if (!$material) continue;
            
            $materialHides = array_filter($hides, fn($hide) => $hide['group_material'] === $material);
            $matVN = array_sum(array_column($materialHides, 'area_vn'));
            $matQT = array_sum(array_column($materialHides, 'area_qt'));
            
            $materialStats[$material] = [
                'count' => count($materialHides),
                'defect_rate' => $matVN > 0 ? (($matVN - $matQT) / $matVN) * 100 : 0
            ];
        }

        // Sort materials by Tingkat defect
        uasort($materialStats, fn($a, $b) => $a['defect_rate'] <=> $b['defect_rate']);

        $this->qualityMetrics = [
            'total_hides' => $totalHides,
            'overall_defect_rate' => round($overallDefectRate, 2),
            'overall_difference_rate' => round($overallDifferenceRate, 2),
            'total_vn' => round($totalVN, 2),
            'total_qt' => round($totalQT, 2),
            'grade_stats' => $gradeStats,
            'material_stats' => $materialStats,
            'best_material' => $materialStats ? array_key_first($materialStats) : null,
            'worst_material' => $materialStats ? array_key_last($materialStats) : null
        ];
    }

    private function renderCharts(array $hides)
    {
        if (empty($hides)) {
            $this->js("
                // Clear all charts if no data
                ['defect-analysis-chart', 'grade-correlation-chart', 'measurement-difference-chart', 'quality-distribution-chart'].forEach(id => {
                    const container = document.getElementById(id + '-container');
                    if (container) container.innerHTML = '<div class=\"flex items-center justify-center h-full text-neutral-500\">" . __('No data available') . "</div>';
                });
            ");
            return;
        }

        // Generate chart options
        $defectOptions = InsLdc::getDefectAnalysisChartOptions($hides);
        $gradeOptions = InsLdc::getGradeQtCorrelationChartOptions($hides);
        $differenceOptions = InsLdc::getMeasurementDifferenceChartOptions($hides);
        $distributionOptions = InsLdc::getQualityDistributionChartOptions($hides);

        $this->js("
            (function() {
                // Destroy existing charts
                if (window.defectChart) window.defectChart.destroy();
                if (window.gradeChart) window.gradeChart.destroy();
                if (window.differenceChart) window.differenceChart.destroy();
                if (window.distributionChart) window.distributionChart.destroy();

                // Defect Analysis Chart
                const defectCtx = document.getElementById('defect-analysis-chart');
                if (defectCtx) {
                    window.defectChart = new Chart(defectCtx, " . json_encode($defectOptions) . ");
                }

                // Grade Correlation Chart
                const gradeCtx = document.getElementById('grade-correlation-chart');
                if (gradeCtx) {
                    window.gradeChart = new Chart(gradeCtx, " . json_encode($gradeOptions) . ");
                }

                // Measurement Difference Chart
                const differenceCtx = document.getElementById('measurement-difference-chart');
                if (differenceCtx) {
                    window.differenceChart = new Chart(differenceCtx, " . json_encode($differenceOptions) . ");
                }

                // Quality Distribution Chart
                const distributionCtx = document.getElementById('quality-distribution-chart');
                if (distributionCtx) {
                    window.distributionChart = new Chart(distributionCtx, " . json_encode($distributionOptions) . ");
                }
            })();
        ");
    }

    public function showMaterialDetails(string $material)
    {
        $this->selectedMaterial = $material;
        
        $hides = $this->getHidesQuery()
            ->where('ins_ldc_groups.material', $material)
            ->get()
            ->toArray();

        $this->materialDetails = [
            'name' => $material,
            'total_hides' => count($hides),
            'total_vn' => array_sum(array_column($hides, 'area_vn')),
            'total_qt' => array_sum(array_column($hides, 'area_qt')),
            'defect_rate' => 0,
            'grade_breakdown' => []
        ];

        if ($this->materialDetails['total_vn'] > 0) {
            $this->materialDetails['defect_rate'] = 
                (($this->materialDetails['total_vn'] - $this->materialDetails['total_qt']) / $this->materialDetails['total_vn']) * 100;
        }

        // Grade breakdown
        for ($grade = 1; $grade <= 5; $grade++) {
            $gradeHides = array_filter($hides, fn($hide) => $hide['grade'] == $grade);
            $this->materialDetails['grade_breakdown'][$grade] = count($gradeHides);
        }

        $this->js('$dispatch("open-modal", "material-details")');
    }

};

?>

<div>
    <!-- Filters -->
    <div class="p-0 sm:p-1 mb-6">
        <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <!-- Date Range -->
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
                <div class="grid gap-3">
                    <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at" id="cal-date-end" type="date"></x-text-input>
                    <div class="px-3">
                        <x-checkbox id="quality_analytics_is_workdate" wire:model.live="is_workdate"
                            value="is_workdate"><span class="uppercase text-xs">{{ __('Workdate') }}</span></x-checkbox>
                    </div>
                </div>
            </div>

            <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-6 lg:my-0"></div>

            <!-- Filters -->
            <div class="grid grid-cols-2 gap-3 w-full lg:w-64">
                <div class="w-full">
                    <label for="line-filter" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-select class="w-full" id="line-filter" wire:model.live="line">
                        <option value=""></option>
                        @foreach($lines as $l)
                        <option value="{{ $l }}">{{ $l }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="w-full">
                    <label for="grade-filter" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Grade') }}</label>
                    <x-select class="w-full" id="grade-filter" wire:model.live="grade">
                        <option value="0"></option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </x-select>
                </div>
                <div class="w-full col-span-2">
                    <label for="material-filter" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Material') }}</label>
                    <x-text-input id="material-filter" wire:model.live="material" type="text" />
                </div>
            </div>

            <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-6 lg:my-0"></div>

            <!-- Loading indicator -->
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

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Total Kulit') }}</div>
            <div class="text-2xl font-bold">{{ number_format($qualityMetrics['total_hides'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Tingkat Defect') }}</div>
            <div class="text-2xl font-bold {{ ($qualityMetrics['overall_defect_rate'] ?? 0) > 15 ? 'text-red-500' : (($qualityMetrics['overall_defect_rate'] ?? 0) > 10 ? 'text-yellow-500' : 'text-green-500') }}">
                {{ ($qualityMetrics['overall_defect_rate'] ?? 0) }}%
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Selisih Pengukuran') }}</div>
            <div class="text-2xl font-bold {{ abs($qualityMetrics['overall_difference_rate'] ?? 0) > 5 ? 'text-red-500' : (abs($qualityMetrics['overall_difference_rate'] ?? 0) > 2 ? 'text-yellow-500' : 'text-green-500') }}">
                {{ ($qualityMetrics['overall_difference_rate'] ?? 0) }}%
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Material Terbaik') }}</div>
            <div class="text-sm font-medium truncate" title="{{ $qualityMetrics['best_material'] ?? __('Tidak ada') }}">
                {{ $qualityMetrics['best_material'] ?? __('Tidak ada') }}
            </div>
            @if($qualityMetrics['best_material'] ?? false)
            <div class="text-xs text-neutral-500">
                {{ round($qualityMetrics['material_stats'][$qualityMetrics['best_material']]['defect_rate'], 2) }}% defect
            </div>
            @endif
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Defect Analysis Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80" id="defect-analysis-chart-container" wire:ignore>
                <canvas id="defect-analysis-chart"></canvas>
            </div>
        </div>

        <!-- Grade vs QT Correlation Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80" id="grade-correlation-chart-container" wire:ignore>
                <canvas id="grade-correlation-chart"></canvas>
            </div>
        </div>

        <!-- Measurement Difference Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80" id="measurement-difference-chart-container" wire:ignore>
                <canvas id="measurement-difference-chart"></canvas>
            </div>
        </div>

        <!-- Quality Distribution Chart -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80" id="quality-distribution-chart-container" wire:ignore>
                <canvas id="quality-distribution-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Material Performance Table -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Performa Material') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-sm text-sm w-full">
                <thead>
                    <tr class="text-xs uppercase text-neutral-500 border-b">
                        <th class="px-4 py-3">{{ __('Material') }}</th>
                        <th class="px-4 py-3">{{ __('Jumlah Kulit') }}</th>
                        <th class="px-4 py-3">{{ __('Tingkat Defect (%)') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($qualityMetrics['material_stats']))
                        @foreach($qualityMetrics['material_stats'] as $material => $stats)
                        <tr class="border-b border-neutral-100 dark:border-neutral-700">
                            <td class="px-4 py-3 font-medium">{{ $material }}</td>
                            <td class="px-4 py-3">{{ number_format($stats['count']) }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ $stats['defect_rate'] > 15 ? 'text-red-500' : ($stats['defect_rate'] > 10 ? 'text-yellow-500' : 'text-green-500') }}">
                                    {{ round($stats['defect_rate'], 2) }}%
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($stats['defect_rate'] <= 10)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                        {{ __('Baik') }}
                                    </span>
                                @elseif($stats['defect_rate'] <= 15)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                        {{ __('Perhatian') }}
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                        {{ __('Buruk') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-text-button type="button" wire:click="showMaterialDetails('{{ $material }}')" 
                                    class="text-xs">{{ __('Detail') }}</x-text-button>
                            </td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-neutral-500">{{ __('Tidak ada data material') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Grade Performance Summary -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Performa Grade') }}</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                @if(isset($qualityMetrics['grade_stats']))
                    @foreach($qualityMetrics['grade_stats'] as $grade => $stats)
                    <div class="text-center p-4 border border-neutral-200 dark:border-neutral-700 rounded-lg">
                        <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $grade }}</div>
                        <div class="text-xs text-neutral-500 uppercase mb-2">{{ __('Grade') }}</div>
                        <div class="text-sm">{{ number_format($stats['count']) }} {{ __('kulit') }}</div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">
                            {{ round($stats['avg_qt_percent'], 1) }}% QT
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <!-- Material Details Modal -->
    <x-modal name="material-details" maxWidth="md">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Detail Material') }}: {{ $materialDetails['name'] ?? '' }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
            </div>

            @if($materialDetails)
            <div class="mt-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('Total Kulit') }}</div>
                        <div class="text-xl font-semibold">{{ number_format($materialDetails['total_hides']) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('Tingkat Defect') }}</div>
                        <div class="text-xl font-semibold {{ $materialDetails['defect_rate'] > 15 ? 'text-red-500' : ($materialDetails['defect_rate'] > 10 ? 'text-yellow-500' : 'text-green-500') }}">
                            {{ round($materialDetails['defect_rate'], 2) }}%
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('Total VN Area') }}</div>
                        <div class="text-lg">{{ number_format($materialDetails['total_vn'], 2) }} SF</div>
                    </div>
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('Total QT Area') }}</div>
                        <div class="text-lg">{{ number_format($materialDetails['total_qt'], 2) }} SF</div>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-neutral-500 mb-3">{{ __('Distribusi Grade') }}</div>
                    <div class="grid grid-cols-5 gap-2">
                        @for($grade = 1; $grade <= 5; $grade++)
                        <div class="text-center p-2 bg-neutral-100 dark:bg-neutral-700 rounded">
                            <div class="text-xs text-neutral-500">{{ __('Grade') }} {{ $grade }}</div>
                            <div class="font-semibold">{{ $materialDetails['grade_breakdown'][$grade] ?? 0 }}</div>
                        </div>
                        @endfor
                    </div>
                </div>
            </div>
            @endif

            <div class="mt-6 flex justify-end">
                <x-primary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Tutup') }}
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript