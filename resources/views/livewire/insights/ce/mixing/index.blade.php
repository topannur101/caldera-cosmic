<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\InvCeMixingLog;
use App\Traits\HasDateRangeFilter;
use Livewire\Attributes\Url;
use Carbon\Carbon;


new #[Layout("layouts.app")] class extends Component {
    use WithPagination;
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public string $status = "";

    public $logsData;
    public $condition = '';

    protected $listeners = ['update' => 'refreshData'];

    public function mount() {
        // set default date today
        if (!$this->start_at) {
            $this->start_at = Carbon::now()->toDateString();
        }
        if (!$this->end_at) {
            $this->end_at = Carbon::now()->toDateString();
        }

        $this->refreshData();
    }

    public function refreshData() {
        $query = InvCeMixingLog::with(['recipe', 'user'])->select('id', 'recipe_id', 'user_id', 'batch_number', 'duration', 'notes', 'status', 'created_at');

        if ($this->condition) {
            $query->where('status', $this->condition);
        }

        if ($this->start_at) {
            $query->whereDate('created_at', '>=', $this->start_at);
        }

        if ($this->end_at) {
            $query->whereDate('created_at', '<=', $this->end_at);
        }

        $this->logsData = $query->get();
    }

    public function updatedStartAt()
    {
        $this->refreshData();
    }

    public function updatedEndAt()
    {
        $this->refreshData();
    }

    public function updatedCondition()
    {
        $this->refreshData();
    }
}

?>

<x-slot name="header">
    <x-nav-insights-ce-mix></x-nav-insights-ce-mix>
</x-slot>

<div class="py-5 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 gap-4">
    <div class="p-0 sm:p-1 mb-6">
        <div class="mt-5 mb-6 flex gap-2 items-center">
            <span class="icon-database text-2xl"></span>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                {{ __("Raw Data") }}
            </h1>
        </div>
        <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div>
                <div class="flex mb-2 text-xs text-neutral-500">
                    <div class="flex">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <x-text-button class="uppercase ml-3">
                                    {{ __("Rentang") }}
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
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("status") }}</label>
                    <x-select wire:model.live="condition" class="w-full">
                        <option value="">{{ __("Semua") }}</option>
                        <option value="success">{{ __("Sukses") }}</option>
                        <option value="failed">{{ __("Gagal") }}</option>
                    </x-select>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <!-- total entri -->
            <div class="flex flex-col gap-1">
                <span class="text-xs text-neutral-500 uppercase">{{ __("Total Entri") }}</span>
                <span class="text-lg font-semibold">{{ count($logsData) }}</span>
            </div>
            <div class="grow flex justify-between gap-x-2 items-center">
                <div class="flex gap-x-2">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <x-text-button><i class="icon-ellipsis-vertical"></i></x-text-button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link href="#" wire:click.prevent="download('counts')">
                                <i class="icon-download me-2"></i>
                                {{ __("CSV Data") }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>

    <!-- table log ce mixing -->
     <div class="w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 overflow-x-auto">
        <table class="table w-full text-left text-sm text-neutral-500 dark:text-neutral-400">
            <thead>
                <tr class="bg-neutral-100 dark:bg-neutral-700">
                    <th class="">Batch Number</th>
                    <th class="">Recipe</th>
                    <th class="">Duration</th>
                    <th class="">Notes</th>
                    <th class="">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logsData as $log)
                    <tr>
                        <td class="">{{ $log->batch_number }}</td>
                        <td class="">{{ $log->recipe->chemical->name }} + {{ $log->recipe->hardener->name }}</td>
                        <td class="">{{ $log->duration }}</td>
                        <td class="">{{ $log->notes }}</td>
                        <td class="">{{ $log->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
     </div>
</div>

