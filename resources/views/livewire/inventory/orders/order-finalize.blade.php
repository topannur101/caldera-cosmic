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
    public array $areas = [];
    public int $selected_area_id = 0;
    public array $order_items = [];
    public array $budget_summary = [];
    public string $order_number = '';
    public string $notes = '';
    public bool $can_finalize = false;
    public string $validation_message = '';
    public string $step = 'area_selection'; // 'area_selection' or 'finalization'

    #[On('finalize-orders')]
    public function loadAreas(array $userAreas)
    {
        $this->areas = $userAreas;
        
        if (count($this->areas) === 1) {
            // Single area - go directly to finalization
            $this->selected_area_id = $this->areas[0]['id'];
            $this->step = 'finalization';
            $this->loadOrderItemsForArea($this->selected_area_id);
        } else {
            // Multiple areas - show area selection first
            $this->step = 'area_selection';
            $this->resetFinalizationData();
        }
    }

    public function selectArea()
    {
        if (!$this->selected_area_id) {
            $this->js('toast("' . __('Pilih area terlebih dahulu') . '", { type: "danger" })');
            return;
        }

        $this->step = 'finalization';
        $this->loadOrderItemsForArea($this->selected_area_id);
    }

    public function backToAreaSelection()
    {
        $this->step = 'area_selection';
        $this->resetFinalizationData();
    }

    private function resetFinalizationData()
    {
        $this->order_items = [];
        $this->budget_summary = [];
        $this->order_number = '';
        $this->notes = '';
        $this->can_finalize = false;
        $this->validation_message = '';
    }

    private function loadOrderItemsForArea(int $areaId)
    {
        $orderItems = InvOrderItem::with([
            'inv_area',
            'inv_order_budget',
            'inv_order_budget.inv_curr'
        ])
        ->where('inv_area_id', $areaId)
        ->whereNull('inv_order_id')
        ->get();

        if ($orderItems->count() === 0) {
            $this->can_finalize = false;
            $this->validation_message = __('Tidak ada butir pesanan terbuka di area yang dipilih.');
            return;
        }

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

        $this->can_finalize = true;
        $this->validation_message = '';
        
        // Generate order number
        $this->order_number = $this->generateOrderNumber();
        
        // Calculate budget summary
        $this->calculateBudgetSummary();
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

            // Get order item IDs
            $orderItemIds = collect($this->order_items)->pluck('id')->toArray();

            // Update order items to link to the order
            InvOrderItem::whereIn('id', $orderItemIds)
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

    public function getSelectedAreaName(): string
    {
        if ($this->selected_area_id) {
            $area = collect($this->areas)->firstWhere('id', $this->selected_area_id);
            return $area ? $area['name'] : '';
        }
        return '';
    }
}

?>

<div class="relative p-6 flex flex-col gap-y-6 max-h-[80vh] overflow-hidden">
    {{-- Header --}}
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            <i class="icon-check mr-2"></i>{{ __('Finalisasi pesanan') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')">
            <i class="icon-x"></i>
        </x-text-button>
    </div>

    {{-- Area Selection Step --}}
    @if($step === 'area_selection')
        <div class="flex-1 overflow-y-auto space-y-6">
            <div class="text-center py-8">
                <i class="icon-house text-4xl text-neutral-300 dark:text-neutral-700 mb-4"></i>
                <h3 class="text-lg font-medium mb-2">{{ __('Pilih area untuk finalisasi') }}</h3>
                <p class="text-neutral-500 text-sm">{{ __('Semua butir pesanan terbuka dari area yang dipilih akan difinalisasi.') }}</p>
            </div>

            <div class="space-y-3">
                @foreach($areas as $area)
                    <x-radio 
                        wire:model="selected_area_id" 
                        id="area-{{ $area['id'] }}" 
                        name="area-selection" 
                        value="{{ $area['id'] }}">
                        {{ $area['name'] }}
                    </x-radio>
                @endforeach
            </div>
        </div>

        {{-- Area Selection Actions --}}
        <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
            <div class="flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Batal') }}
                </x-secondary-button>
                
                <x-primary-button type="button" wire:click="selectArea">
                    <i class="icon-arrow-right mr-2"></i>{{ __('Lanjutkan') }}
                </x-primary-button>
            </div>
        </div>

    {{-- Finalization Step --}}
    @elseif($step === 'finalization')
        
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
            {{-- Back to area selection (if multiple areas) --}}
            @if(count($areas) > 1)
                <div class="flex items-center text-sm text-neutral-500">
                    <x-text-button type="button" wire:click="backToAreaSelection" class="mr-2">
                        <i class="icon-arrow-left"></i>
                    </x-text-button>
                    <span>{{ __('Area: ') }}<strong>{{ $this->getSelectedAreaName() }}</strong></span>
                </div>
            @endif

            {{-- Order Details --}}
            <div class="grid grid-cols-1 gap-y-4">
                <div>
                    <label for="order_number" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nomor pesanan') }}</label>
                    <x-text-input id="order_number" wire:model="order_number" type="text" class="w-full" maxlength="20" />
                </div>

                <div>
                    <label for="notes" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Catatan') }}</label>
                    <x-text-input id="notes" wire:model="notes" type="text" class="w-full" />
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
                        <span class="font-medium ml-2">{{ $this->getSelectedAreaName() }}</span>
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

        {{-- Finalization Actions --}}
        <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
            <div class="flex justify-end space-x-3">
                @if(count($areas) > 1)
                    <x-secondary-button type="button" wire:click="backToAreaSelection">
                        {{ __('Kembali') }}
                    </x-secondary-button>
                @else
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Batal') }}
                    </x-secondary-button>
                @endif
                
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
    @endif

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>