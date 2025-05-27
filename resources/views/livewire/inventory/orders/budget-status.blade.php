<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InvOrderBudget;
use App\Models\InvOrderItem;

new class extends Component {

    public array $area_ids = [];
    public array $budgets = [];
    public bool $show_component = false;
    
    // Modal properties
    public int $selected_budget_id = 0;
    public array $selected_budget = [];
    public float $new_balance = 0;

    public function mount($area_ids = [])
    {
        $this->area_ids = $area_ids;
        $this->loadBudgets();
    }

    #[On('order-item-updated')]
    #[On('order-item-created')]
    #[On('order-items-finalized')]
    #[On('order-items-bulk-edited')]
    public function refreshBudgets()
    {
        $this->loadBudgets();
    }

    #[On('area-ids-updated')]
    public function updateAreaIds($areaIds)
    {
        $this->area_ids = $areaIds;
        $this->loadBudgets();
    }

    private function loadBudgets()
    {
        // Only show when exactly one area is selected
        if (count($this->area_ids) === 1) {
            $this->show_component = true;
            
            $budgets = InvOrderBudget::where('inv_area_id', $this->area_ids[0])
                ->where('is_active', true)
                ->with('inv_curr')
                ->orderBy('name')
                ->get();

            $this->budgets = $budgets->map(function ($budget) {
                $allocatedAmount = $budget->allocated_amount;
                $availableAmount = $budget->available_budget;
                $usagePercentage = $budget->balance > 0 ? ($allocatedAmount / $budget->balance) * 100 : 0;
                
                return [
                    'id' => $budget->id,
                    'name' => $budget->name,
                    'balance' => $budget->balance,
                    'allocated_amount' => $allocatedAmount,
                    'available_amount' => $availableAmount,
                    'usage_percentage' => $usagePercentage,
                    'currency_name' => $budget->inv_curr->name,
                    'status_color' => $this->getStatusColor($usagePercentage),
                ];
            })->toArray();
        } else {
            $this->show_component = false;
            $this->budgets = [];
        }
    }

    private function getStatusColor($percentage)
    {
        if ($percentage >= 95) {
            return 'red';
        } elseif ($percentage >= 80) {
            return 'yellow';
        } else {
            return 'caldy';
        }
    }

    public function openBudgetModal($budgetId)
    {
        $budget = collect($this->budgets)->firstWhere('id', $budgetId);
        if ($budget) {
            $this->selected_budget_id = $budgetId;
            $this->selected_budget = $budget;
            $this->new_balance = $budget['balance'];
            
            // Load order items using this budget
            $orderItems = InvOrderItem::whereNull('inv_order_id')
                ->where('inv_order_budget_id', $budgetId)
                ->with('inv_curr')
                ->get();
                
            $this->selected_budget['order_items'] = $orderItems->map(function ($item) {
                return [
                    'name' => $item->name,
                    'qty' => $item->qty,
                    'uom' => $item->uom,
                    'amount_budget' => $item->amount_budget,
                    'purpose' => $item->purpose,
                ];
            })->toArray();
            
            $this->js('$dispatch("open-modal", "budget-details")');
        }
    }

    public function updateBalance()
    {
        $this->validate([
            'new_balance' => ['required', 'numeric', 'min:0', 'max:1000000000'],
        ]);

        try {
            $budget = InvOrderBudget::find($this->selected_budget_id);
            if ($budget) {
                $budget->update(['balance' => $this->new_balance]);
                $this->loadBudgets();
                $this->js('$dispatch("close")');
                $this->js('toast("' . __('Saldo budget berhasil diperbarui') . '", { type: "success" })');
            }
        } catch (\Exception $e) {
            $this->js('toast("' . __('Terjadi kesalahan saat memperbarui saldo') . '", { type: "danger" })');
        }
    }

    public function with(): array
    {
        return [
            'budgets' => $this->budgets,
        ];
    }
};

?>

<div>
    @if($show_component)
        {{-- Budget Details Modal --}}
        <x-modal name="budget-details" focusable>
            <div class="p-6">
                <div class="flex justify-between items-start mb-6">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Detail Budget') }} - {{ $selected_budget['name'] ?? '' }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')">
                        <i class="icon-x"></i>
                    </x-text-button>
                </div>

                @if($selected_budget)
                    {{-- Budget Summary --}}
                    <div class="grid grid-cols-2 gap-6 mb-6">
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Total Budget') }}:</span>
                                <span class="font-mono">{{ $selected_budget['currency_name'] ?? '' }} {{ number_format($selected_budget['balance'] ?? 0, 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Dialokasikan') }}:</span>
                                <span class="font-mono text-orange-600 dark:text-orange-400">{{ $selected_budget['currency_name'] ?? '' }} {{ number_format($selected_budget['allocated_amount'] ?? 0, 0) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-neutral-300 dark:border-neutral-600 pt-2">
                                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Tersedia') }}:</span>
                                <span class="font-mono font-medium">{{ $selected_budget['currency_name'] ?? '' }} {{ number_format($selected_budget['available_amount'] ?? 0, 0) }}</span>
                            </div>
                        </div>

                        {{-- Balance Edit --}}
                        <div>
                            <label for="new_balance" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Perbarui Saldo') }}</label>
                            <div class="flex gap-2">
                                <x-text-input wire:model="new_balance" id="new_balance" type="number" step="0.01" min="0" class="flex-1" />
                                <x-primary-button type="button" wire:click="updateBalance">
                                    {{ __('Simpan') }}
                                </x-primary-button>
                            </div>
                            @error('new_balance')
                                <x-input-error :messages="$message" class="mt-1" />
                            @enderror
                        </div>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Penggunaan Budget') }}</span>
                            <span class="text-sm font-mono 
                                {{ ($selected_budget['status_color'] ?? '') === 'red' ? 'text-red-600 dark:text-red-400' : 
                                   (($selected_budget['status_color'] ?? '') === 'yellow' ? 'text-yellow-600 dark:text-yellow-400' : 
                                    'text-caldy-600 dark:text-caldy-400') }}">
                                {{ number_format($selected_budget['usage_percentage'] ?? 0, 1) }}%
                            </span>
                        </div>
                        
                        <div class="w-full bg-neutral-200 rounded-full h-3 dark:bg-neutral-700">
                            <div class="h-3 rounded-full transition-all duration-300
                                {{ ($selected_budget['status_color'] ?? '') === 'red' ? 'bg-red-500' : 
                                   (($selected_budget['status_color'] ?? '') === 'yellow' ? 'bg-yellow-500' : 
                                    'bg-caldy-500') }}"
                                style="width: {{ min($selected_budget['usage_percentage'] ?? 0, 100) }}%">
                            </div>
                        </div>
                    </div>

                    {{-- Order Items using this budget --}}
                    @if(isset($selected_budget['order_items']) && count($selected_budget['order_items']) > 0)
                        <div>
                            <h3 class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __('Pesanan Terbuka') }}</h3>
                            <div class="max-h-60 overflow-y-auto space-y-2">
                                @foreach($selected_budget['order_items'] as $item)
                                    <div class="border border-neutral-200 dark:border-neutral-700 rounded p-3 text-sm">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium truncate">{{ $item['name'] }}</div>
                                                <div class="text-xs text-neutral-500 truncate">{{ $item['purpose'] }}</div>
                                            </div>
                                            <div class="text-right ml-3">
                                                <div class="font-medium">{{ $item['qty'] }} {{ $item['uom'] }}</div>
                                                <div class="text-xs text-neutral-500">{{ number_format($item['amount_budget'], 0) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4 text-neutral-500 dark:text-neutral-400">
                            <i class="icon-package text-xl mb-2 opacity-50"></i>
                            <div class="text-sm">{{ __('Tidak ada pesanan terbuka menggunakan budget ini') }}</div>
                        </div>
                    @endif
                @endif
            </div>
        </x-modal>

        {{-- Budget Cards --}}
        @if(count($budgets) > 0)
            <div class="overflow-x-auto pb-2">
                <div class="flex gap-3 min-w-max">
                    @foreach($budgets as $budget)
                        <div wire:click="openBudgetModal({{ $budget['id'] }})" 
                             class="flex-shrink-0 w-48 p-3 border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 hover:shadow-md transition-shadow cursor-pointer">
                            
                            {{-- Budget Name --}}
                            <div class="font-medium text-neutral-900 dark:text-neutral-100 truncate mb-1">
                                {{ $budget['name'] }}
                            </div>

                            {{-- Currency and Remaining --}}
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-xs text-neutral-500 font-mono bg-neutral-100 dark:bg-neutral-700 px-2 py-1 rounded">
                                    {{ $budget['currency_name'] }}
                                </span>
                                <span class="text-sm font-mono font-medium">
                                    {{ number_format($budget['available_amount'], 0) }}
                                </span>
                            </div>

                            {{-- Progress Bar --}}
                            <div class="space-y-1">
                                <div class="w-full bg-neutral-200 rounded-full h-1.5 dark:bg-neutral-700">
                                    <div class="h-1.5 rounded-full transition-all duration-300
                                        {{ $budget['status_color'] === 'red' ? 'bg-red-500' : 
                                           ($budget['status_color'] === 'yellow' ? 'bg-yellow-500' : 
                                            'bg-caldy-500') }}"
                                        style="width: {{ min($budget['usage_percentage'], 100) }}%">
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <span class="text-xs font-mono 
                                        {{ $budget['status_color'] === 'red' ? 'text-red-600 dark:text-red-400' : 
                                           ($budget['status_color'] === 'yellow' ? 'text-yellow-600 dark:text-yellow-400' : 
                                            'text-caldy-600 dark:text-caldy-400') }}">
                                        {{ number_format($budget['usage_percentage'], 1) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>