<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Carbon\Carbon;
use App\Models\InsRdcTest;
use Illuminate\Support\Facades\DB;

new class extends Component {

    #[Url]
    public $year;

    #[Url]
    public $month;

    public function mount()
    {
        if (!$this->year) {
            $this->year = now()->year;
        }
        if (!$this->month) {
            $this->month = now()->month;
        }
    }

    private function getWeeksInMonth()
    {
        $startOfMonth = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();
        
        $weeks = [];
        $current = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        
        while ($current->lte($endOfMonth)) {
            $weekStart = $current->copy();
            $weekEnd = $current->copy()->endOfWeek(Carbon::SUNDAY);
            
            // Calculate overlap with the month
            $overlapStart = $weekStart->max($startOfMonth);
            $overlapEnd = $weekEnd->min($endOfMonth);
            
            if ($overlapStart->lte($overlapEnd)) {
                $daysInMonth = intval($overlapStart->diffInDays($overlapEnd)) + 1;
                $weekNumber = $weekStart->weekOfYear;
                
                $weeks[] = [
                    'week' => $weekNumber,
                    'days' => $daysInMonth,
                    'start' => $weekStart,
                    'end' => $weekEnd,
                    'date_range_start' => $overlapStart,
                    'date_range_end' => $overlapEnd,
                ];
            }
            
            $current->addWeek();
        }
        
        return $weeks;
    }

    private function getMonthlySummaryData()
    {
        $startOfMonth = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();
        $weeks = $this->getWeeksInMonth();
        
        // Get all RDC tests for the month
        $tests = InsRdcTest::join('ins_rubber_batches', 'ins_rdc_tests.ins_rubber_batch_id', '=', 'ins_rubber_batches.id')
            ->select(
                'ins_rdc_tests.eval',
                'ins_rdc_tests.created_at',
                'ins_rubber_batches.mcs'
            )
            ->whereBetween('ins_rdc_tests.created_at', [$startOfMonth, $endOfMonth])
            ->whereNotNull('ins_rubber_batches.mcs')
            ->get();

        // Group by MCS and week
        $summary = [];
        $weekTotals = [];
        
        foreach ($weeks as $week) {
            $weekTotals[$week['week']] = ['jumlah' => 0, 'pass' => 0, 'fail' => 0];
        }

        foreach ($tests as $test) {
            $testDate = Carbon::parse($test->created_at);
            $weekNumber = $testDate->weekOfYear;
            
            // Find the week this test belongs to
            $belongsToWeek = false;
            foreach ($weeks as $week) {
                if ($week['week'] == $weekNumber) {
                    $belongsToWeek = true;
                    break;
                }
            }
            
            if (!$belongsToWeek) continue;
            
            $mcs = $test->mcs;
            
            if (!isset($summary[$mcs])) {
                $summary[$mcs] = [];
                foreach ($weeks as $week) {
                    $summary[$mcs][$week['week']] = ['jumlah' => 0, 'pass' => 0, 'fail' => 0];
                }
            }
            
            // Count totals
            $summary[$mcs][$weekNumber]['jumlah']++;
            $weekTotals[$weekNumber]['jumlah']++;
            
            // Count by evaluation
            if ($test->eval === 'pass') {
                $summary[$mcs][$weekNumber]['pass']++;
                $weekTotals[$weekNumber]['pass']++;
            } elseif ($test->eval === 'fail') {
                $summary[$mcs][$weekNumber]['fail']++;
                $weekTotals[$weekNumber]['fail']++;
            }
        }
        
        // Sort summary by MCS ascending
        ksort($summary);
        
        return [
            'weeks' => $weeks,
            'summary' => $summary,
            'totals' => $weekTotals
        ];
    }

    public function with(): array
    {
        $data = $this->getMonthlySummaryData();
        
        return [
            'weeks' => $data['weeks'],
            'summary' => $data['summary'],
            'totals' => $data['totals'],
            'months' => [
                1 => __('Januari'),
                2 => __('Februari'), 
                3 => __('Maret'),
                4 => __('April'),
                5 => __('Mei'),
                6 => __('Juni'),
                7 => __('Juli'),
                8 => __('Agustus'),
                9 => __('September'),
                10 => __('Oktober'),
                11 => __('November'),
                12 => __('Desember'),
            ]
        ];
    }
};

?>

<div>
    <div class="p-0 sm:p-1 mb-6">
        <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="flex gap-3">
                <div>
                    <label for="summary-year"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tahun') }}</label>
                    <x-text-input id="summary-year" wire:model.live="year" type="number" min="2020" max="{{ now()->year + 5 }}" />
                </div>
                <div>
                    <label for="summary-month"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Bulan') }}</label>
                    <x-select id="summary-month" wire:model.live="month">
                        @foreach($months as $monthValue => $monthName)
                        <option value="{{ $monthValue }}">{{ $monthName }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-between gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div>{{ count($summary) . ' MCS ' . __('ditemukan') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (!count($summary))
        <div class="py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="icon-ghost"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada data untuk bulan ini') }}
            </div>
        </div>
    @else
        <div class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
                <table class="table table-sm text-sm text-neutral-600 dark:text-neutral-400 w-full">
                    <thead>
                        <tr class="uppercase text-xs border-b border-neutral-200 dark:border-neutral-700">
                            <th rowspan="3" class="text-left px-4 py-3 bg-neutral-50 dark:bg-neutral-900 align-middle">MCS</th>
                            @foreach($weeks as $week)
                            <td colspan="3" class="text-center px-2 py-3 bg-neutral-50 dark:bg-neutral-900 border-l border-neutral-200 dark:border-neutral-700 uppercase text-sm font-bold">
                                W{{ str_pad($week['week'], 2, '0', STR_PAD_LEFT) }}
                            </td>
                            @endforeach
                        </tr>
                        <tr class="text-xs border-b border-neutral-200 dark:border-neutral-700">
                            @foreach($weeks as $week)
                            <td colspan="3" class="text-center px-2 py-2 bg-neutral-50 dark:bg-neutral-900 border-l border-neutral-200 dark:border-neutral-700 uppercase text-xs">
                                {{ $week['date_range_start']->format('j') }} {{ $week['date_range_start']->format('M') }} - {{ $week['date_range_end']->format('j') }} {{ $week['date_range_end']->format('M') }} ({{ $week['days'] }} {{ __('hari') }})
                            </td>
                            @endforeach
                        </tr>
                        <tr class="text-xs border-b border-neutral-200 dark:border-neutral-700">
                            @foreach($weeks as $week)
                            <td class="text-center px-2 py-2 bg-neutral-50 dark:bg-neutral-900 border-l border-neutral-200 dark:border-neutral-700 uppercase text-xs">{{ __('Jml') }}</td>
                            <td class="text-center px-2 py-2 bg-neutral-50 dark:bg-neutral-900 uppercase text-xs">{{ __('Pass') }}</td>
                            <td class="text-center px-2 py-2 bg-neutral-50 dark:bg-neutral-900 uppercase text-xs">{{ __('Fail') }}</td>
                            @endforeach
                        </tr>
                        <tr class="font-semibold text-xs bg-neutral-100 dark:bg-neutral-800 border-b-2 border-neutral-300 dark:border-neutral-600">
                            <td class="text-left px-4 py-2 font-bold">{{ __('TOTAL') }}</td>
                            @foreach($weeks as $week)
                            <td class="text-center px-2 py-2 border-l border-neutral-200 dark:border-neutral-700">{{ $totals[$week['week']]['jumlah'] ?? 0 }}</td>
                            <td class="text-center px-2 py-2 text-green-600 dark:text-green-400">{{ $totals[$week['week']]['pass'] ?? 0 }}</td>
                            <td class="text-center px-2 py-2 text-red-600 dark:text-red-400">{{ $totals[$week['week']]['fail'] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($summary as $mcs => $weekData)
                        <tr class="border-b border-neutral-100 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-750">
                            <td class="text-left px-4 py-3 font-medium align-middle">{{ $mcs }}</td>
                            @foreach($weeks as $week)
                            <td class="text-center px-2 py-3 border-l border-neutral-200 dark:border-neutral-700">{{ $weekData[$week['week']]['jumlah'] ?? 0 }}</td>
                            <td class="text-center px-2 py-3 text-green-600 dark:text-green-400">{{ $weekData[$week['week']]['pass'] ?? 0 }}</td>
                            <td class="text-center px-2 py-3 text-red-600 dark:text-red-400">{{ $weekData[$week['week']]['fail'] ?? 0 }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>