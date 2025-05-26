<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InvOrderItem;
use App\Models\InvOrder;
use App\Models\InvOrderBudgetSnapshot;
use App\Models\InvOrderBudget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public array $order_item_ids = [];
    public array $order_items = [];
    public array $budget_summary = [];
    public string $order_number = '';
    public string $notes = '';
    public bool $can_finalize = false;
    public string $validation_message = '';

    #[On('finalize-order-items')]
    public function loadOrderItems(array $orderItemIds)
    {
        $this->order_item_ids = $orderItemIds;
        
        $orderItems = InvOrderItem::with([
            'inv_area',
            'inv_order_budget',
            'inv_order_budget.inv_curr'
        ])
        ->whereIn('id', $orderItemIds)
        ->whereNull('inv_order_id')
        ->get();

        $this->order_items = $orderItems->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'desc' => $item->desc,
                'code' => $item->code,
                'qty' => $item->qty,
                'uom' => $item->uom,
                'amount_budget' => $item->amount_budget,
                'area_name' => $item->inv_area->name,
                'area_id' => $item->inv_area_id,
                'budget_name' => $item->inv_order_budget->name,
                'budget_id' => $item->inv_order_budget_id,
                'budget_currency' => $item->inv_order_budget->inv_curr->name,
            ];
        })->toArray();

        // Validate same area requirement
        $areas = collect($this->order_items)->pluck('area_id')->unique();
        if ($areas->count() > 1) {
            $this->can_finalize = false;
            $this->validation_message = __('Semua butir pesanan harus dari area yang sama untuk dapat difinalisasi.');
        } else {
            $this->can_finalize = true;
            $this->validation_message = '';
            
            // Generate order number
            $this->order_number = $this->generateOrderNumber();
            
            // Calculate budget summary
            $this->calculateBudgetSummary();
        }
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        
        $lastOrder = InvOrder::whereDate('created_at', now())
            ->orderBy('id', 'desc')
            ->first();
            
        $sequence = $lastOrder ? (int) substr($lastOrder->order_number, -4) + 1 : 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    private function calculateBudgetSummary()
    {
        $budgetGroups = collect($this->order_items)->groupBy('budget_id');
        
        $this->budget_summary = $budgetGroups->map(function ($items, $budgetId) {
            $firstItem = $items->first();
            $totalAllocation = $items->sum('amount_budget');
            
            // Get current budget balance
            $budget = InvOrderBudget::find($budgetId);
            $currentBalance = $budget ? $budget->balance : 0;
            $allocatedAmount = $budget ? $budget->allocated_amount : 0;
            $availableBalance = $currentBalance - $allocatedAmount;
            
            return [
                'budget_id' => $budgetId,
                'budget_name' => $firstItem['budget_name'],
                'budget_currency' => $firstItem['budget_currency'],
                'item_count' => $items->count(),
                'total_allocation' => $totalAllocation,
                'current_balance' => $currentBalance,
                'available_balance' => $availableBalance,
                'balance_after' => $availableBalance - $totalAllocation,
                'is_sufficient' => $availableBalance >= $totalAllocation,
            ];
        })->values()->toArray();

        // Check if all budgets have sufficient funds
        $insufficientBudgets = collect($this->budget_summary)->where('is_sufficient', false);
        if ($insufficientBudgets->count() > 0) {
            $this->can_finalize = false;
            $this->validation_message = __('Budget tidak mencukupi untuk beberapa item yang dipilih.');
        }
    }

    public function finalize()
    {
        if (!$this->can_finalize) {
            $this->js('toast("' . $this->validation_message . '", { type: "danger" })');
            return;
        }

        $this->order_number = strtoupper(trim($this->order_number));
        $this->notes = trim($this->notes);

        $this->validate([
            'order_number' => ['required', 'unique:inv_orders,order_number', 'max:20'],
            'notes' => ['nullable', 'max:500'],
        ]);

        try {
            DB::beginTransaction();

            // Create the order
            $order = InvOrder::create([
                'user_id' => Auth::id(),
                'order_number' => $this->order_number,
                'notes' => $this->notes,
            ]);

            // Update order items to link to the order
            InvOrderItem::whereIn('id', $this->order_item_ids)
                ->whereNull('inv_order_id')
                ->update(['inv_order_id' => $order->id]);

            // Create budget snapshots and update balances
            foreach ($this->budget_summary as $budgetSummary) {
                $budget = InvOrderBudget::find($budgetSummary['budget_id']);
                
                if ($budget) {
                    // Create snapshot
                    InvOrderBudgetSnapshot::create([
                        'inv_order_id' => $order->id,
                        'inv_order_budget_id' => $budget->id,
                        'balance_before' => $budget->balance,
                        'balance_after' => $budget->balance - $budgetSummary['total_allocation'],
                        'inv_curr_id' => $budget->inv_curr_id,
                    ]);

                    // Update budget balance
                    $budget->update([
                        'balance' => $budget->balance - $budgetSummary['total_allocation']
                    ]);
                }
            }

            DB::commit();

            $this->dispatch('order-items-finalized');
            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Pesanan berhasil difinalisasi dengan nomor: ') . $this->order_number . '", { type: "success" })');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->js('toast("' . __('Terjadi kesalahan saat memfinalisasi pesanan') . '", { type: "danger" })');
        }
    }

    public function getTotalItems(): int
    {
        return count($this->order_items);
    }

    public function getTotalAllocation(): float
    {
        return collect($this->budget_summary)->sum('total_allocation');
    }
}

?>

<div class="p-6 flex flex-col gap-y-6 max-h-[80vh] overflow-hidden">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            <i class="icon-check mr-2"></i>{{ __('Finalisasi pesanan') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')">
            <i class="icon-x"></i>
        </x-text-button>
    </div>

    @if(!$can_finalize)
        <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex items-center">
                <i class="icon-triangle-alert text-red-500 mr-2"></i>
                <span class="text-red-700 dark:text-red-200">{{ $validation_message }}</span>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="text-center">
            <x-input-error :messages="$errors->first()" />
        </div>
    @endif

    <div class="flex-1 overflow-y-auto space-y-6">
        {{-- Order Details --}}
        <div class="grid grid-cols-1 gap-y-4">
            <div>
                <label for="order_number" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nomor pesanan') }}</label>
                <x-text-input id="order_number" wire:model="order_number" type="text" class="w-full" maxlength="20" />
            </div>

            <div>
                <label for="notes" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Catatan') }}</label>
                <textarea id="notes" wire:model="notes" 
                         class="w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                         rows="3" maxlength="500" placeholder="{{ __('Catatan opsional untuk pesanan ini...') }}"></textarea>
            </div>
        </div>

        {{-- Summary --}}
        <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4">
            <h3 class="font-medium mb-3">{{ __('Ringkasan pesanan') }}</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-neutral-500">{{ __('Total item') }}:</span>
                    <span class="font-medium ml-2">{{ $this->getTotalItems() }}</span>
                </div>
                <div>
                    <span class="text-neutral-500">{{ __('Area') }}:</span>
                    <span class="font-medium ml-2">{{ collect($order_items)->first()['area_name'] ?? '-' }}</span>
                </div>
            </div>
        </div>

        {{-- Budget Impact --}}
        @if(count($budget_summary) > 0)
            <div>
                <h3 class="font-medium mb-3">{{ __('Dampak budget') }}</h3>
                <div class="space-y-3">
                    @foreach($budget_summary as $budget)
                        <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div class="font-medium">{{ $budget['budget_name'] }}</div>
                                <div class="text-sm text-neutral-500">{{ $budget['item_count'] }} {{ __('item') }}</div>
                            </div>
                            
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Saldo tersedia') }}:</span>
                                    <span class="font-mono">{{ number_format($budget['available_balance'], 2) }} {{ $budget['budget_currency'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Akan dialokasikan') }}:</span>
                                    <span class="font-mono">{{ number_format($budget['total_allocation'], 2) }} {{ $budget['budget_currency'] }}</span>
                                </div>
                                <hr class="border-neutral-300 dark:border-neutral-600">
                                <div class="flex justify-between font-medium {{ $budget['is_sufficient'] ? '' : 'text-red-600 dark:text-red-400' }}">
                                    <span>{{ __('Saldo setelah') }}:</span>
                                    <span class="font-mono">{{ number_format($budget['balance_after'], 2) }} {{ $budget['budget_currency'] }}</span>
                                </div>
                                
                                @if(!$budget['is_sufficient'])
                                    <div class="text-red-600 dark:text-red-400 text-xs mt-1">
                                        <i class="icon-triangle-alert mr-1"></i>{{ __('Budget tidak mencukupi') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Order Items List --}}
        <div>
            <h3 class="font-medium mb-3">{{ __('Item yang akan difinalisasi') }}</h3>
            <div class="max-h-60 overflow-y-auto">
                <div class="space-y-2">
                    @foreach($order_items as $item)
                        <div class="border border-neutral-200 dark:border-neutral-700 rounded p-3 text-sm">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium truncate">{{ $item['name'] }}</div>
                                    <div class="text-neutral-500 truncate">{{ $item['desc'] }}</div>
                                    <div class="text-xs text-neutral-400">{{ $item['code'] ?: __('Tidak ada kode') }}</div>
                                </div>
                                <div class="text-right ml-3">
                                    <div class="font-medium">{{ $item['qty'] }} {{ $item['uom'] }}</div>
                                    <div class="text-xs text-neutral-500">{{ number_format($item['amount_budget'], 2) }} {{ $item['budget_currency'] }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
        <div class="flex justify-end space-x-3">
            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                {{ __('Batal') }}
            </x-secondary-button>
            
            @if($can_finalize)
                <div wire:loading>
                    <x-primary-button type="button" disabled>
                        <i class="icon-check mr-2"></i>{{ __('Finalisasi') }}
                    </x-primary-button>
                </div>
                <div wire:loading.remove>
                    <x-primary-button type="button" wire:click="finalize">
                        <i class="icon-check mr-2"></i>{{ __('Finalisasi') }}
                    </x-primary-button>
                </div>
            @else
                <x-primary-button type="button" disabled>
                    <i class="icon-check mr-2"></i>{{ __('Finalisasi') }}
                </x-primary-button>
            @endif
        </div>
    </div>

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>