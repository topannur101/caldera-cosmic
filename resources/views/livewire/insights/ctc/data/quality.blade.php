<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

new #[Layout('layouts.app')] class extends Component {

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public string $device_id = '';

    #[Url]
    public string $model = '';

    #[Url]
    public string $recipe_id = '';

    public array $devices = [];
    public array $models = [];
    public array $recipes = [];
    public array $qualityMetrics = [];
    public array $trendData = [];
    public array $recipePerformance = [];
    public array $workerDecisions = [];

    public function mount()
    {
        if (!$this->start_at || !$this->end_at) {
            $this->start_at = now()->subMonth()->format('Y-m-d');
            $this->end_at = now()->format('Y-m-d');
        }
        
        $this->loadMockData();
    }

    private function loadMockData(): void
    {
        $this->devices = [
            ['id' => 1, 'line' => 3],
            ['id' => 2, 'line' => 4],
        ];

        $this->models = ['AF1', 'AM270', 'AM95', 'ALPHA'];
        
        $this->recipes = [
            ['id' => 6, 'name' => 'AF1 GS (ONE COLOR)'],
            ['id' => 7, 'name' => 'AF1 WS (TWO COLOR)'],
            ['id' => 8, 'name' => 'AM 270 (CENTER)'],
        ];

        $this->qualityMetrics = [
            'total_batches' => 145,
            'pass_rate' => 92.4,
            'avg_duration' => '00:28:15',
            'avg_sd_left' => 0.085,
            'avg_sd_right' => 0.089,
            'avg_mae_left' => 0.032,
            'avg_mae_right' => 0.028,
            'recommendation_follow_rate' => 78.6,
            'override_rate' => 21.4,
        ];

        $this->trendData = [
            [
                'date' => '2024-05-01',
                'pass_rate' => 94.2,
                'avg_sd' => 0.082,
                'avg_mae' => 0.029,
                'batches_count' => 23
            ],
            [
                'date' => '2024-05-02', 
                'pass_rate' => 91.7,
                'avg_sd' => 0.095,
                'avg_mae' => 0.035,
                'batches_count' => 24
            ],
            [
                'date' => '2024-05-03',
                'pass_rate' => 96.1,
                'avg_sd' => 0.078,
                'avg_mae' => 0.025,
                'batches_count' => 26
            ]
        ];

        $this->recipePerformance = [
            [
                'recipe_name' => 'AF1 GS (ONE COLOR)',
                'total_batches' => 48,
                'pass_rate' => 95.8,
                'avg_sd_left' => 0.074,
                'avg_sd_right' => 0.081,
                'avg_duration' => '00:26:30',
                'recommendation_accuracy' => 89.6
            ],
            [
                'recipe_name' => 'AF1 WS (TWO COLOR)',
                'total_batches' => 35,
                'pass_rate' => 88.6,
                'avg_sd_left' => 0.098,
                'avg_sd_right' => 0.102,
                'avg_duration' => '00:31:45',
                'recommendation_accuracy' => 71.4
            ],
            [
                'recipe_name' => 'AM 270 (CENTER)',
                'total_batches' => 29,
                'pass_rate' => 93.1,
                'avg_sd_left' => 0.069,
                'avg_sd_right' => 0.075,
                'avg_duration' => '00:29:20',
                'recommendation_accuracy' => 82.8
            ]
        ];

        $this->workerDecisions = [
            [
                'worker_name' => 'Ahmad S.',
                'worker_emp_id' => 'EMP001',
                'total_batches' => 67,
                'follow_rate' => 85.1,
                'override_rate' => 14.9,
                'avg_quality_when_follow' => 94.2,
                'avg_quality_when_override' => 89.0
            ],
            [
                'worker_name' => 'Budi W.',
                'worker_emp_id' => 'EMP002', 
                'total_batches' => 43,
                'follow_rate' => 72.1,
                'override_rate' => 27.9,
                'avg_quality_when_follow' => 91.5,
                'avg_quality_when_override' => 86.7
            ],
            [
                'worker_name' => 'Candra L.',
                'worker_emp_id' => 'EMP003',
                'total_batches' => 35,
                'follow_rate' => 80.0,
                'override_rate' => 20.0,
                'avg_quality_when_follow' => 93.8,
                'avg_quality_when_override' => 91.4
            ]
        ];
    }

    public function getFilteredData(): array
    {
        // Apply filters to mock data
        $filteredMetrics = $this->qualityMetrics;
        $filteredTrends = $this->trendData;
        $filteredRecipes = $this->recipePerformance;
        $filteredWorkers = $this->workerDecisions;

        // In real implementation, apply date range, device, model, recipe filters
        
        return [
            'metrics' => $filteredMetrics,
            'trends' => $filteredTrends,
            'recipes' => $filteredRecipes,
            'workers' => $filteredWorkers
        ];
    }

    public function resetFilters(): void
    {
        $this->reset(['device_id', 'model', 'recipe_id']);
    }

    public function export(): void
    {
        $this->js('toast("' . __('Laporan kualitas diunduh') . '", { type: "success" })');
    }

    public function with(): array
    {
        return $this->getFilteredData();
    }
};

?>

<div>
    {{-- Filter Panel --}}
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
                                <x-dropdown-link href="#" wire:click.prevent="$set('start_at', '{{ now()->subWeek()->format('Y-m-d') }}'); $set('end_at', '{{ now()->format('Y-m-d') }}')">
                                    {{ __('Minggu ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="$set('start_at', '{{ now()->subMonth()->format('Y-m-d') }}'); $set('end_at', '{{ now()->format('Y-m-d') }}')">
                                    {{ __('Bulan ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="$set('start_at', '{{ now()->subMonths(3)->format('Y-m-d') }}'); $set('end_at', '{{ now()->format('Y-m-d') }}')">
                                    {{ __('3 Bulan') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" type="date" class="w-36"></x-text-input>
                    <x-text-input wire:model.live="end_at" type="date" class="w-36"></x-text-input>
                </div>
            </div>
            
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            
            <div class="grid grid-cols-2 lg:flex gap-3">
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-select wire:model.live="device_id" class="w-full lg:w-20">
                        <option value=""></option>
                        @foreach($devices as $device)
                            <option value="{{ $device['line'] }}">{{ $device['line'] }}</option>
                        @endforeach
                    </x-select>
                </div>
                
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Model') }}</label>
                    <x-select wire:model.live="model" class="w-full lg:w-24">
                        <option value=""></option>
                        @foreach($models as $modelOption)
                            <option value="{{ $modelOption }}">{{ $modelOption }}</option>
                        @endforeach
                    </x-select>
                </div>
                
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Resep') }}</label>
                    <x-select wire:model.live="recipe_id" class="w-full lg:w-32">
                        <option value=""></option>
                        @foreach($recipes as $recipe)
                            <option value="{{ $recipe['id'] }}">{{ $recipe['name'] }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
            
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            
            <div class="grow flex justify-between gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div class="text-sm font-medium">{{ $metrics['total_batches'] . ' ' . __('batch dianalisis') }}</div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <x-secondary-button wire:click="resetFilters" type="button">
                        <i class="icon-filter-x me-1"></i>{{ __('Reset') }}
                    </x-secondary-button>
                    <x-secondary-button wire:click="export" type="button">
                        <i class="icon-download me-1"></i>{{ __('Export') }}
                    </x-secondary-button>
                </div>
            </div>
        </div>
    </div>

    {{-- Overview Metrics Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs text-neutral-500 uppercase">{{ __('Tingkat Lulus') }}</div>
            <div class="text-2xl font-bold {{ $metrics['pass_rate'] >= 90 ? 'text-green-600' : 'text-yellow-600' }}">
                {{ number_format($metrics['pass_rate'], 1) }}%
            </div>
            <div class="text-xs text-neutral-400">{{ $metrics['total_batches'] . ' ' . __('batch') }}</div>
        </div>
        
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs text-neutral-500 uppercase">{{ __('Rata-rata SD') }}</div>
            <div class="text-2xl font-bold font-mono">
                {{ number_format($metrics['avg_sd_left'], 3) }} | {{ number_format($metrics['avg_sd_right'], 3) }}
            </div>
            <div class="text-xs text-neutral-400">{{ __('Kiri | Kanan') }}</div>
        </div>
        
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs text-neutral-500 uppercase">{{ __('Rata-rata MAE') }}</div>
            <div class="text-2xl font-bold font-mono">
                {{ number_format($metrics['avg_mae_left'], 3) }} | {{ number_format($metrics['avg_mae_right'], 3) }}
            </div>
            <div class="text-xs text-neutral-400">{{ __('Kiri | Kanan') }}</div>
        </div>
        
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-xs text-neutral-500 uppercase">{{ __('Ikuti Rekomendasi') }}</div>
            <div class="text-2xl font-bold {{ $metrics['recommendation_follow_rate'] >= 80 ? 'text-green-600' : 'text-yellow-600' }}">
                {{ number_format($metrics['recommendation_follow_rate'], 1) }}%
            </div>
            <div class="text-xs text-neutral-400">{{ __('Override: ') . number_format($metrics['override_rate'], 1) }}%</div>
        </div>
    </div>

    {{-- Analysis Sections --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recipe Performance --}}
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
            <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                <h3 class="text-lg font-medium">{{ __('Performa Resep') }}</h3>
            </div>
            <div class="overflow-auto">
                <table class="table table-sm text-sm">
                    <tr class="text-xs uppercase text-neutral-500 border-b border-neutral-200 dark:border-neutral-700">
                        <th class="text-left px-6 py-3">{{ __('Resep') }}</th>
                        <th class="text-center px-3 py-3">{{ __('Batch') }}</th>
                        <th class="text-center px-3 py-3">{{ __('Lulus') }}</th>
                        <th class="text-center px-3 py-3">{{ __('SD Avg') }}</th>
                        <th class="text-center px-3 py-3">{{ __('Akurasi') }}</th>
                    </tr>
                    @foreach($recipePerformance as $recipePerformanceRow)
                        <tr class="border-b border-neutral-100 dark:border-neutral-700">
                            <td class="px-6 py-3 max-w-32 truncate">{{ $recipePerformanceRow['recipe_name'] }}</td>
                            <td class="text-center px-3 py-3 font-mono">{{ $recipePerformanceRow['total_batches'] }}</td>
                            <td class="text-center px-3 py-3">
                                <span class="font-mono {{ $recipePerformanceRow['pass_rate'] >= 90 ? 'text-green-600' : 'text-yellow-600' }}">
                                    {{ number_format($recipePerformanceRow['pass_rate'], 1) }}%
                                </span>
                            </td>
                            <td class="text-center px-3 py-3 font-mono text-xs">
                                {{ number_format($recipePerformanceRow['avg_sd_left'], 3) }} | {{ number_format($recipePerformanceRow['avg_sd_right'], 3) }}
                            </td>
                            <td class="text-center px-3 py-3">
                                <span class="font-mono {{ $recipePerformanceRow['recommendation_accuracy'] >= 80 ? 'text-green-600' : 'text-yellow-600' }}">
                                    {{ number_format($recipePerformanceRow['recommendation_accuracy'], 1) }}%
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>

        {{-- Worker Decision Analysis --}}
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
            <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                <h3 class="text-lg font-medium">{{ __('Analisis Keputusan Operator') }}</h3>
            </div>
            <div class="overflow-auto">
                <table class="table table-sm text-sm">
                    <tr class="text-xs uppercase text-neutral-500 border-b border-neutral-200 dark:border-neutral-700">
                        <th class="text-left px-6 py-3">{{ __('Operator') }}</th>
                        <th class="text-center px-3 py-3">{{ __('Batch') }}</th>
                        <th class="text-center px-3 py-3">{{ __('Ikuti') }}</th>
                        <th class="text-center px-3 py-3">{{ __('Q. Ikuti') }}</th>
                        <th class="text-center px-3 py-3">{{ __('Q. Override') }}</th>
                    </tr>
                    @foreach($workers as $worker)
                        <tr class="border-b border-neutral-100 dark:border-neutral-700">
                            <td class="px-6 py-3">
                                <div>{{ $worker['worker_name'] }}</div>
                                <div class="text-xs text-neutral-400">{{ $worker['worker_emp_id'] }}</div>
                            </td>
                            <td class="text-center px-3 py-3 font-mono">{{ $worker['total_batches'] }}</td>
                            <td class="text-center px-3 py-3">
                                <span class="font-mono {{ $worker['follow_rate'] >= 80 ? 'text-green-600' : 'text-yellow-600' }}">
                                    {{ number_format($worker['follow_rate'], 1) }}%
                                </span>
                            </td>
                            <td class="text-center px-3 py-3 font-mono text-green-600">
                                {{ number_format($worker['avg_quality_when_follow'], 1) }}%
                            </td>
                            <td class="text-center px-3 py-3 font-mono text-yellow-600">
                                {{ number_format($worker['avg_quality_when_override'], 1) }}%
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>

    {{-- Daily Trend Chart --}}
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg mt-6">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium">{{ __('Tren Kualitas Harian') }}</h3>
        </div>
        <div class="p-6">
            <div id="quality-trend-chart" class="h-64 flex items-center justify-center bg-neutral-50 dark:bg-neutral-700 rounded">
                <div class="text-center">
                    <div class="text-lg font-medium mb-2">{{ __('Grafik Tren Kualitas') }}</div>
                    <div class="text-sm text-neutral-500">
                        {{ __('Menampilkan tren tingkat lulus, SD rata-rata, dan MAE rata-rata') }}
                    </div>
                    <div class="text-xs text-neutral-400 mt-2">
                        {{ count($trends) . ' ' . __('hari data') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>