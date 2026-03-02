<?php

use App\Models\InsBpmCount;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    use HasDateRangeFilter {
        setToday as traitSetToday;
        setYesterday as traitSetYesterday;
        setThisWeek as traitSetThisWeek;
        setLastWeek as traitSetLastWeek;
        setThisMonth as traitSetThisMonth;
        setLastMonth as traitSetLastMonth;
    }

    public $view = 'summary';

    #[Url]
    public $start_at;

    #[Url]
    public $end_at;

    #[Url]
    public $condition = 'all';

    public $lastUpdated;

    public $summaryCards = [];

    public $dailyData = [];

    public $chartLabels = [];

    public $chartData = [];

    public $chartDatasets = [];

    public function mount()
    {
        // update menu
        $this->dispatch('update-menu', $this->view);

        // Set default dates if not set
        if (! $this->start_at || ! $this->end_at) {
            $this->setToday();
        }

        // Load initial data
        $this->loadData();
        $this->generateEmergencyChart();
    }

    public function with(): array
    {
        return [
            'summaryCards' => $this->summaryCards,
            'dailyData' => $this->dailyData,
            'lastUpdated' => $this->lastUpdated,
        ];
    }

    public function loadData()
    {
        $from = Carbon::parse($this->start_at)->startOfDay();
        $to = Carbon::parse($this->end_at)->endOfDay();

        // Get all records with cumulative > 0
        $allRecords = InsBpmCount::whereBetween('created_at', [$from, $to])
            ->where('cumulative', '>', 0)
            ->when($this->condition !== 'all', fn ($q) => $q->where('condition', $this->condition))
            ->orderBy('created_at', 'desc')
            ->get();

        // Group by day and plant to get daily cumulative per plant (use max, not sum)
        $dailyByPlant = $allRecords->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d').'-'.$item->plant;
        })->map(function ($items, $key) {
            $parts = explode('-', $key);
            $date = $parts[0].'-'.$parts[1].'-'.$parts[2];
            $plant = $parts[3];

            return (object) [
                'date' => $date,
                'plant' => $plant,
                'total' => $items->max('cumulative'),
                'hot' => $items->where('condition', 'hot')->max('cumulative') ?? 0,
                'cold' => $items->where('condition', 'cold')->max('cumulative') ?? 0,
            ];
        });

        // Get all unique plants
        $plants = $allRecords->pluck('plant')->unique()->sort()->values();

        // Get all unique dates in range
        $dateRange = Carbon::parse($from)->toPeriod(Carbon::parse($to));

        // Build matrix: dates as rows, plants as columns
        $this->dailyData = [];
        foreach ($dateRange as $date) {
            $dateStr = $date->format('Y-m-d');
            $row = [
                'date' => $dateStr,
                'display_date' => $date->format('d M'),
                'day_name' => $date->format('D'),
            ];

            $rowTotal = 0;
            foreach ($plants as $plant) {
                $key = $dateStr.'-'.$plant;
                $data = $dailyByPlant->get($key);
                $row['plant_'.$plant] = $data ? $data->total : 0;
                $rowTotal += $data ? $data->total : 0;
            }
            $row['total'] = $rowTotal;
            $this->dailyData[] = $row;
        }

        // Calculate summary cards
        $totalEmergency = $allRecords->sum('cumulative');

        // Find day with highest emergency
        $dailyTotals = collect($this->dailyData)->pluck('total', 'date');
        $highestDate = $dailyTotals->sortDesc()->keys()->first();
        $highestValue = $dailyTotals->max();

        // Find day with lowest emergency (excluding zero days if range > 1)
        $lowestDate = $dailyTotals->sortBy(fn ($v) => $v)->keys()->first();
        $lowestValue = $dailyTotals->min();

        // Average per day
        $average = $dailyTotals->count() > 0 ? round($dailyTotals->avg()) : 0;

        // Get top plant
        $plantTotals = $allRecords->groupBy('plant')->map(fn ($items) => $items->sum('cumulative'))->sortDesc();
        $topPlant = $plantTotals->keys()->first();
        $topPlantValue = $plantTotals->first();

        $this->summaryCards = [
            [
                'label' => 'Total Emergency',
                'sublabel' => $from->format('d M').' - '.$to->format('d M'),
                'value' => $totalEmergency,
                'color' => 'red',
                'icon' => 'emergency',
            ],
            [
                'label' => 'Tertinggi',
                'sublabel' => $highestDate ? Carbon::parse($highestDate)->format('d M') : '-',
                'value' => $highestValue,
                'color' => 'orange',
                'icon' => 'trending-up',
            ],
            [
                'label' => 'Rata-rata',
                'sublabel' => 'Per Hari',
                'value' => $average,
                'color' => 'blue',
                'icon' => 'calendar',
            ],
            [
                'label' => 'Terendah',
                'sublabel' => $lowestDate ? Carbon::parse($lowestDate)->format('d M') : '-',
                'value' => $lowestValue,
                'color' => 'green',
                'icon' => 'clock',
            ],
        ];

        $this->lastUpdated = now()->format('n/j/Y, H:i.s');
    }

    public function generateEmergencyChart()
    {
        $from = Carbon::parse($this->start_at)->startOfDay();
        $to = Carbon::parse($this->end_at)->endOfDay();

        // Get all records with cumulative > 0
        $allRecords = InsBpmCount::whereBetween('created_at', [$from, $to])
            ->where('cumulative', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        // Group by plant and condition (use max cumulative for each plant)
        $plantTotals = $allRecords->groupBy('plant')->map(function ($items) {
            return (object) [
                'total' => $items->max('cumulative'),
                'hot' => $items->where('condition', 'hot')->max('cumulative') ?? 0,
                'cold' => $items->where('condition', 'cold')->max('cumulative') ?? 0,
            ];
        })->sortBy(fn ($item, $key) => $key);

        $labels = $plantTotals->keys()->toArray();

        if ($this->condition === 'all') {
            $this->chartLabels = $labels;
            $this->chartDatasets = [
                [
                    'label' => 'Hot',
                    'data' => $plantTotals->pluck('hot')->map(fn ($v) => (int) $v)->toArray(),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgba(239, 68, 68, 1)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Cold',
                    'data' => $plantTotals->pluck('cold')->map(fn ($v) => (int) $v)->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 1,
                ],
            ];
        } else {
            $data = $this->condition === 'hot'
                ? $plantTotals->pluck('hot')->map(fn ($v) => (int) $v)->toArray()
                : $plantTotals->pluck('cold')->map(fn ($v) => (int) $v)->toArray();

            $color = $this->condition === 'hot'
                ? ['bg' => 'rgba(239, 68, 68, 0.8)', 'border' => 'rgba(239, 68, 68, 1)']
                : ['bg' => 'rgba(59, 130, 246, 0.8)', 'border' => 'rgba(59, 130, 246, 1)'];

            $this->chartLabels = $labels;
            $this->chartDatasets = [
                [
                    'label' => ucfirst($this->condition),
                    'data' => $data,
                    'backgroundColor' => $color['bg'],
                    'borderColor' => $color['border'],
                    'borderWidth' => 1,
                ],
            ];
        }

        $this->dispatch('chart-data-updated', labels: $this->chartLabels, datasets: $this->chartDatasets);
    }

    public function updatedStartAt()
    {
        $this->loadData();
        $this->generateEmergencyChart();
    }

    public function updatedEndAt()
    {
        $this->loadData();
        $this->generateEmergencyChart();
    }

    public function updatedCondition()
    {
        $this->loadData();
        $this->generateEmergencyChart();
    }

    public function setToday()
    {
        $this->traitSetToday();
        $this->loadData();
        $this->generateEmergencyChart();
    }

    public function setYesterday()
    {
        $this->traitSetYesterday();
        $this->loadData();
        $this->generateEmergencyChart();
    }

    public function setThisWeek()
    {
        $this->traitSetThisWeek();
        $this->loadData();
        $this->generateEmergencyChart();
    }

    public function setLastWeek()
    {
        $this->traitSetLastWeek();
        $this->loadData();
        $this->generateEmergencyChart();
    }

    public function setThisMonth()
    {
        $this->traitSetThisMonth();
        $this->loadData();
        $this->generateEmergencyChart();
    }

    public function setLastMonth()
    {
        $this->traitSetLastMonth();
        $this->loadData();
        $this->generateEmergencyChart();
    }
}; ?>

<div class="p-6 space-y-6">
    {{-- Header with Filters --}}
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-end flex-1">
            <div>
                <div class="flex mb-2 text-xs text-neutral-500">
                    <div class="flex">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <x-text-button class="uppercase ml-3">
                                    {{ __("RENTANG") }}
                                    <i class="icon-chevron-down ms-1"></i>
                                </x-text-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setToday">
                                    {{ __("Hari ini") }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                    {{ __("Kemarin") }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                    {{ __("Minggu ini") }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                    {{ __("Minggu lalu") }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                    {{ __("Bulan ini") }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                    {{ __("Bulan lalu") }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" type="date" class="w-40" />
                    <x-text-input wire:model.live="end_at" type="date" class="w-40" />
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div>
                <label class="block text-sm font-medium mb-2">{{ __('CONDITION') }}</label>
                <select wire:model.live="condition" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                    <option value="all">All</option>
                    <option value="hot">Hot</option>
                    <option value="cold">Cold</option>
                </select>
            </div>
        </div>
        <div wire:loading wire:target="start_at,end_at,condition,setToday,setYesterday,setThisWeek,setLastWeek,setThisMonth,setLastMonth" class="rela inset-0 bg-white/70 dark:bg-neutral-800/70 backdrop-blur-sm rounded-lg z-10 flex items-center justify-center">
            <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Loading...') }}</span>
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-400">
            <div>{{ __('Last Updated') }}</div>
            <div class="font-semibold">{{ $lastUpdated }}</div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Emergency Counter Chart - wire:ignore wraps entire block so Alpine persists across Livewire updates --}}
        <div class="lg:col-span-2 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">Emergency Counter</h2>
            </div>
            <div wire:ignore>
                <div
                    x-data="{
                        emergencyChart: null,
                        initTimeout: null,

                        destroyChart() {
                            if (this.initTimeout) {
                                clearTimeout(this.initTimeout);
                                this.initTimeout = null;
                            }
                            const canvasEl = this.$refs.emergencyChartCanvas;
                            if (canvasEl) {
                                const existingChart = Chart.getChart(canvasEl);
                                if (existingChart) {
                                    try {
                                        existingChart.destroy();
                                    } catch (e) {
                                        console.log('Error destroying chart:', e);
                                    }
                                }
                            }
                            this.emergencyChart = null;
                        },

                        initOrUpdateEmergencyChart(chartData) {
                            if (this.initTimeout) {
                                clearTimeout(this.initTimeout);
                                this.initTimeout = null;
                            }

                            const canvasEl = this.$refs.emergencyChartCanvas;
                            if (!canvasEl || typeof Chart === 'undefined') return;

                            const labels = chartData?.labels || [];
                            const datasets = chartData?.datasets || [];

                            if (labels.length === 0 || datasets.length === 0) {
                                this.$refs.chartContainer?.classList.add('hidden');
                                this.$refs.emptyState?.classList.remove('hidden');
                                this.destroyChart();
                                return;
                            }

                            this.$refs.chartContainer?.classList.remove('hidden');
                            this.$refs.emptyState?.classList.add('hidden');
                            this.destroyChart();

                            this.initTimeout = setTimeout(() => {
                                this.initTimeout = null;
                                const ctx = canvasEl.getContext('2d');
                                if (!ctx) return;
                                try {
                                    this.emergencyChart = new Chart(ctx, {
                                        type: 'bar',
                                        data: { labels, datasets },
                                        options: {
                                            indexAxis: 'y',
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            animation: { duration: 300 },
                                            plugins: {
                                                legend: { display: datasets.length > 1, position: 'top' },
                                                tooltip: {
                                                    callbacks: {
                                                        label: (ctx) => ctx.dataset.label + ': ' + ctx.parsed.x + ' counts'
                                                    }
                                                },
                                                datalabels: { display: false }
                                            },
                                            scales: {
                                                x: {
                                                    stacked: true,
                                                    beginAtZero: true,
                                                    title: { display: true, text: 'Counter' },
                                                    ticks: {
                                                        stepSize: 1,
                                                        callback: (v) => Number.isInteger(v) ? v.toLocaleString() : ''
                                                    }
                                                },
                                                y: {
                                                    stacked: true,
                                                    title: { display: true, text: 'Plant' }
                                                }
                                            }
                                        }
                                    });
                                } catch (e) {
                                    console.error('Chart creation error:', e);
                                }
                            }, 50);
                        }
                    }"
                    x-init="
                        $nextTick(() => {
                            initOrUpdateEmergencyChart({
                                labels: @js($this->chartLabels),
                                datasets: @js($this->chartDatasets)
                            });
                        });
                    "
                    @chart-data-updated.window="
                        const chartData = Array.isArray($event.detail) ? $event.detail[0] : $event.detail;
                        if (chartData && (chartData.labels !== undefined || chartData.datasets?.length)) {
                            initOrUpdateEmergencyChart(chartData);
                        }
                    "
                >
                    <div x-ref="chartContainer" style="height: 500px; position: relative;">
                        <canvas x-ref="emergencyChartCanvas" style="display: block;"></canvas>
                    </div>
                    <div x-ref="emptyState" class="hidden flex flex-col items-center justify-center text-gray-500" style="height: 500px;">
                        <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <p class="text-lg font-medium">No Data Available</p>
                        <p class="text-sm text-gray-400 mt-2">Try adjusting your filters or date range</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Summary Cards and Ranking --}}
        <div class="space-y-6">
            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 gap-4">
                @foreach($summaryCards as $card)
                <div class="bg-{{ $card['color'] }}-500 text-white rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="text-3xl font-bold">{{ number_format($card['value']) }}</div>
                            <div class="text-sm mt-1 font-medium">{{ $card['label'] }}</div>
                            <div class="text-xs mt-0.5 opacity-80">{{ $card['sublabel'] }}</div>
                        </div>
                        <div class="text-2xl opacity-75">
                            @if($card['icon'] === 'emergency')
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            @elseif($card['icon'] === 'trending-up')
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            @elseif($card['icon'] === 'calendar')
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Daily Summary Table --}}
            <div class="bg-white dark:bg-neutral-800 rounded-lg shadow">
                <div class="p-4 border-b border-neutral-200 dark:border-neutral-700">
                    <h2 class="font-semibold">Daily Emergency Counter by Plant</h2>
                </div>
                <div class="overflow-auto max-h-96">
                    @php
                        $plants = collect($dailyData)->flatMap(fn($row) => collect($row)->keys()->filter(fn($k) => str_starts_with($k, 'plant_'))->map(fn($k) => str_replace('plant_', '', $k)))->unique()->sort()->values();
                    @endphp
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-neutral-700 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300">DATE</th>
                                @foreach($plants as $plant)
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300">Plant {{ $plant }}</th>
                                @endforeach
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @forelse($dailyData as $index => $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-neutral-700">
                                <td class="px-3 py-2 font-medium whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $row['display_date'] }}</span>
                                        <span class="text-xs text-gray-400">{{ $row['day_name'] }}</span>
                                    </div>
                                </td>
                                @foreach($plants as $plant)
                                <td class="px-3 py-2 text-center {{ ($row['plant_' . $plant] ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                    {{ number_format($row['plant_' . $plant] ?? 0) }}
                                </td>
                                @endforeach
                                <td class="px-3 py-2 text-right font-bold text-red-600">{{ number_format($row['total']) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $plants->count() + 2 }}" class="px-4 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="text-sm">No data available</p>
                                        <p class="text-xs text-gray-400 mt-1">Try adjusting your date range</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
