<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public string $device_id = '';

    #[Url]
    public string $model = '';

    #[Url]
    public string $mcs = '';

    #[Url]
    public string $recipe_id = '';

    #[Url]
    public string $quality_status = '';

    public int $perPage = 20;
    public array $devices = [];
    public array $models = [];
    public array $mcsOptions = [];
    public array $recipes = [];
    public array $batches = [];

    public function mount()
    {
        if (!$this->start_at || !$this->end_at) {
            $this->start_at = now()->subWeek()->format('Y-m-d');
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
        $this->mcsOptions = ['GS', 'WS', 'RS'];
        $this->recipes = [
            ['id' => 6, 'name' => 'AF1 GS (ONE COLOR)'],
            ['id' => 7, 'name' => 'AF1 WS (TWO COLOR)'],
            ['id' => 8, 'name' => 'AM 270 (CENTER)'],
        ];

        $this->batches = [
            [
                'id' => 1,
                'rubber_batch_code' => 'RB240501A',
                'device_line' => 3,
                'recipe_name' => 'AF1 GS (ONE COLOR)',
                'model' => 'AF1',
                'mcs' => 'GS',
                'started_at' => '2024-05-01 08:15:00',
                'ended_at' => '2024-05-01 08:45:30',
                'duration' => '00:30:30',
                'measurement_count' => 1830,
                'avg_left' => 3.05,
                'avg_right' => 3.02,
                'sd_left' => 0.08,
                'sd_right' => 0.09,
                'correction_rate' => 0.15,
                'quality_status' => 'pass',
                'worker_override' => false,
                'shift' => 1
            ],
            [
                'id' => 2,
                'rubber_batch_code' => 'RB240501B',
                'device_line' => 4,
                'recipe_name' => 'AF1 WS (TWO COLOR)',
                'model' => 'AF1',
                'mcs' => 'WS',
                'started_at' => '2024-05-01 09:00:00',
                'ended_at' => '2024-05-01 09:28:15',
                'duration' => '00:28:15',
                'measurement_count' => 1695,
                'avg_left' => 3.12,
                'avg_right' => 3.08,
                'sd_left' => 0.12,
                'sd_right' => 0.11,
                'correction_rate' => 0.22,
                'quality_status' => 'fail',
                'worker_override' => true,
                'shift' => 1
            ],
            [
                'id' => 3,
                'rubber_batch_code' => 'RB240430C',
                'device_line' => 3,
                'recipe_name' => 'AM 270 (CENTER)',
                'model' => 'AM270',
                'mcs' => 'RS',
                'started_at' => '2024-04-30 14:30:00',
                'ended_at' => '2024-04-30 15:05:45',
                'duration' => '00:35:45',
                'measurement_count' => 2145,
                'avg_left' => 2.78,
                'avg_right' => 2.82,
                'sd_left' => 0.06,
                'sd_right' => 0.07,
                'correction_rate' => 0.08,
                'quality_status' => 'pass',
                'worker_override' => false,
                'shift' => 2
            ]
        ];
    }

    public function getFilteredBatches(): array
    {
        return array_filter($this->batches, function($batch) {
            // Apply filters
            if ($this->device_id && $batch['device_line'] != $this->device_id) return false;
            if ($this->model && $batch['model'] !== $this->model) return false;
            if ($this->mcs && $batch['mcs'] !== $this->mcs) return false;
            if ($this->quality_status && $batch['quality_status'] !== $this->quality_status) return false;
            
            // Date filtering would be applied here
            return true;
        });
    }

    public function showBatchDetail(int $batchId): void
    {
        $this->dispatch('open-modal', 'batch-detail');
        $this->dispatch('batch-detail-load', ['id' => $batchId]);
    }

    public function resetFilters(): void
    {
        $this->reset(['device_id', 'model', 'mcs', 'recipe_id', 'quality_status']);
    }

    public function download(): void
    {
        $this->js('toast("' . __('Unduhan dimulai...') . '", { type: "success" })');
    }

    public function with(): array
    {
        $filteredBatches = $this->getFilteredBatches();
        
        return [
            'filteredBatches' => $filteredBatches,
            'totalBatches' => count($filteredBatches)
        ];
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
                                <x-dropdown-link href="#" wire:click.prevent="$set('start_at', '{{ now()->format('Y-m-d') }}'); $set('end_at', '{{ now()->format('Y-m-d') }}')">
                                    {{ __('Hari ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="$set('start_at', '{{ now()->subDay()->format('Y-m-d') }}'); $set('end_at', '{{ now()->subDay()->format('Y-m-d') }}')">
                                    {{ __('Kemarin') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="$set('start_at', '{{ now()->startOfWeek()->format('Y-m-d') }}'); $set('end_at', '{{ now()->endOfWeek()->format('Y-m-d') }}')">
                                    {{ __('Minggu ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="$set('start_at', '{{ now()->subWeek()->startOfWeek()->format('Y-m-d') }}'); $set('end_at', '{{ now()->subWeek()->endOfWeek()->format('Y-m-d') }}')">
                                    {{ __('Minggu lalu') }}
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
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('MCS') }}</label>
                    <x-select wire:model.live="mcs" class="w-full lg:w-20">
                        <option value=""></option>
                        @foreach($mcsOptions as $mcsOption)
                            <option value="{{ $mcsOption }}">{{ $mcsOption }}</option>
                        @endforeach
                    </x-select>
                </div>
                
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kualitas') }}</label>
                    <x-select wire:model.live="quality_status" class="w-full lg:w-24">
                        <option value=""></option>
                        <option value="pass">{{ __('Lulus') }}</option>
                        <option value="fail">{{ __('Gagal') }}</option>
                    </x-select>
                </div>
            </div>
            
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            
            <div class="grow flex justify-between gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div wire:loading.class="hidden">{{ $totalBatches . ' ' . __('ditemukan') }}</div>
                        <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                            <div class="relative w-3">
                                <x-spinner class="sm mono"></x-spinner>
                            </div>
                            <div>{{ __('Memuat...') }}</div>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <x-secondary-button wire:click="resetFilters" type="button">
                        <i class="icon-filter-x me-1"></i>{{ __('Reset') }}
                    </x-secondary-button>
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <x-text-button><i class="icon-ellipsis-vertical"></i></x-text-button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link href="#" wire:click.prevent="download">
                                <i class="icon-download me-2"></i>{{ __('CSV Export') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>

    {{-- Batch Table --}}
    @if (!count($filteredBatches))
        @if (!$start_at || !$end_at)
            <div class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="icon-calendar"></i>
                </div>
                <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih rentang tanggal') }}</div>
            </div>
        @else
            <div class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="icon-ghost"></i>
                </div>
                <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}</div>
            </div>
        @endif
    @else
        <div class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('Batch') }}</th>
                        <th>{{ __('Line') }}</th>
                        <th>{{ __('Model') }}</th>
                        <th>{{ __('MCS') }}</th>
                        <th>{{ __('Resep') }}</th>
                        <th>{{ __('Shift') }}</th>
                        <th>{{ __('Override') }}</th>
                        <th>{{ __('Kualitas') }}</th>
                        <th>{{ __('AVG') }}</th>
                        <th>{{ __('SD') }}</th>
                        <th>{{ __('Durasi') }}</th>
                        <th>{{ __('Mulai') }}</th>
                    </tr>
                    @foreach ($filteredBatches as $batch)
                        <tr wire:key="batch-tr-{{ $batch['id'] }}" tabindex="0"
                            wire:click="showBatchDetail({{ $batch['id'] }})"
                            class="cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-700">
                            <td>{{ $batch['rubber_batch_code'] }}</td>
                            <td>{{ $batch['device_line'] }}</td>
                            <td>{{ $batch['model'] }}</td>
                            <td>{{ $batch['mcs'] }}</td>
                            <td class="max-w-32 truncate">{{ $batch['recipe_name'] }}</td>
                            <td>{{ $batch['shift'] }}</td>
                            <td>
                                @if($batch['worker_override'])
                                    <i class="icon-alert-circle text-yellow-500" title="{{ __('Operator override') }}"></i>
                                @else
                                    <i class="icon-check-circle text-green-500" title="{{ __('Mengikuti rekomendasi') }}"></i>
                                @endif
                            </td>
                            <td>
                                @if($batch['quality_status'] === 'pass')
                                    <span class="inline-flex px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">{{ __('Lulus') }}</span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">{{ __('Gagal') }}</span>
                                @endif
                            </td>
                            <td class="font-mono">{{ number_format($batch['avg_left'], 2) }} | {{ number_format($batch['avg_right'], 2) }}</td>
                            <td class="font-mono">{{ number_format($batch['sd_left'], 2) }} | {{ number_format($batch['sd_right'], 2) }}</td>
                            <td class="font-mono">{{ $batch['duration'] }}</td>
                            <td class="font-mono">{{ $batch['started_at'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif

    {{-- Batch Detail Modal --}}
    <x-modal name="batch-detail" maxWidth="3xl">
        <livewire:insights.ctc.data.batch-detail />
    </x-modal>
</div>