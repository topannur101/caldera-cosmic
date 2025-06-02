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

    public array $workerStats = [];
    public array $shiftTeamStats = [];
    public array $experienceCorrelation = [];
    public array $improvementTrends = [];
    public array $summaryKpis = [];
    public string $selectedWorker = '';
    public array $workerDetails = [];

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
            ->join('users', 'ins_ldc_hides.user_id', '=', 'users.id')
            ->select(
                'ins_ldc_hides.*',
                'ins_ldc_hides.updated_at as hide_updated_at',
                'ins_ldc_groups.workdate as group_workdate',
                'ins_ldc_groups.style as group_style',
                'ins_ldc_groups.line as group_line',
                'ins_ldc_groups.material as group_material',
                'users.emp_id as user_emp_id',
                'users.name as user_name',
                'users.created_at as user_created_at'
            );

        if (!$this->is_workdate) {
            $query->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $query->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }

        if ($this->line) {
            $query->where('ins_ldc_groups.line', $this->line);
        }

        if ($this->shift) {
            $query->where('ins_ldc_hides.shift', $this->shift);
        }

        return $query;
    }

    private function calculateWorkerExperience($empId, $userCreatedAt, $firstHideDate)
    {
        // Parse emp_id for hire date (e.g., TT1711 = Nov 2017)
        $hireYearMonth = null;
        if (preg_match('/[A-Z]{2}(\d{4})/', $empId, $matches)) {
            $yearMonth = $matches[1];
            $year = 2000 + (int)substr($yearMonth, 0, 2);
            $month = (int)substr($yearMonth, 2, 2);
            if ($month >= 1 && $month <= 12) {
                $hireYearMonth = Carbon::create($year, $month, 1);
            }
        }

        // Calculate experience from both methods (positive values)
        $experienceFromHire = $hireYearMonth ? 
            round($hireYearMonth->diffInMonths(Carbon::now()), 1) : null;
        
        $experienceFromFirstHide = $firstHideDate ? 
            round(Carbon::parse($firstHideDate)->diffInMonths(Carbon::now()), 1) : null;

        return [
            'hire_date' => $hireYearMonth,
            'first_hide_date' => $firstHideDate,
            'experience_from_hire' => $experienceFromHire,
            'experience_from_first_hide' => $experienceFromFirstHide
        ];
    }

    private function calculateWorkerStats()
    {
        $hides = $this->getHidesQuery()->get();
        $workerStats = [];
        $workerFirstHides = [];

        // Get first hide date for each worker
        $firstHides = DB::table('ins_ldc_hides')
            ->join('users', 'ins_ldc_hides.user_id', '=', 'users.id')
            ->select('users.emp_id', 'users.name', 'users.created_at as user_created_at', 
                     DB::raw('MIN(ins_ldc_hides.updated_at) as first_hide_date'))
            ->groupBy('users.emp_id', 'users.name', 'users.created_at')
            ->get()
            ->keyBy('emp_id');

        foreach ($hides as $hide) {
            $empId = $hide->user_emp_id;
            
            if (!isset($workerStats[$empId])) {
                $firstHideInfo = $firstHides[$empId] ?? null;
                $experience = $this->calculateWorkerExperience(
                    $empId, 
                    $hide->user_created_at,
                    $firstHideInfo->first_hide_date ?? null
                );

                $workerStats[$empId] = [
                    'name' => $hide->user_name,
                    'emp_id' => $empId,
                    'total_hides' => 0,
                    'total_days_worked' => 0,
                    'daily_totals' => [],
                    'shift_totals' => [],
                    'qt_measurements' => [],
                    'defect_rates' => [],
                    'experience' => $experience
                ];
            }

            $dateKey = $this->is_workdate ? $hide->group_workdate : date('Y-m-d', strtotime($hide->hide_updated_at));
            $shiftKey = $hide->shift;
            $defectRate = $hide->area_vn > 0 ? (($hide->area_vn - $hide->area_qt) / $hide->area_vn) * 100 : 0;

            $workerStats[$empId]['total_hides']++;
            $workerStats[$empId]['daily_totals'][$dateKey] = ($workerStats[$empId]['daily_totals'][$dateKey] ?? 0) + 1;
            $workerStats[$empId]['shift_totals'][$shiftKey] = ($workerStats[$empId]['shift_totals'][$shiftKey] ?? 0) + 1;
            $workerStats[$empId]['qt_measurements'][] = $hide->area_qt;
            $workerStats[$empId]['defect_rates'][] = $defectRate;
        }

        // Calculate final metrics
        foreach ($workerStats as $empId => &$stats) {
            $stats['total_days_worked'] = count($stats['daily_totals']);
            $stats['avg_hides_per_day'] = $stats['total_days_worked'] > 0 ? 
                round($stats['total_hides'] / $stats['total_days_worked'], 1) : 0;
            
            $stats['avg_qt_measurement'] = count($stats['qt_measurements']) > 0 ? 
                round(array_sum($stats['qt_measurements']) / count($stats['qt_measurements']), 2) : 0;
            
            $stats['qt_consistency'] = count($stats['qt_measurements']) > 1 ? 
                round($this->calculateStandardDeviation($stats['qt_measurements']), 2) : 0;
            
            $stats['avg_defect_rate'] = count($stats['defect_rates']) > 0 ? 
                round(array_sum($stats['defect_rates']) / count($stats['defect_rates']), 2) : 0;
        }

        $this->workerStats = $workerStats;
    }

    private function calculateShiftTeamStats()
    {
        $shiftStats = [];
        
        foreach ($this->workerStats as $empId => $worker) {
            foreach ($worker['shift_totals'] as $shift => $hides) {
                if (!isset($shiftStats[$shift])) {
                    $shiftStats[$shift] = [
                        'total_workers' => 0,
                        'total_hides' => 0,
                        'worker_performances' => []
                    ];
                }
                
                $shiftStats[$shift]['total_workers']++;
                $shiftStats[$shift]['total_hides'] += $hides;
                $shiftStats[$shift]['worker_performances'][] = $worker['avg_hides_per_day'];
            }
        }

        // Calculate team averages
        foreach ($shiftStats as $shift => &$stats) {
            $stats['avg_hides_per_worker'] = $stats['total_workers'] > 0 ? 
                round($stats['total_hides'] / $stats['total_workers'], 1) : 0;
            
            $stats['team_consistency'] = count($stats['worker_performances']) > 1 ? 
                round($this->calculateStandardDeviation($stats['worker_performances']), 2) : 0;
        }

        $this->shiftTeamStats = $shiftStats;
    }

    private function calculateExperienceCorrelation()
    {
        $correlationData = [];
        
        foreach ($this->workerStats as $empId => $worker) {
            if ($worker['experience']['experience_from_hire'] !== null) {
                $correlationData[] = [
                    'emp_id' => $empId,
                    'name' => $worker['name'],
                    'experience_hire' => $worker['experience']['experience_from_hire'],
                    'experience_system' => $worker['experience']['experience_from_first_hide'],
                    'productivity' => $worker['avg_hides_per_day'],
                    'consistency' => $worker['qt_consistency']
                ];
            }
        }

        $this->experienceCorrelation = $correlationData;
    }

    private function calculateImprovementTrends()
    {
        // This would require time-series analysis over weeks/months
        // For now, we'll create a simplified version comparing first vs recent performance
        $trends = [];
        
        foreach ($this->workerStats as $empId => $worker) {
            if ($worker['total_hides'] >= 10) { // Only workers with sufficient data
                // Split data into first half and second half of period
                $totalHides = count($worker['defect_rates']);
                $firstHalf = array_slice($worker['defect_rates'], 0, intval($totalHides / 2));
                $secondHalf = array_slice($worker['defect_rates'], intval($totalHides / 2));
                
                if (count($firstHalf) > 0 && count($secondHalf) > 0) {
                    $firstAvg = array_sum($firstHalf) / count($firstHalf);
                    $secondAvg = array_sum($secondHalf) / count($secondHalf);
                    $improvement = $firstAvg - $secondAvg; // Positive = improvement (lower defect rate)
                    
                    $trends[] = [
                        'emp_id' => $empId,
                        'name' => $worker['name'],
                        'first_period_avg' => round($firstAvg, 2),
                        'second_period_avg' => round($secondAvg, 2),
                        'improvement' => round($improvement, 2),
                        'trend' => $improvement > 1 ? 'improving' : ($improvement < -1 ? 'declining' : 'stable')
                    ];
                }
            }
        }

        $this->improvementTrends = $trends;
    }

    private function calculateSummaryKpis()
    {
        $totalWorkers = count($this->workerStats);
        $totalHides = array_sum(array_column($this->workerStats, 'total_hides'));
        $avgProductivity = $totalWorkers > 0 ? 
            array_sum(array_column($this->workerStats, 'avg_hides_per_day')) / $totalWorkers : 0;
        
        $topPerformer = collect($this->workerStats)->sortByDesc('avg_hides_per_day')->first();
        $mostConsistent = collect($this->workerStats)->sortBy('qt_consistency')->first();

        $this->summaryKpis = [
            'total_workers' => $totalWorkers,
            'total_hides' => $totalHides,
            'avg_productivity' => round($avgProductivity, 1),
            'top_performer' => $topPerformer,
            'most_consistent' => $mostConsistent
        ];
    }

    private function calculateStandardDeviation(array $values)
    {
        if (count($values) < 2) return 0;
        
        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return sqrt(array_sum($squaredDiffs) / (count($values) - 1));
    }

    #[On('update')]
    public function updated()
    {
        $this->calculateWorkerStats();
        $this->calculateShiftTeamStats();
        $this->calculateExperienceCorrelation();
        $this->calculateImprovementTrends();
        $this->calculateSummaryKpis();
        $this->renderCharts();
    }

    private function renderCharts()
    {
        $productivityOptions = InsLdc::getWorkerProductivityChartOptions($this->workerStats);
        $consistencyOptions = InsLdc::getWorkerConsistencyChartOptions($this->workerStats);
        $experienceOptions = InsLdc::getExperienceCorrelationChartOptions($this->experienceCorrelation);
        $improvementOptions = InsLdc::getImprovementTrendChartOptions($this->improvementTrends);
        $teamOptions = InsLdc::getShiftTeamChartOptions($this->shiftTeamStats);

        $this->js("
            (function() {
                // Destroy existing charts
                if (window.productivityChart) window.productivityChart.destroy();
                if (window.consistencyChart) window.consistencyChart.destroy();
                if (window.experienceChart) window.experienceChart.destroy();
                if (window.improvementChart) window.improvementChart.destroy();
                if (window.teamChart) window.teamChart.destroy();

                // Worker Productivity Chart
                const productivityCtx = document.getElementById('productivity-chart');
                if (productivityCtx) {
                    window.productivityChart = new Chart(productivityCtx, " . json_encode($productivityOptions) . ");
                }

                // Worker Consistency Chart
                const consistencyCtx = document.getElementById('consistency-chart');
                if (consistencyCtx) {
                    window.consistencyChart = new Chart(consistencyCtx, " . json_encode($consistencyOptions) . ");
                }

                // Experience Correlation Chart
                const experienceCtx = document.getElementById('experience-chart');
                if (experienceCtx) {
                    window.experienceChart = new Chart(experienceCtx, " . json_encode($experienceOptions) . ");
                }

                // Improvement Trend Chart
                const improvementCtx = document.getElementById('improvement-chart');
                if (improvementCtx) {
                    window.improvementChart = new Chart(improvementCtx, " . json_encode($improvementOptions) . ");
                }

                // Shift Team Chart
                const teamCtx = document.getElementById('team-chart');
                if (teamCtx) {
                    window.teamChart = new Chart(teamCtx, " . json_encode($teamOptions) . ");
                }
            })();
        ");
    }

    public function showWorkerDetails(string $empId)
    {
        $this->selectedWorker = $empId;
        $worker = $this->workerStats[$empId] ?? null;
        
        if ($worker) {
            $this->workerDetails = [
                'name' => $worker['name'],
                'emp_id' => $empId,
                'total_hides' => $worker['total_hides'],
                'total_days_worked' => $worker['total_days_worked'],
                'avg_hides_per_day' => $worker['avg_hides_per_day'],
                'qt_consistency' => $worker['qt_consistency'],
                'experience' => $worker['experience'],
                'shift_breakdown' => $worker['shift_totals']
            ];
            
            $this->js('$dispatch("open-modal", "worker-details")');
        }
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
                        <x-checkbox id="worker_is_workdate" wire:model.live="is_workdate"
                            value="is_workdate"><span class="uppercase text-xs">{{ __('Workdate') }}</span></x-checkbox>
                    </div>
                </div>
            </div>
            <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-6 lg:my-0"></div>
            <div class="flex gap-3">
                <div>
                    <label for="worker-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="worker-line" wire:model.live="line" type="text" />
                </div>
                <div>
                    <label for="worker-shift" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Shift') }}</label>
                    <x-select class="w-full" id="worker-shift" wire:model.live="shift">
                        <option value=""></option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </x-select>
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Total Pekerja') }}</div>
            <div class="text-2xl font-bold">{{ number_format($summaryKpis['total_workers'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Total Kulit') }}</div>
            <div class="text-2xl font-bold">{{ number_format($summaryKpis['total_hides'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Rata-rata Produktivitas') }}</div>
            <div class="text-2xl font-bold">{{ ($summaryKpis['avg_productivity'] ?? 0) }}</div>
            <div class="text-xs text-neutral-500">{{ __('kulit/hari') }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Pekerja Terproduktif') }}</div>
            <div class="text-lg font-bold truncate">{{ $summaryKpis['top_performer']['name'] ?? __('Tidak ada') }}</div>
            <div class="text-xs text-neutral-500">{{ ($summaryKpis['top_performer']['avg_hides_per_day'] ?? 0) }} {{ __('kulit/hari') }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Paling Konsisten') }}</div>
            <div class="text-lg font-bold truncate">{{ $summaryKpis['most_consistent']['name'] ?? __('Tidak ada') }}</div>
            <div class="text-xs text-neutral-500">{{ ($summaryKpis['most_consistent']['qt_consistency'] ?? 0) }} {{ __('konsistensi') }}</div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="space-y-8">
        <!-- 1. Worker Productivity & Team Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div class="h-80">
                    <canvas id="productivity-chart" wire:ignore></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div class="h-80">
                    <canvas id="team-chart" wire:ignore></canvas>
                </div>
            </div>
        </div>

        <!-- 2. Consistency & Experience Analysis -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div class="h-80">
                    <canvas id="consistency-chart" wire:ignore></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div class="h-80">
                    <canvas id="experience-chart" wire:ignore></canvas>
                </div>
            </div>
        </div>

        <!-- 3. Improvement Trends -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div class="h-80">
                    <canvas id="improvement-chart" wire:ignore></canvas>
                </div>
            </div>
            <div class="lg:col-span-2 bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                    <h3 class="text-lg font-medium">{{ __('Tren Peningkatan Pekerja') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-sm text-sm w-full">
                        <thead>
                            <tr class="text-xs uppercase text-neutral-500 border-b">
                                <th class="px-4 py-3">{{ __('Nama') }}</th>
                                <th class="px-4 py-3">{{ __('Periode Awal') }}</th>
                                <th class="px-4 py-3">{{ __('Periode Akhir') }}</th>
                                <th class="px-4 py-3">{{ __('Peningkatan') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($improvementTrends as $trend)
                            <tr class="border-b border-neutral-100 dark:border-neutral-700">
                                <td class="px-4 py-3 font-medium">{{ $trend['name'] }}</td>
                                <td class="px-4 py-3">{{ $trend['first_period_avg'] }}%</td>
                                <td class="px-4 py-3">{{ $trend['second_period_avg'] }}%</td>
                                <td class="px-4 py-3">
                                    <span class="{{ $trend['improvement'] > 0 ? 'text-green-500' : ($trend['improvement'] < 0 ? 'text-red-500' : 'text-neutral-500') }}">
                                        {{ $trend['improvement'] > 0 ? '+' : '' }}{{ $trend['improvement'] }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($trend['trend'] === 'improving')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                            {{ __('Membaik') }}
                                        </span>
                                    @elseif($trend['trend'] === 'declining')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                            {{ __('Menurun') }}
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-100">
                                            {{ __('Stabil') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if(empty($improvementTrends))
                    <div class="text-center py-8 text-neutral-500">
                        {{ __('Tidak ada data peningkatan yang cukup untuk analisis') }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Worker Statistics Table -->
    <div class="mt-8 bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium">{{ __('Statistik Detail Pekerja') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-sm text-sm w-full">
                <thead>
                    <tr class="text-xs uppercase text-neutral-500 border-b">
                        <th class="px-4 py-3">{{ __('Nama') }}</th>
                        <th class="px-4 py-3">{{ __('NIK') }}</th>
                        <th class="px-4 py-3">{{ __('Total Kulit') }}</th>
                        <th class="px-4 py-3">{{ __('Hari Kerja') }}</th>
                        <th class="px-4 py-3">{{ __('Kulit/Hari') }}</th>
                        <th class="px-4 py-3">{{ __('Konsistensi QT') }}</th>
                        <th class="px-4 py-3">{{ __('Pengalaman (Hire)') }}</th>
                        <th class="px-4 py-3">{{ __('Pengalaman (System)') }}</th>
                        <th class="px-4 py-3">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workerStats as $empId => $worker)
                    <tr class="border-b border-neutral-100 dark:border-neutral-700">
                        <td class="px-4 py-3 font-medium">{{ $worker['name'] }}</td>
                        <td class="px-4 py-3 font-mono">{{ $worker['emp_id'] }}</td>
                        <td class="px-4 py-3">{{ number_format($worker['total_hides']) }}</td>
                        <td class="px-4 py-3">{{ number_format($worker['total_days_worked']) }}</td>
                        <td class="px-4 py-3">{{ $worker['avg_hides_per_day'] }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $worker['qt_consistency'] < 1.0 ? 'text-green-500' : ($worker['qt_consistency'] < 2.0 ? 'text-yellow-500' : 'text-red-500') }}">
                                {{ $worker['qt_consistency'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            {{ $worker['experience']['experience_from_hire'] ? $worker['experience']['experience_from_hire'] . ' bulan' : __('N/A') }}
                        </td>
                        <td class="px-4 py-3">
                            {{ $worker['experience']['experience_from_first_hide'] ? $worker['experience']['experience_from_first_hide'] . ' bulan' : __('N/A') }}
                        </td>
                        <td class="px-4 py-3">
                            <x-text-button type="button" wire:click="showWorkerDetails('{{ $empId }}')" 
                                class="text-xs">{{ __('Detail') }}</x-text-button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if(empty($workerStats))
            <div class="text-center py-12">
                <div class="text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="icon-users"></i>
                </div>
                <div class="text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada data pekerja untuk periode yang dipilih') }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Worker Details Modal -->
    <x-modal name="worker-details" maxWidth="md">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Detail Pekerja') }}: {{ $workerDetails['name'] ?? '' }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
            </div>

            @if($workerDetails)
            <div class="mt-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('NIK') }}</div>
                        <div class="text-lg font-mono">{{ $workerDetails['emp_id'] }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('Total Kulit') }}</div>
                        <div class="text-lg">{{ number_format($workerDetails['total_hides']) }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('Hari Kerja') }}</div>
                        <div class="text-lg">{{ number_format($workerDetails['total_days_worked']) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('Rata-rata per Hari') }}</div>
                        <div class="text-lg">{{ $workerDetails['avg_hides_per_day'] }}</div>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-neutral-500">{{ __('Konsistensi QT') }}</div>
                    <div class="text-lg {{ $workerDetails['qt_consistency'] < 1.0 ? 'text-green-500' : ($workerDetails['qt_consistency'] < 2.0 ? 'text-yellow-500' : 'text-red-500') }}">
                        {{ $workerDetails['qt_consistency'] }}
                        <span class="text-sm text-neutral-500">({{ __('semakin rendah semakin baik') }})</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('Pengalaman (Hire Date)') }}</div>
                        <div class="text-lg">
                            {{ $workerDetails['experience']['experience_from_hire'] ? $workerDetails['experience']['experience_from_hire'] . ' bulan' : __('N/A') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-neutral-500">{{ __('Pengalaman (System)') }}</div>
                        <div class="text-lg">
                            {{ $workerDetails['experience']['experience_from_first_hide'] ? $workerDetails['experience']['experience_from_first_hide'] . ' bulan' : __('N/A') }}
                        </div>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-neutral-500 mb-3">{{ __('Distribusi Shift') }}</div>
                    <div class="grid grid-cols-3 gap-2">
                        @for($shift = 1; $shift <= 3; $shift++)
                        <div class="text-center p-2 bg-neutral-100 dark:bg-neutral-700 rounded">
                            <div class="text-xs text-neutral-500">{{ __('Shift') }} {{ $shift }}</div>
                            <div class="font-semibold">{{ $workerDetails['shift_breakdown'][$shift] ?? 0 }}</div>
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