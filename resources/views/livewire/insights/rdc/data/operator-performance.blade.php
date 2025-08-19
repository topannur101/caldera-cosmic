<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use Carbon\Carbon;
use App\Models\InsRdcTest;
use App\Models\InsRdcMachine;
use App\Models\User;
use App\Traits\HasDateRangeFilter;
use Illuminate\Support\Facades\DB;

new class extends Component {

    use HasDateRangeFilter;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public $machine_id;

    public int $progress = 0;
    public array $operator_stats = [];
    public array $overall_stats = [];
    public array $machines = [];

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisMonth();
        }
    }

    #[On('update')]
    public function updated()
    {
        $this->progress = 0;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        // Phase 1: Mengambil data (0-49%)
        $this->progress = 10;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        $this->calculateOperatorStats();

        $this->progress = 49;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        // Phase 2: Menghitung metrik (49-98%)
        $this->progress = 60;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        $this->machines = InsRdcMachine::where('is_active', true)
            ->orderBy('number')
            ->get()
            ->toArray();

        $this->progress = 98;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );

        // Phase 3: Merender grafik (98-100%)
        $this->renderCharts();

        $this->progress = 100;
        $this->stream(
            to: 'progress',
            content: $this->progress,
            replace: true
        );
    }

    private function calculateOperatorStats()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsRdcTest::join('ins_rubber_batches', 'ins_rdc_tests.ins_rubber_batch_id', '=', 'ins_rubber_batches.id')
            ->join('users', 'ins_rdc_tests.user_id', '=', 'users.id')
            ->join('ins_rdc_machines', 'ins_rdc_tests.ins_rdc_machine_id', '=', 'ins_rdc_machines.id')
            ->whereBetween('ins_rdc_tests.created_at', [$start, $end])
            ->whereNotNull('ins_rdc_tests.eval');

        if ($this->machine_id) {
            $query->where('ins_rdc_tests.ins_rdc_machine_id', $this->machine_id);
        }

        $tests = $query->select(
            'ins_rdc_tests.*',
            'users.name as operator_name',
            'users.emp_id as operator_emp_id',
            'users.photo as operator_photo',
            'ins_rubber_batches.mcs',
            'ins_rdc_machines.name as machine_name'
        )->get();

        $operatorData = [];
        $totalTests = 0;
        $totalPass = 0;
        $totalFail = 0;

        foreach ($tests as $test) {
            $operatorId = $test->user_id;
            
            if (!isset($operatorData[$operatorId])) {
                $operatorData[$operatorId] = [
                    'name' => $test->operator_name,
                    'emp_id' => $test->operator_emp_id,
                    'photo' => $test->operator_photo,
                    'total_tests' => 0,
                    'pass_count' => 0,
                    'fail_count' => 0,
                    'unique_mcs' => [],
                    'machines_used' => [],
                    'test_dates' => []
                ];
            }

            $operatorData[$operatorId]['total_tests']++;
            $totalTests++;

            if ($test->eval === 'pass') {
                $operatorData[$operatorId]['pass_count']++;
                $totalPass++;
            } else {
                $operatorData[$operatorId]['fail_count']++;
                $totalFail++;
            }


            // Track unique MCS and machines
            if ($test->mcs && !in_array($test->mcs, $operatorData[$operatorId]['unique_mcs'])) {
                $operatorData[$operatorId]['unique_mcs'][] = $test->mcs;
            }
            if (!in_array($test->machine_name, $operatorData[$operatorId]['machines_used'])) {
                $operatorData[$operatorId]['machines_used'][] = $test->machine_name;
            }

            $operatorData[$operatorId]['test_dates'][] = $test->created_at;
        }

        // Calculate derived metrics for each operator
        $this->operator_stats = [];
        foreach ($operatorData as $operatorId => $data) {
            $passRate = $data['total_tests'] > 0 ? ($data['pass_count'] / $data['total_tests']) * 100 : 0;
            
            // Calculate average tests per day
            $testDates = array_map(function($date) {
                return Carbon::parse($date)->format('Y-m-d');
            }, $data['test_dates']);
            $uniqueDays = count(array_unique($testDates));
            $testsPerDay = $uniqueDays > 0 ? $data['total_tests'] / $uniqueDays : 0;

            $this->operator_stats[] = [
                'name' => $data['name'],
                'emp_id' => $data['emp_id'],
                'photo' => $data['photo'],
                'total_tests' => $data['total_tests'],
                'pass_count' => $data['pass_count'],
                'fail_count' => $data['fail_count'],
                'pass_rate' => round($passRate, 1),
                'tests_per_day' => round($testsPerDay, 1),
                'unique_mcs_count' => count($data['unique_mcs']),
                'machines_used_count' => count($data['machines_used'])
            ];
        }

        // Sort by total tests (test volume)
        usort($this->operator_stats, function($a, $b) {
            return $b['total_tests'] <=> $a['total_tests'];
        });

        // Calculate overall statistics
        $totalOperators = count($this->operator_stats);
        $overallPassRate = $totalTests > 0 ? ($totalPass / $totalTests) * 100 : 0;
        
        $this->overall_stats = [
            'total_operators' => $totalOperators,
            'total_tests' => $totalTests,
            'total_pass' => $totalPass,
            'total_fail' => $totalFail,
            'overall_pass_rate' => round($overallPassRate, 1),
            'overall_fail_rate' => round(100 - $overallPassRate, 1),
            'avg_tests_per_operator' => $totalOperators > 0 ? round($totalTests / $totalOperators, 1) : 0
        ];
    }

    private function renderCharts()
    {
        if (count($this->operator_stats) === 0) return;
        
        $operatorNames = array_map(function($operator) {
            return $operator['name'];
        }, $this->operator_stats);
        
        $passRates = array_column($this->operator_stats, 'pass_rate');
        $testsPerDay = array_column($this->operator_stats, 'tests_per_day');
        $mcsCount = array_column($this->operator_stats, 'unique_mcs_count');

        $chartData = [
            'labels' => $operatorNames,
            'datasets' => [
                [
                    'label' => __('Pass Rate (%)'),
                    'hidden' => true,
                    'data' => $passRates,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)'
                ],
                [
                    'label' => __('Uji/Hari'),
                    'data' => $testsPerDay,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)'
                ],
                [
                    'label' => __('Jenis MCS'),
                    'data' => $mcsCount,
                    'backgroundColor' => 'rgba(234, 179, 8, 0.8)'
                ]
            ]
        ];

        $this->js("
            setTimeout(() => {
                const ctx = document.getElementById('operator-performance-chart');
                if (!ctx) {
                    console.error('Chart canvas element not found');
                    return;
                }
                if (window.operatorPerformanceChart) {
                    window.operatorPerformanceChart.destroy();
                }
                window.operatorPerformanceChart = new Chart(ctx, {
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
                                ticks: {
                                    callback: function(value, index, values) {
                                        if (index === 0) return value + '%';
                                        return value;
                                    }
                                }
                            }
                        }
                    }
                });
            }, 100);
        ");
    }

    public function with(): array
    {
        return [
            'operator_stats' => $this->operator_stats,
            'overall_stats' => $this->overall_stats,
            'machines' => $this->machines
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
            <div class="grid grid-cols-1 gap-3">
                <div class="w-full lg:w-48">
                    <label for="operator-machine"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mesin') }}</label>
                    <x-select id="operator-machine" wire:model.live="machine_id">
                        <option value="">{{ __('Semua mesin') }}</option>
                        @foreach($machines as $machine)
                        <option value="{{ $machine['id'] }}">{{ $machine['number'] }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex justify-between gap-x-2 items-center">
                <div class="flex gap-3 text-sm">
                    <div class="flex flex-col justify-around">
                        <table>
                            <tr>
                                <td class="text-neutral-500">{{ __('Operator') . ': ' }}</td>
                                <td>{{ $overall_stats['total_operators'] ?? 0 }}</td>
                            </tr>
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
                </div>
            </div>
            <div class="grow flex justify-center gap-x-2 items-center">
                <div wire:loading.class.remove="hidden" class="hidden px-3">
                    <x-progress-bar :$progress>                       
                        <span x-text="
                        progress < 49 ? '{{ __('Mengambil data...') }}' : 
                        progress < 98 ? '{{ __('Menghitung metrik...') }}' : 
                        '{{ __('Merender grafik...') }}'
                        "></span>
                    </x-progress-bar>
                </div>
            </div>
            <div class="my-auto">                
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
            <div class="p-6 overflow-auto">
                <div class="flex justify-between items-start mb-6">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Penjelasan Metrik Kinerja Operator') }}
                    </h2>
                    <x-text-button type="button" x-on:click="window.dispatchEvent(escKey)">
                        <i class="icon-x"></i>
                    </x-text-button>
                </div>
                
                <div class="space-y-6 text-sm text-neutral-600 dark:text-neutral-400">
                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Pass Rate') }}</h3>
                        <p class="mb-2">{{ __('Persentase sampel yang lulus evaluasi dari total sampel yang diuji oleh operator.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Rumus: (Jumlah Pass / Total Uji) × 100') }}<br>
                            {{ __('Pass rate tinggi = keterampilan operator baik') }}<br>
                            {{ __('Contoh: 95 pass dari 100 uji = 95%') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Uji/Hari') }}</h3>
                        <p class="mb-2">{{ __('Rata-rata jumlah pengujian yang dilakukan operator per hari pada periode yang dipilih.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Rumus: Total Uji / Jumlah Hari Aktif') }}<br>
                            {{ __('Tinggi = produktivitas operator tinggi') }}<br>
                            {{ __('Rendah = operator kurang aktif atau efisiensi rendah') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('MCS') }}</h3>
                        <p class="mb-2">{{ __('Jumlah jenis compound (MCS) yang berbeda yang diuji oleh operator.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Menunjukkan variasi material yang ditangani operator') }}<br>
                            {{ __('Tinggi = operator versatile, menangani banyak jenis compound') }}<br>
                            {{ __('Rendah = operator spesialis untuk compound tertentu') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Mesin') }}</h3>
                        <p class="mb-2">{{ __('Jumlah mesin yang berbeda yang dioperasikan oleh operator pada periode ini.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Menunjukkan fleksibilitas dan kemampuan adaptasi operator') }}<br>
                            {{ __('Banyak mesin = operator multiskill, dapat mengoperasikan berbagai mesin') }}<br>
                            {{ __('Sedikit mesin = operator spesialis pada mesin tertentu') }}
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Indikator Warna') }}</h3>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                                <span>{{ __('Hijau: Pass Rate ≥95% (Sangat Baik)') }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-yellow-500 rounded mr-2"></div>
                                <span>{{ __('Kuning: Pass Rate 90-94% (Baik)') }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-red-500 rounded mr-2"></div>
                                <span>{{ __('Merah: Pass Rate <90% (Perlu Perhatian)') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Peringkat') }}</h3>
                        <p class="mb-2">{{ __('Operator diurutkan berdasarkan total volume pengujian (Total Uji) untuk menunjukkan operator yang paling aktif.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('🥇🥈🥉 = 3 operator teratas berdasarkan volume pengujian') }}<br>
                            {{ __('Volume tinggi menunjukkan operator yang produktif dan aktif') }}<br>
                            {{ __('Kombinasi volume tinggi + pass rate tinggi = operator terbaik') }}
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Tips Interpretasi') }}</h3>
                        <div class="space-y-2 text-xs">
                            <p>• <strong>Operator Ideal:</strong> Volume tinggi + Pass rate tinggi + Banyak MCS/Mesin</p>
                            <p>• <strong>Perlu Pelatihan:</strong> Volume tinggi tetapi Pass rate rendah</p>
                            <p>• <strong>Kurang Aktif:</strong> Volume rendah meski Pass rate tinggi</p>
                            <p>• <strong>Spesialis:</strong> Sedikit MCS/Mesin tetapi konsisten</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-slide-over>
    </div>

    @if (!count($operator_stats))
        <div class="py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="icon-users"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada data operator untuk periode ini') }}
            </div>
        </div>
    @else
        <!-- Charts and Table Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Performance Chart -->
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
                <div style="min-height: 400px;">
                    <canvas id="operator-performance-chart" wire:ignore></canvas>
                </div>
            </div>

            <!-- Performance Table -->
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden col-span-2">
                <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                    <h3 class="text-lg font-medium">{{ __('Peringkat Performa') }}</h3>
                </div>
                <div class="overflow-auto">
                    <table class="table table-sm text-sm text-neutral-600 dark:text-neutral-400 w-full">
                        <thead>
                            <tr class="uppercase text-xs border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900">
                                <th class="text-left px-4 py-3">{{ __('Operator') }}</th>
                                <th class="text-center px-2 py-3">{{ __('Total Uji') }}</th>
                                <th class="text-center px-2 py-3">{{ __('Pass') }}</th>
                                <th class="text-center px-2 py-3">{{ __('Fail') }}</th>
                                <th class="text-center px-2 py-3">{{ __('Pass Rate') }}</th>
                                <th class="text-center px-2 py-3">{{ __('Uji/Hari') }}</th>
                                <th class="text-center px-2 py-3">{{ __('MCS') }}</th>
                                <th class="text-center px-2 py-3">{{ __('Mesin') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($operator_stats as $index => $operator)
                            <tr class="border-b border-neutral-100 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-750">
                                <td class="text-left px-4 py-3 font-medium">
                                    <div class="flex items-center gap-3">
                                        @if($index < 3)
                                            <div class="text-lg">
                                                {{ $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉') }}
                                            </div>
                                        @else
                                            <div class="w-6 h-6 flex items-center justify-center text-xs text-neutral-500">{{ $index + 1 }}</div>
                                        @endif
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                                @if($operator['photo'] ?? false)
                                                <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$operator['photo'] }}" />
                                                @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                                @endif
                                            </div>
                                            <span title="{{ $operator['emp_id'] }}">{{ $operator['name'] }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center px-2 py-3 font-medium">{{ $operator['total_tests'] }}</td>
                                <td class="text-center px-2 py-3 text-green-600 dark:text-green-400">{{ $operator['pass_count'] }}</td>
                                <td class="text-center px-2 py-3 text-red-600 dark:text-red-400">{{ $operator['fail_count'] }}</td>
                                <td class="text-center px-2 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        {{ $operator['pass_rate'] >= 95 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                           ($operator['pass_rate'] >= 90 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                                        {{ $operator['pass_rate'] }}%
                                    </span>
                                </td>
                                <td class="text-center px-2 py-3">{{ $operator['tests_per_day'] }}</td>
                                <td class="text-center px-2 py-3">{{ $operator['unique_mcs_count'] }}</td>
                                <td class="text-center px-2 py-3">{{ $operator['machines_used_count'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    @endif
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript