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
    public array $deviationSummary = [];
    public array $severityBreakdown = [];
    public array $lineDeviations = [];

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
        $this->calculateDeviations();
        $this->renderCharts();
    }

    private function calculateDeviations()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();
        $targets = InsStc::$target_values; // [75, 73, 68, 63, 58, 53, 43, 43]

        $query = InsStcDSum::with(['ins_stc_machine'])
            ->when($this->line, function (Builder $query) {
                $query->whereHas('ins_stc_machine', function (Builder $query) {
                    $query->where('line', $this->line);
                });
            })
            ->when($this->position, function (Builder $query) {
                $query->where('position', $this->position);
            })
            ->whereBetween('created_at', [$start, $end]);

        $dSums = $query->get();

        $totalMeasurements = 0;
        $totalDeviations = 0;
        $severityCount = ['minor' => 0, 'major' => 0, 'critical' => 0];
        $lineStats = [];

        foreach ($dSums as $dSum) {
            $hbValues = json_decode($dSum->hb_values, true) ?? [];
            $line = $dSum->ins_stc_machine->line;

            if (!isset($lineStats[$line])) {
                $lineStats[$line] = [
                    'total' => 0,
                    'deviations' => 0,
                    'minor' => 0,
                    'major' => 0,
                    'critical' => 0
                ];
            }

            $lineStats[$line]['total']++;
            $totalMeasurements++;

            // Check each zone for deviations
            for ($i = 0; $i < 8; $i++) {
                if (isset($hbValues[$i]) && isset($targets[$i])) {
                    $deviation = abs($hbValues[$i] - $targets[$i]);
                    
                    if ($deviation > 1) { // Any deviation > 1°C
                        $totalDeviations++;
                        $lineStats[$line]['deviations']++;

                        // Classify severity
                        if ($deviation >= 10) {
                            $severityCount['critical']++;
                            $lineStats[$line]['critical']++;
                        } elseif ($deviation >= 5) {
                            $severityCount['major']++;
                            $lineStats[$line]['major']++;
                        } else {
                            $severityCount['minor']++;
                            $lineStats[$line]['minor']++;
                        }
                    }
                }
            }
        }

        $this->deviationSummary = [
            'total_measurements' => $totalMeasurements,
            'total_deviations' => $totalDeviations,
            'deviation_rate' => $totalMeasurements > 0 ? round(($totalDeviations / ($totalMeasurements * 8)) * 100, 2) : 0
        ];

        $this->severityBreakdown = $severityCount;
        $this->lineDeviations = $lineStats;
    }

    private function renderCharts()
    {
        // Severity breakdown pie chart
        $severityChartData = [
            'labels' => [__('Minor (1-5°C)'), __('Major (5-10°C)'), __('Critical (>10°C)')],
            'datasets' => [[
                'data' => array_values($this->severityBreakdown),
                'backgroundColor' => ['rgba(255, 205, 86, 0.8)', 'rgba(255, 159, 64, 0.8)', 'rgba(255, 99, 132, 0.8)']
            ]]
        ];

        // Line deviation rate chart
        $lineLabels = array_map(fn($line) => "Line $line", array_keys($this->lineDeviations));
        $lineRates = array_map(function($stats) {
            return $stats['total'] > 0 ? round(($stats['deviations'] / ($stats['total'] * 8)) * 100, 2) : 0;
        }, $this->lineDeviations);

        $lineChartData = [
            'labels' => $lineLabels,
            'datasets' => [[
                'label' => __('Deviation Rate (%)'),
                'data' => $lineRates,
                'backgroundColor' => 'rgba(214, 69, 80, 0.8)'
            ]]
        ];

        $this->js("
            (function() {
                  var severityCtx = document.getElementById('severity-chart');
                  if (window.severityChart) window.severityChart.destroy();
                  window.severityChart = new Chart(severityCtx, {
                     type: 'doughnut',
                     data: " . json_encode($severityChartData) . ",
                     options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                              title: {
                                 display: true,
                                 text: '" . __('Deviation Severity Breakdown') . "'
                              }
                        }
                     }
                  });
                  
                  var lineCtx = document.getElementById('line-chart');
                  if (window.lineChart) window.lineChart.destroy();
                  window.lineChart = new Chart(lineCtx, {
                     type: 'bar',
                     data: " . json_encode($lineChartData) . ",
                     options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                              title: {
                                 display: true,
                                 text: '" . __('Deviation Rate by Line') . "'
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
            })()
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
                    <x-text-input wire:model.live="end_at" id="cal-date-end" type="date"></x-text-input>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex gap-3">
                <div>
                    <label for="device-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-select id="device-line" wire:model.live="line">
                        <option value=""></option>
                        @foreach($lines as $line)
                        <option value="{{ $line }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label for="device-position" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                    <x-select id="device-position" wire:model.live="position">
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
                    <div>{{ __('Memuat...') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Total Pengukuran') }}</div>
            <div class="text-2xl font-bold">{{ number_format($deviationSummary['total_measurements'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Total Deviasi') }}</div>
            <div class="text-2xl font-bold text-red-500">{{ number_format($deviationSummary['total_deviations'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase mb-2">{{ __('Tingkat Deviasi') }}</div>
            <div class="text-2xl font-bold {{ ($deviationSummary['deviation_rate'] ?? 0) > 10 ? 'text-red-500' : (($deviationSummary['deviation_rate'] ?? 0) > 5 ? 'text-yellow-500' : 'text-green-500') }}">
                {{ ($deviationSummary['deviation_rate'] ?? 0) }}%
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="severity-chart"></canvas>
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="h-80">
                <canvas id="line-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class="mt-8 bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        <table class="table table-sm text-sm w-full">
            <thead>
                <tr class="text-xs uppercase text-neutral-500 border-b">
                    <th class="px-4 py-3">{{ __('Line') }}</th>
                    <th class="px-4 py-3">{{ __('Total Pengukuran') }}</th>
                    <th class="px-4 py-3">{{ __('Deviasi') }}</th>
                    <th class="px-4 py-3">{{ __('Tingkat (%)') }}</th>
                    <th class="px-4 py-3">{{ __('Minor') }}</th>
                    <th class="px-4 py-3">{{ __('Major') }}</th>
                    <th class="px-4 py-3">{{ __('Critical') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lineDeviations as $line => $stats)
                <tr class="border-b border-neutral-100 dark:border-neutral-700">
                    <td class="px-4 py-3 font-mono">{{ sprintf('%02d', $line) }}</td>
                    <td class="px-4 py-3">{{ number_format($stats['total']) }}</td>
                    <td class="px-4 py-3">{{ number_format($stats['deviations']) }}</td>
                    <td class="px-4 py-3">
                    <span class="{{ $stats['total'] > 0 && (($stats['deviations'] / ($stats['total'] * 8)) * 100) > 10 ? 'text-red-500' : ((($stats['deviations'] / ($stats['total'] * 8)) * 100) > 5 ? 'text-yellow-500' : 'text-green-500') }}">
                        {{ $stats['total'] > 0 ? number_format(($stats['deviations'] / ($stats['total'] * 8)) * 100, 2) : 0 }}%
                     </span>
                    </td>
                    <td class="px-4 py-3">{{ number_format($stats['minor']) }}</td>
                    <td class="px-4 py-3">{{ number_format($stats['major']) }}</td>
                    <td class="px-4 py-3">{{ number_format($stats['critical']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript