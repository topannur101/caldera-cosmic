<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsStc;
use App\Models\InsStcDSum;
use App\Models\InsStcMachine;
use App\Models\User;
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
    public $line;

    #[Url]
    public string $position = '';

    #[Url]
    public int $min_measurements = 5;

    #[Url] 
    public int $cycle_gap_hours = 4; // Configurable gap between cycle 1 and 2

    public array $lines = [];
    public array $operator_stats = [];
    public array $benchmarks = [];

    // Shift configuration
    private int $shift_duration_hours = 8;
    private int $shift_start_hour = 6; // 6 AM

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisMonth();
        }

        $this->lines = InsStcMachine::orderBy('line')->get()->pluck('line')->toArray();
    }

    #[On('update')]
    public function update()
    {
        $this->calculateOperatorPerformance();
        $this->renderCharts();
    }

    private function calculateOperatorPerformance()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        // Get all machines to calculate expected measurements
        $totalLines = InsStcMachine::count();
        $expectedMeasurementsPerShift = $totalLines * 4; // 2 cycles × 2 positions × all lines

        // Get d_sums with their related data
        $query = InsStcDSum::with(['user', 'ins_stc_machine'])
            ->whereBetween('created_at', [$start, $end]);

        if ($this->line) {
            $query->whereHas('ins_stc_machine', function (Builder $q) {
                $q->where('line', $this->line);
            });
        }

        if ($this->position) {
            $query->where('position', $this->position);
        }

        $dSums = $query->get();

        // Group by operator and shift
        $operatorData = [];
        $allLatencies = [];

        foreach ($dSums as $dSum) {
            $userId = $dSum->user_id;
            $userName = $dSum->user->name ?? 'Unknown';
            $userEmpId = $dSum->user->emp_id ?? 'N/A';

            // Calculate upload latency (ended_at to created_at)
            $latencyMinutes = 0;
            if ($dSum->ended_at && $dSum->created_at) {
                $latencyMinutes = Carbon::parse($dSum->ended_at)
                    ->diffInMinutes(Carbon::parse($dSum->created_at));
            }

            // Determine shift based on created_at
            $measurementTime = Carbon::parse($dSum->created_at);
            $shiftDate = $this->getShiftDate($measurementTime);
            $shiftKey = $shiftDate . '_' . $userId;

            if (!isset($operatorData[$userId])) {
                $operatorData[$userId] = [
                    'user_id' => $userId,
                    'name' => $userName,
                    'emp_id' => $userEmpId,
                    'shifts' => [],
                    'total_measurements' => 0,
                    'total_latency' => 0,
                    'latencies' => [],
                ];
            }

            if (!isset($operatorData[$userId]['shifts'][$shiftKey])) {
                $operatorData[$userId]['shifts'][$shiftKey] = [
                    'date' => $shiftDate,
                    'measurements' => [],
                    'lines_covered' => [],
                    'positions_covered' => [],
                ];
            }

            // Add measurement to shift data
            $operatorData[$userId]['shifts'][$shiftKey]['measurements'][] = [
                'line' => $dSum->ins_stc_machine->line,
                'position' => $dSum->position,
                'sequence' => $dSum->sequence,
                'created_at' => $dSum->created_at,
                'latency' => $latencyMinutes,
            ];

            $operatorData[$userId]['shifts'][$shiftKey]['lines_covered'][] = $dSum->ins_stc_machine->line;
            $operatorData[$userId]['shifts'][$shiftKey]['positions_covered'][] = $dSum->position;
            
            $operatorData[$userId]['total_measurements']++;
            $operatorData[$userId]['total_latency'] += $latencyMinutes;
            $operatorData[$userId]['latencies'][] = $latencyMinutes;
            $allLatencies[] = $latencyMinutes;
        }

        // Calculate performance metrics for each operator
        $this->operator_stats = [];

        foreach ($operatorData as $data) {
            if ($data['total_measurements'] >= $this->min_measurements) {
                
                // Upload latency metrics
                $avgLatency = $data['total_measurements'] > 0 
                    ? $data['total_latency'] / $data['total_measurements'] 
                    : 0;

                $latencyScore = $this->calculateLatencyScore($avgLatency);

                // Compliance metrics
                $complianceMetrics = $this->calculateComplianceMetrics($data['shifts'], $expectedMeasurementsPerShift);
                
                // Time distribution metrics
                $distributionScore = $this->calculateTimeDistributionScore($data['shifts']);

                // Sequence compliance
                $sequenceScore = $this->calculateSequenceCompliance($data['shifts']);

                // Overall efficiency score (weighted average)
                $efficiencyScore = round(
                    ($latencyScore * 0.3) + 
                    ($complianceMetrics['compliance_score'] * 0.3) + 
                    ($distributionScore * 0.2) + 
                    ($sequenceScore * 0.2), 1
                );

                $this->operator_stats[] = [
                    'user_id' => $data['user_id'],
                    'name' => $data['name'],
                    'emp_id' => $data['emp_id'],
                    'total_measurements' => $data['total_measurements'],
                    'shifts_worked' => count($data['shifts']),
                    'avg_latency_minutes' => round($avgLatency, 1),
                    'latency_score' => $latencyScore,
                    'expected_measurements' => $complianceMetrics['expected'],
                    'actual_measurements' => $complianceMetrics['actual'],
                    'missing_measurements' => $complianceMetrics['missing'],
                    'excess_measurements' => $complianceMetrics['excess'],
                    'compliance_score' => $complianceMetrics['compliance_score'],
                    'distribution_score' => $distributionScore,
                    'sequence_score' => $sequenceScore,
                    'efficiency_score' => $efficiencyScore,
                ];
            }
        }

        // Sort by efficiency score (highest first)
        usort($this->operator_stats, function($a, $b) {
            return $b['efficiency_score'] <=> $a['efficiency_score'];
        });

        // Calculate benchmarks
        if (!empty($this->operator_stats)) {
            $efficiencyScores = array_column($this->operator_stats, 'efficiency_score');
            $latencies = array_column($this->operator_stats, 'avg_latency_minutes');
            $complianceScores = array_column($this->operator_stats, 'compliance_score');

            $this->benchmarks = [
                'avg_efficiency' => round(array_sum($efficiencyScores) / count($efficiencyScores), 1),
                'best_efficiency' => round(max($efficiencyScores), 1),
                'avg_latency' => round(array_sum($latencies) / count($latencies), 1),
                'best_latency' => round(min($latencies), 1),
                'avg_compliance' => round(array_sum($complianceScores) / count($complianceScores), 1),
                'total_operators' => count($this->operator_stats),
            ];
        }
    }

    private function getShiftDate(Carbon $dateTime): string
    {
        // If measurement is before 6 AM, it belongs to previous day's shift
        if ($dateTime->hour < $this->shift_start_hour) {
            return $dateTime->subDay()->format('Y-m-d');
        }
        return $dateTime->format('Y-m-d');
    }

    private function calculateLatencyScore(float $avgLatency): int
    {
        if ($avgLatency < 5) return 100; // Excellent
        if ($avgLatency <= 15) return 80; // Good  
        if ($avgLatency <= 30) return 60; // Average
        return 40; // Poor
    }

    private function calculateComplianceMetrics(array $shifts, int $expectedPerShift): array
    {
        $totalExpected = count($shifts) * $expectedPerShift;
        $totalActual = 0;
        
        foreach ($shifts as $shift) {
            $totalActual += count($shift['measurements']);
        }

        $missing = max(0, $totalExpected - $totalActual);
        $excess = max(0, $totalActual - $totalExpected);
        
        $complianceScore = $totalExpected > 0 
            ? min(100, round(($totalActual / $totalExpected) * 100))
            : 0;

        return [
            'expected' => $totalExpected,
            'actual' => $totalActual,
            'missing' => $missing,
            'excess' => $excess,
            'compliance_score' => $complianceScore,
        ];
    }

    private function calculateTimeDistributionScore(array $shifts): int
    {
        $totalScore = 0;
        $validShifts = 0;

        foreach ($shifts as $shift) {
            if (count($shift['measurements']) < 2) continue;

            $measurements = collect($shift['measurements'])
                ->sortBy('created_at')
                ->values()
                ->toArray();

            // Calculate time gaps between measurements
            $gaps = [];
            for ($i = 1; $i < count($measurements); $i++) {
                $prevTime = Carbon::parse($measurements[$i-1]['created_at']);
                $currTime = Carbon::parse($measurements[$i]['created_at']);
                $gaps[] = $prevTime->diffInMinutes($currTime);
            }

            // Score based on distribution evenness
            if (!empty($gaps)) {
                $avgGap = array_sum($gaps) / count($gaps);
                $idealGap = (8 * 60) / count($measurements); // 8 hours / number of measurements
                
                $deviation = abs($avgGap - $idealGap) / $idealGap;
                $shiftScore = max(0, 100 - ($deviation * 100));
                
                $totalScore += $shiftScore;
                $validShifts++;
            }
        }

        return $validShifts > 0 ? round($totalScore / $validShifts) : 0;
    }

    private function calculateSequenceCompliance(array $shifts): int
    {
        $totalCompliant = 0;
        $totalChecked = 0;

        foreach ($shifts as $shift) {
            $measurementsByLine = [];
            
            // Group measurements by line
            foreach ($shift['measurements'] as $measurement) {
                $line = $measurement['line'];
                if (!isset($measurementsByLine[$line])) {
                    $measurementsByLine[$line] = [];
                }
                $measurementsByLine[$line][] = $measurement;
            }

            // Check sequence compliance for each line
            foreach ($measurementsByLine as $lineMeasurements) {
                if (count($lineMeasurements) >= 2) {
                    usort($lineMeasurements, function($a, $b) {
                        return $a['created_at'] <=> $b['created_at'];
                    });

                    // Check if sequence goes 1 -> 2 and timing is appropriate
                    for ($i = 1; $i < count($lineMeasurements); $i++) {
                        $prev = $lineMeasurements[$i-1];
                        $curr = $lineMeasurements[$i];
                        
                        if ($prev['sequence'] == '1' && $curr['sequence'] == '2') {
                            $timeDiff = Carbon::parse($prev['created_at'])
                                ->diffInHours(Carbon::parse($curr['created_at']));
                            
                            if ($timeDiff >= ($this->cycle_gap_hours - 1) && $timeDiff <= ($this->cycle_gap_hours + 1)) {
                                $totalCompliant++;
                            }
                            $totalChecked++;
                        }
                    }
                }
            }
        }

        return $totalChecked > 0 ? round(($totalCompliant / $totalChecked) * 100) : 0;
    }

    private function renderCharts()
    {
        // Always render charts, even with empty data
        $chartData = [
            'labels' => [],
            'efficiency' => [],
            'latency' => [],
            'compliance' => [],
        ];

        if (!empty($this->operator_stats)) {
            $chartData = [
                'labels' => array_map(function($stat) {
                    return $stat['emp_id'] . ' - ' . $stat['name'];
                }, $this->operator_stats),
                'efficiency' => array_column($this->operator_stats, 'efficiency_score'),
                'latency' => array_column($this->operator_stats, 'avg_latency_minutes'),
                'compliance' => array_column($this->operator_stats, 'compliance_score'),
            ];
        }

        $this->js("
            (function() {
                // Efficiency Score Chart
                const efficiencyOptions = {
                    type: 'bar',
                    data: {
                        labels: " . json_encode(array_slice($chartData['labels'], 0, 15)) . ",
                        datasets: [
                            {
                                label: '" . __('Skor Efisiensi') . "',
                                data: " . json_encode(array_slice($chartData['efficiency'], 0, 15)) . ",
                                backgroundColor: function(context) {
                                    const value = context.parsed.y;
                                    if (value >= 90) return 'rgba(34, 197, 94, 0.8)';
                                    if (value >= 75) return 'rgba(101, 163, 13, 0.8)';
                                    if (value >= 60) return 'rgba(234, 179, 8, 0.8)';
                                    return 'rgba(239, 68, 68, 0.8)';
                                },
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: '" . __('Skor Efisiensi Operator (Top 15)') . "'
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: '" . __('Skor') . "'
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            }
                        }
                    }
                };

                const efficiencyContainer = \$wire.\$el.querySelector('#operator-efficiency-chart');
                if (efficiencyContainer) {
                    efficiencyContainer.innerHTML = '';
                    const efficiencyCanvas = document.createElement('canvas');
                    efficiencyContainer.appendChild(efficiencyCanvas);
                    new Chart(efficiencyCanvas, efficiencyOptions);
                }

                // Upload Latency Chart
                const latencyOptions = {
                    type: 'bar',
                    data: {
                        labels: " . json_encode(array_slice($chartData['labels'], 0, 15)) . ",
                        datasets: [
                            {
                                label: '" . __('Latensi Upload (menit)') . "',
                                data: " . json_encode(array_slice($chartData['latency'], 0, 15)) . ",
                                backgroundColor: function(context) {
                                    const value = context.parsed.y;
                                    if (value < 5) return 'rgba(34, 197, 94, 0.8)';
                                    if (value <= 15) return 'rgba(101, 163, 13, 0.8)';
                                    if (value <= 30) return 'rgba(234, 179, 8, 0.8)';
                                    return 'rgba(239, 68, 68, 0.8)';
                                },
                                borderColor: 'rgba(239, 68, 68, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: '" . __('Latensi Upload Rata-rata') . "'
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: '" . __('Menit') . "'
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            }
                        }
                    }
                };

                const latencyContainer = \$wire.\$el.querySelector('#operator-latency-chart');
                if (latencyContainer) {
                    latencyContainer.innerHTML = '';
                    const latencyCanvas = document.createElement('canvas');
                    latencyContainer.appendChild(latencyCanvas);
                    new Chart(latencyCanvas, latencyOptions);
                }
            })();
        ");
    }

    public function updated()
    {
        $this->update();
    }

    public function getPerformanceClass($score): string
    {
        if ($score >= 90) return 'text-green-600 font-semibold';
        if ($score >= 75) return 'text-lime-600 font-medium';
        if ($score >= 60) return 'text-yellow-600';
        return 'text-red-600 font-medium';
    }

    public function getLatencyClass($latency): string
    {
        if ($latency < 5) return 'text-green-600 font-semibold';
        if ($latency <= 15) return 'text-lime-600 font-medium';
        if ($latency <= 30) return 'text-yellow-600';
        return 'text-red-600 font-medium';
    }

    public function getRankIcon($index): string
    {
        switch ($index) {
            case 0: return '🥇';
            case 1: return '🥈';
            case 2: return '🥉';
            default: return '';
        }
    }

    public function getAlertLevel($stats): string
    {
        // Management attention thresholds
        if ($stats['efficiency_score'] < 60) return 'critical';
        if ($stats['avg_latency_minutes'] > 30) return 'critical';
        if ($stats['missing_measurements'] > 10) return 'warning';
        if ($stats['efficiency_score'] < 75) return 'warning';
        return 'good';
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
            <div class="grid grid-cols-2 lg:flex gap-3">
                <div>
                    <label for="device-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-select class="w-full lg:w-auto" id="device-line" wire:model.live="line">
                        <option value=""></option>
                        @foreach($lines as $line)
                        <option value="{{ $line }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label for="device-position"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                    <x-select class="w-full lg:w-auto" id="device-position" wire:model.live="position">
                        <option value=""></option>
                        <option value="upper">{{ __('Atas') }}</option>
                        <option value="lower">{{ __('Bawah') }}</option>
                    </x-select>
                </div>
                <div>
                    <label for="min-measurements"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Min. Pengukuran') }}</label>
                    <x-text-input class="w-full lg:w-24" id="min-measurements" wire:model.live="min_measurements" type="number" min="1" step="1"></x-text-input>
                </div>
                <div>
                    <label for="cycle-gap-hours"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Gap Siklus (jam)') }}</label>
                    <x-text-input class="w-full lg:w-24" id="cycle-gap-hours" wire:model.live="cycle_gap_hours" type="number" min="1" max="6" step="1"></x-text-input>
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

    <!-- Benchmarks Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Rata-rata Efisiensi') }}</div>
            <div class="text-2xl font-bold text-blue-600">{{ $benchmarks['avg_efficiency'] ?? '0' }}</div>
            <div class="text-xs text-neutral-500">{{ __('Target: ≥90') }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Efisiensi Terbaik') }}</div>
            <div class="text-2xl font-bold text-green-600">{{ $benchmarks['best_efficiency'] ?? '0' }}</div>
            <div class="text-xs text-neutral-500">{{ __('Operator terbaik') }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Rata-rata Latensi') }}</div>
            <div class="text-2xl font-bold text-orange-600">{{ $benchmarks['avg_latency'] ?? '0' }} min</div>
            <div class="text-xs text-neutral-500">{{ __('Target: <5 menit') }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __('Total Operator') }}</div>
            <div class="text-2xl font-bold text-purple-600">{{ $benchmarks['total_operators'] ?? '0' }}</div>
            <div class="text-xs text-neutral-500">{{ __('Min. ') . $min_measurements . __(' pengukuran') }}</div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div id="operator-efficiency-chart" class="h-80" wire:ignore></div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div id="operator-latency-chart" class="h-80" wire:ignore></div>
        </div>
    </div>

    <!-- Detailed Performance Table -->
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Peringkat Performa Operator') }}
            </h3>
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Diurutkan berdasarkan skor efisiensi keseluruhan') }}
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                <thead class="bg-neutral-50 dark:bg-neutral-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                            {{ __('Peringkat') }}
                        </th>
                        <th colspan="3" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                            {{ __('Operator') }}
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                            {{ __('Latensi Upload') }}
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                            {{ __('Kepatuhan') }}
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                            {{ __('Missing/Excess') }}
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                            {{ __('Status') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($operator_stats as $index => $stat)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700 {{ $this->getAlertLevel($stat) == 'critical' ? 'bg-red-50 dark:bg-red-900/20' : ($this->getAlertLevel($stat) == 'warning' ? 'bg-yellow-50 dark:bg-yellow-900/20' : '') }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-neutral-900 dark:text-neutral-100">
                            <div class="flex items-center">
                                <span class="text-lg mr-2">{{ $this->getRankIcon($index) }}</span>
                                <span class="text-lg font-bold {{ $index < 3 ? 'text-yellow-600' : 'text-neutral-600' }}">
                                    #{{ $index + 1 }}
                                </span>
                                @if($this->getAlertLevel($stat) == 'critical')
                                    <i class="icon-alert-triangle text-red-500 ml-2" title="{{ __('Perlu perhatian segera') }}"></i>
                                @elseif($this->getAlertLevel($stat) == 'warning')
                                    <i class="icon-alert-circle text-yellow-500 ml-2" title="{{ __('Perlu perhatian') }}"></i>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden mr-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000">
                                        <path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ $stat['name'] }}
                                    </div>
                                    <div class="text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ $stat['emp_id'] }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-neutral-900 dark:text-neutral-100">
                            <div class="font-medium">{{ $stat['shifts_worked'] }}</div>
                            <div class="text-xs text-neutral-500">{{ $stat['total_measurements'] }} {{ __('pengukuran') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="text-lg font-bold {{ $this->getPerformanceClass($stat['efficiency_score']) }}">
                                {{ $stat['efficiency_score'] }}
                            </div>
                            <div class="text-xs text-neutral-500">{{ __('/100') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="text-sm font-medium {{ $this->getLatencyClass($stat['avg_latency_minutes']) }}">
                                {{ $stat['avg_latency_minutes'] }} {{ __('min') }}
                            </div>
                            <div class="text-xs text-neutral-500">{{ __('rata-rata') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="text-sm font-medium {{ $this->getPerformanceClass($stat['compliance_score']) }}">
                                {{ $stat['compliance_score'] }}%
                            </div>
                            <div class="text-xs text-neutral-500">
                                {{ $stat['actual_measurements'] }}/{{ $stat['expected_measurements'] }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                            @if($stat['missing_measurements'] > 0)
                                <div class="text-red-600 font-medium">
                                    -{{ $stat['missing_measurements'] }} {{ __('kurang') }}
                                </div>
                            @endif
                            @if($stat['excess_measurements'] > 0)
                                <div class="text-blue-600">
                                    +{{ $stat['excess_measurements'] }} {{ __('lebih') }}
                                </div>
                            @endif
                            @if($stat['missing_measurements'] == 0 && $stat['excess_measurements'] == 0)
                                <div class="text-green-600">{{ __('Sesuai') }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($stat['efficiency_score'] >= 90)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                    {{ __('Excellent') }}
                                </span>
                            @elseif($stat['efficiency_score'] >= 75)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-lime-100 text-lime-800 dark:bg-lime-800 dark:text-lime-100">
                                    {{ __('Good') }}
                                </span>
                            @elseif($stat['efficiency_score'] >= 60)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                    {{ __('Average') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                    {{ __('Needs Improvement') }}
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-20 text-center">
                            <div class="text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                                <i class="icon-users"></i>
                            </div>
                            <div class="text-neutral-500 dark:text-neutral-600">
                                {{ __('Tidak ada data operator untuk periode ini') }}
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Performance Breakdown Cards for Top 3 -->
    @if(count($operator_stats) >= 3)
    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(array_slice($operator_stats, 0, 3) as $index => $stat)
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 border-l-4 {{ $index == 0 ? 'border-yellow-400' : ($index == 1 ? 'border-gray-400' : 'border-yellow-600') }}">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-medium text-neutral-900 dark:text-neutral-100">
                    {{ $this->getRankIcon($index) }} {{ $stat['name'] }}
                </h4>
                <span class="text-2xl font-bold {{ $this->getPerformanceClass($stat['efficiency_score']) }}">
                    {{ $stat['efficiency_score'] }}
                </span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Latensi Upload') }}:</span>
                    <span class="{{ $this->getLatencyClass($stat['avg_latency_minutes']) }}">{{ $stat['avg_latency_minutes'] }} min</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Kepatuhan') }}:</span>
                    <span class="{{ $this->getPerformanceClass($stat['compliance_score']) }}">{{ $stat['compliance_score'] }}%</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Distribusi Waktu') }}:</span>
                    <span class="{{ $this->getPerformanceClass($stat['distribution_score']) }}">{{ $stat['distribution_score'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Urutan Siklus') }}:</span>
                    <span class="{{ $this->getPerformanceClass($stat['sequence_score']) }}">{{ $stat['sequence_score'] }}%</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Metrics Legend -->
    <div class="mt-6 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
        <h4 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">
            {{ __('Keterangan Metrik Performa') }}
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Skor Efisiensi') }}</dt>
                <dd class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">
                    {{ __('Gabungan dari latensi upload (30%), kepatuhan (30%), distribusi waktu (20%), dan urutan siklus (20%)') }}
                </dd>
                
                <dt class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Latensi Upload') }}</dt>
                <dd class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">
                    {{ __('Waktu antara selesai pengukuran dan upload ke sistem') }}
                </dd>

                <dt class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Kepatuhan') }}</dt>
                <dd class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Ketepatan jumlah pengukuran (4 per line per shift: 2 siklus × 2 posisi)') }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Distribusi Waktu') }}</dt>
                <dd class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">
                    {{ __('Seberapa merata pengukuran tersebar dalam 8 jam shift') }}
                </dd>

                <dt class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Urutan Siklus') }}</dt>
                <dd class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">
                    {{ __('Ketepatan urutan siklus 1→2 dengan jarak ') . $cycle_gap_hours . __(' jam') }}
                </dd>

                <dt class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Alert Levels') }}</dt>
                <dd class="text-sm text-neutral-600 dark:text-neutral-400">
                    <i class="icon-alert-triangle text-red-500 mr-1"></i>{{ __('Critical: Efisiensi <60 atau latensi >30 min') }}<br>
                    <i class="icon-alert-circle text-yellow-500 mr-1"></i>{{ __('Warning: Efisiensi <75 atau missing >10') }}
                </dd>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div class="flex items-center">
                <div class="w-3 h-3 bg-green-500 rounded mr-2"></div>
                <span>{{ __('Excellent: ≥90 efisiensi') }}</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-lime-500 rounded mr-2"></div>
                <span>{{ __('Good: 75-89 efisiensi') }}</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-yellow-500 rounded mr-2"></div>
                <span>{{ __('Average: 60-74 efisiensi') }}</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-red-500 rounded mr-2"></div>
                <span>{{ __('Needs Improvement: <60 efisiensi') }}</span>
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript