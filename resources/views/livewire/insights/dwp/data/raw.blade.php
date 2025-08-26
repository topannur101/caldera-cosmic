<?php

use App\Models\InsDwpCount;
use Carbon\Carbon;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $selectedLine = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount()
    {
        // Set default date range to current week
        $this->dateFrom = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->dateTo = Carbon::now()->endOfWeek()->format('Y-m-d');
    }

    public function getAvailableLines()
    {
        return InsDwpCount::distinct('line')
            ->orderBy('line')
            ->pluck('line')
            ->toArray();
    }

    public function getCounts()
    {
        $query = InsDwpCount::query();

        // Filter by line if selected
        if ($this->selectedLine) {
            $query->where('line', $this->selectedLine);
        }

        // Filter by date range
        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(50);
    }

    public function refreshData()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->selectedLine = '';
        $this->dateFrom = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->dateTo = Carbon::now()->endOfWeek()->format('Y-m-d');
        $this->resetPage();
    }
};

?>

<div>
    <div class="bg-white dark:bg-neutral-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Raw Data DWP") }}
            </h3>
        
        <!-- Filters -->
        <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                    {{ __("Line") }}
                </label>
                <select wire:model.live="selectedLine" class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                    <option value="">{{ __("All Lines") }}</option>
                    @foreach($this->getAvailableLines() as $line)
                        <option value="{{ $line }}">{{ $line }}</option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                    {{ __("Date From") }}
                </label>
                <input type="date" wire:model.live="dateFrom" class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                    {{ __("Date To") }}
                </label>
                <input type="date" wire:model.live="dateTo" class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
            </div>
            
            <div class="flex items-end gap-2">
                <button wire:click="refreshData" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                    {{ __("Refresh") }}
                </button>
                <button wire:click="resetFilters" class="px-4 py-2 bg-neutral-600 text-white text-sm rounded-md hover:bg-neutral-700">
                    {{ __("Reset") }}
                </button>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead class="bg-neutral-50 dark:bg-neutral-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                        {{ __("Timestamp") }}
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                        {{ __("Line") }}
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                        {{ __("Cumulative") }}
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                        {{ __("Incremental") }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse($this->getCounts() as $count)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900 dark:text-neutral-100">
                            {{ $count->created_at->format('Y-m-d H:i:s') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900 dark:text-neutral-100">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                {{ $count->line }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900 dark:text-neutral-100">
                            {{ number_format($count->cumulative) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900 dark:text-neutral-100">
                            @if($count->incremental > 0)
                                <span class="text-green-600 dark:text-green-400">+{{ number_format($count->incremental) }}</span>
                            @else
                                {{ number_format($count->incremental) }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-neutral-500 dark:text-neutral-400">
                            {{ __("No data found") }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($this->getCounts()->hasPages())
        <div class="px-6 py-4 border-t border-neutral-200 dark:border-neutral-700">
            {{ $this->getCounts()->links() }}
        </div>
    @endif
    </div>
</div>