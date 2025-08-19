<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Carbon\Carbon;
use App\Models\InsRdcTest;
use App\Models\InsRdcMachine;
use App\Traits\HasDateRangeFilter;
use Illuminate\Support\Facades\DB;

new class extends Component {

    use HasDateRangeFilter;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public $type;


    public array $machine_stats = [];
    public array $overall_stats = [];
    public array $machine_types = [];

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisMonth();
        }
    }

    private function calculateMachineStats()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsRdcTest::join('ins_rubber_batches', 'ins_rdc_tests.ins_rubber_batch_id', '=', 'ins_rubber_batches.id')
            ->join('ins_rdc_machines', 'ins_rdc_tests.ins_rdc_machine_id', '=', 'ins_rdc_machines.id')
            ->join('users', 'ins_rdc_tests.user_id', '=', 'users.id')
            ->whereBetween('ins_rdc_tests.created_at', [$start, $end])
            ->whereNotNull('ins_rdc_tests.eval');

        if ($this->type) {
            $query->where('ins_rdc_machines.type', $this->type);
        }

        $tests = $query->select(
            'ins_rdc_tests.*',
            'ins_rdc_machines.name as machine_name',
            'ins_rdc_machines.type as machine_type',
            'ins_rdc_machines.number as machine_number',
            'ins_rubber_batches.mcs',
            'users.name as operator_name'
        )->get();

        $machineData = [];
        $totalTests = 0;
        $totalPass = 0;
        $totalFail = 0;
        $totalMachines = 0;

        // Get all active machines for utilization calculation
        $allMachines = InsRdcMachine::where('is_active', true)->get();
        foreach ($allMachines as $machine) {
            $machineData[$machine->id] = [
                'name' => $machine->name,
                'type' => $machine->type,
                'number' => $machine->number,
                'total_tests' => 0,
                'pass_count' => 0,
                'fail_count' => 0,
                'tc10_values' => [],
                'tc50_values' => [],
                'tc90_values' => [],
                'unique_mcs' => [],
                'operators_used' => [],
                'test_dates' => [],
                'test_hours' => []
            ];
        }

        foreach ($tests as $test) {
            $machineId = $test->ins_rdc_machine_id;
            
            if (!isset($machineData[$machineId])) continue;

            $machineData[$machineId]['total_tests']++;
            $totalTests++;

            if ($test->eval === 'pass') {
                $machineData[$machineId]['pass_count']++;
                $totalPass++;
            } else {
                $machineData[$machineId]['fail_count']++;
                $totalFail++;
            }

            // Collect TC values for consistency analysis
            if ($test->tc10) $machineData[$machineId]['tc10_values'][] = (float) $test->tc10;
            if ($test->tc50) $machineData[$machineId]['tc50_values'][] = (float) $test->tc50;
            if ($test->tc90) $machineData[$machineId]['tc90_values'][] = (float) $test->tc90;

            // Track unique MCS and operators
            if ($test->mcs && !in_array($test->mcs, $machineData[$machineId]['unique_mcs'])) {
                $machineData[$machineId]['unique_mcs'][] = $test->mcs;
            }
            if (!in_array($test->operator_name, $machineData[$machineId]['operators_used'])) {
                $machineData[$machineId]['operators_used'][] = $test->operator_name;
            }

            $machineData[$machineId]['test_dates'][] = $test->created_at;
            $machineData[$machineId]['test_hours'][] = Carbon::parse($test->created_at)->hour;
        }

        // Calculate derived metrics for each machine
        $this->machine_stats = [];
        foreach ($machineData as $machineId => $data) {
            if ($data['total_tests'] > 0) {
                $passRate = $data['total_tests'] > 0 ? ($data['pass_count'] / $data['total_tests']) * 100 : 0;
                
                // Calculate utilization metrics
                $testDates = array_map(function($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                }, $data['test_dates']);
                $uniqueDays = count(array_unique($testDates));
                $testsPerDay = $uniqueDays > 0 ? $data['total_tests'] / $uniqueDays : 0;
                
                // Calculate working days in period
                $workingDays = $this->calculateWorkingDays($start, $end);
                $utilizationRate = $workingDays > 0 ? ($uniqueDays / $workingDays) * 100 : 0;

                $this->machine_stats[] = [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'number' => $data['number'],
                    'total_tests' => $data['total_tests'],
                    'pass_count' => $data['pass_count'],
                    'fail_count' => $data['fail_count'],
                    'pass_rate' => round($passRate, 1),
                    'tests_per_day' => round($testsPerDay, 1),
                    'utilization_rate' => round($utilizationRate, 1),
                    'unique_mcs_count' => count($data['unique_mcs']),
                    'operators_count' => count($data['operators_used'])
                ];
                $totalMachines++;
            }
        }

        // Sort by total tests (test volume)
        usort($this->machine_stats, function($a, $b) {
            return $b['total_tests'] <=> $a['total_tests'];
        });

        // Calculate overall statistics
        $overallPassRate = $totalTests > 0 ? ($totalPass / $totalTests) * 100 : 0;
        $avgUtilization = count($this->machine_stats) > 0 ? 
            array_sum(array_column($this->machine_stats, 'utilization_rate')) / count($this->machine_stats) : 0;
        
        $this->overall_stats = [
            'active_machines' => $totalMachines,
            'total_tests' => $totalTests,
            'total_pass' => $totalPass,
            'total_fail' => $totalFail,
            'overall_pass_rate' => round($overallPassRate, 1),
            'overall_fail_rate' => round(100 - $overallPassRate, 1),
            'avg_utilization' => round($avgUtilization, 1),
            'avg_tests_per_machine' => $totalMachines > 0 ? round($totalTests / $totalMachines, 1) : 0
        ];
    }

    private function calculateWorkingDays(Carbon $start, Carbon $end): int
    {
        $workingDays = 0;
        $current = $start->copy();
        
        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $workingDays++;
            }
            $current->addDay();
        }
        
        return $workingDays;
    }


    public function with(): array
    {
        $this->calculateMachineStats();
        
        $this->machine_types = InsRdcMachine::select('type')
            ->where('is_active', true)
            ->distinct()
            ->pluck('type')
            ->toArray();

        return [
            'machine_stats' => $this->machine_stats,
            'overall_stats' => $this->overall_stats,
            'machine_types' => $this->machine_types
        ];
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
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex justify-between gap-x-2 items-center">
                <div class="flex gap-3 text-sm">
                    <div class="flex flex-col justify-around">
                        <table>
                            <tr>
                                <td class="text-neutral-500">{{ __('Total uji') . ': ' }}</td>
                                <td>{{ $overall_stats['total_tests'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500">{{ __('Pass') . ': ' }}</td>
                                <td class="text-green-600 dark:text-green-400">{{ ($overall_stats['total_pass'] ?? 0) . ' (' . ($overall_stats['overall_pass_rate'] ?? 0) . '%)' }}</td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500">{{ __('Fail') . ': ' }}</td>
                                <td class="text-red-600 dark:text-red-400">{{ ($overall_stats['total_fail'] ?? 0) . ' (' . ($overall_stats['overall_fail_rate'] ?? 0) . '%)' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <table>
                            <tr>
                                <td class="text-neutral-500">{{ __('Rata-rata utilisasi') . ': ' }}</td>
                                <td>{{ ($overall_stats['avg_utilization'] ?? 0) . '%' }}</td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500">{{ __('Uji per mesin') . ': ' }}</td>
                                <td>{{ $overall_stats['avg_tests_per_machine'] ?? 0 }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-between gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div>{{ count($machine_stats) . ' mesin' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (!count($machine_stats))
        <div class="py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="icon-cpu"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada data mesin untuk periode ini') }}
            </div>
        </div>
    @else
        <div class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
                <table class="table table-sm text-sm text-neutral-600 dark:text-neutral-400 w-full">
                    <thead>
                        <tr class="uppercase text-xs border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900">
                            <th class="text-left px-4 py-3">{{ __('Mesin') }}</th>
                            <th class="text-center px-2 py-3">{{ __('Tipe') }}</th>
                            <th class="text-center px-2 py-3">{{ __('Total Uji') }}</th>
                            <th class="text-center px-2 py-3">{{ __('Pass') }}</th>
                            <th class="text-center px-2 py-3">{{ __('Fail') }}</th>
                            <th class="text-center px-2 py-3">{{ __('Pass Rate') }}</th>
                            <th class="text-center px-2 py-3">{{ __('Uji/Hari') }}</th>
                            <th class="text-center px-2 py-3">{{ __('Utilisasi') }}</th>
                            <th class="text-center px-2 py-3">{{ __('MCS') }}</th>
                            <th class="text-center px-2 py-3">{{ __('Operator') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($machine_stats as $index => $machine)
                        <tr class="border-b border-neutral-100 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-750">
                            <td class="text-left px-4 py-3 font-medium">
                                <div class="flex items-center gap-2">
                                    @if($index < 3)
                                        <div class="text-lg">
                                            {{ $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉') }}
                                        </div>
                                    @else
                                        <div class="w-6 h-6 flex items-center justify-center text-xs text-neutral-500">{{ $index + 1 }}</div>
                                    @endif
                                    {{ $machine['name'] }}
                                </div>
                            </td>
                            <td class="text-center px-2 py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium uppercase
                                    {{ $machine['type'] === 'excel' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                       'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                    {{ $machine['type'] }}
                                </span>
                            </td>
                            <td class="text-center px-2 py-3 font-medium">{{ $machine['total_tests'] }}</td>
                            <td class="text-center px-2 py-3 text-green-600 dark:text-green-400">{{ $machine['pass_count'] }}</td>
                            <td class="text-center px-2 py-3 text-red-600 dark:text-red-400">{{ $machine['fail_count'] }}</td>
                            <td class="text-center px-2 py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    {{ $machine['pass_rate'] >= 95 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                       ($machine['pass_rate'] >= 90 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                                    {{ $machine['pass_rate'] }}%
                                </span>
                            </td>
                            <td class="text-center px-2 py-3">{{ $machine['tests_per_day'] }}</td>
                            <td class="text-center px-2 py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    {{ $machine['utilization_rate'] >= 80 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                       ($machine['utilization_rate'] >= 60 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                                    {{ $machine['utilization_rate'] }}%
                                </span>
                            </td>
                            <td class="text-center px-2 py-3">{{ $machine['unique_mcs_count'] }}</td>
                            <td class="text-center px-2 py-3">{{ $machine['operators_count'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>