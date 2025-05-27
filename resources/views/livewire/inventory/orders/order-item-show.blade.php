<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InvOrderItem;
use App\Models\InvOrderBudget;
use App\Models\InvOrderEval;
use App\Models\InvCurr;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public array $order_item = [
        'id' => 0,
        'name' => '',
        'desc' => '',
        'code' => '',
        'purpose' => '',
        'qty' => 1,
        'uom' => '',
        'unit_price' => 0,
        'total_amount' => 0,
        'amount_budget' => 0,
        'exchange_rate_used' => 1.00,
        'inv_area_id' => 0,
        'inv_curr_id' => 0,
        'inv_order_budget_id' => 0,
        'inv_item_id' => null,
        'photo' => null,
    ];

    public array $budgets = [];
    public array $currencies = [];
    public array $evaluations = [];

    // Form fields
    public string $name = '';
    public string $desc = '';
    public string $code = '';
    public string $purpose = '';
    public int $qty = 1;
    public string $uom = '';
    public float $unit_price = 0;
    public int $currency_id = 0;
    public int $budget_id = 0;
    public string $eval_message = '';

    public bool $can_edit_item_details = false;
    public string $active_tab = 'edit'; // 'edit' or 'evals'

    #[On('order-item-show')]
    public function loadOrderItem(int $id, string $tab = 'edit')
    {
        $this->active_tab = $tab;
        
        $orderItem = InvOrderItem::with([
            'inv_area',
            'inv_curr', 
            'inv_order_budget',
            'inv_order_budget.inv_curr',
            'inv_order_evals.user'
        ])->find($id);

        if ($orderItem) {
            $this->order_item = $orderItem->toArray();
            
            // Set form fields
            $this->name = $orderItem->name;
            $this->desc = $orderItem->desc;
            $this->code = $orderItem->code;
            $this->purpose = $orderItem->purpose;
            $this->qty = $orderItem->qty;
            $this->uom = $orderItem->uom;
            $this->unit_price = $orderItem->unit_price;
            $this->currency_id = $orderItem->inv_curr_id;
            $this->budget_id = $orderItem->inv_order_budget_id;

            // Check if item details can be edited (manual entries only)
            $this->can_edit_item_details = is_null($orderItem->inv_item_id);

            // Load budgets for the area
            $this->loadBudgets($orderItem->inv_area_id);
            
            // Load currencies
            $this->currencies = InvCurr::where('is_active', true)->get()->toArray();

            // Load evaluations with parsed data
            $this->evaluations = $orderItem->inv_order_evals()
                ->with('user')
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($eval) {
                    $changes = [];
                    if ($eval->data) {
                        $parsedData = json_decode($eval->data, true);
                        if ($parsedData) {
                            $changes = collect($parsedData)->map(function($change) {
                                return $this->formatChangeDescription($change);
                            })->toArray();
                        }
                    }
                    
                    return [
                        'id' => $eval->id,
                        'user_name' => $eval->user->name,
                        'user_emp_id' => $eval->user->emp_id,
                        'user_photo' => $eval->user->photo,
                        'qty_before' => $eval->qty_before,
                        'qty_after' => $eval->qty_after,
                        'quantity_change' => $eval->qty_after - $eval->qty_before,
                        'message' => $eval->message,
                        'changes' => $changes,
                        'created_at' => $eval->created_at->format('Y-m-d H:i'),
                        'created_at_diff' => $eval->created_at->diffForHumans(),
                    ];
                })
                ->toArray();

            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }


    #[On('order-item-edit')]
    public function loadOrderItemForEdit(int $id)
    {
        $this->loadOrderItem($id, 'edit');
    }

    #[On('order-item-evals')]
    public function loadOrderItemForEvals(int $id)
    {
        $this->loadOrderItem($id, 'evals');
    }

    private function detectChanges($orderItem, $beforeBudget = null)
    {
        $changes = [];
        
        // 1. Quantity changes
        if ($orderItem->qty != $this->qty) {
            if ($this->qty > $orderItem->qty) {
                $changes[] = [
                    'type' => 'qty_increase',
                    'from' => $orderItem->qty,
                    'to' => $this->qty
                ];
            } else {
                $changes[] = [
                    'type' => 'qty_decrease', 
                    'from' => $orderItem->qty,
                    'to' => $this->qty
                ];
            }
        }
        
        // 2. Budget changes
        if ($orderItem->inv_order_budget_id != $this->budget_id) {
            $newBudget = collect($this->budgets)->firstWhere('id', $this->budget_id);
            
            $changes[] = [
                'type' => 'budget_change',
                'from_budget_name' => $beforeBudget ? $beforeBudget->name : 'Unknown',
                'to_budget_name' => $newBudget ? $newBudget['name'] : 'Unknown',
                'from_amount_budget' => $orderItem->amount_budget,
                'to_amount_budget' => $this->order_item['amount_budget'] ?? 0,
                'from_currency' => $beforeBudget ? $beforeBudget->inv_curr->name : 'Unknown',
                'to_currency' => $newBudget ? $newBudget['inv_curr']['name'] : 'Unknown'
            ];
        }
        
        // 3. Purpose changes
        if ($orderItem->purpose != $this->purpose) {
            $changes[] = [
                'type' => 'purpose_change',
                'from' => $orderItem->purpose,
                'to' => $this->purpose
            ];
        }
        
        // 4. Item info changes (only for manual entries)
        if ($this->can_edit_item_details) {
            if ($orderItem->name != $this->name) {
                $changes[] = [
                    'type' => 'name_change',
                    'from' => $orderItem->name,
                    'to' => $this->name
                ];
            }
            
            if ($orderItem->desc != $this->desc) {
                $changes[] = [
                    'type' => 'desc_change',
                    'from' => $orderItem->desc,
                    'to' => $this->desc
                ];
            }
            
            if ($orderItem->code != $this->code) {
                $changes[] = [
                    'type' => 'code_change',
                    'from' => $orderItem->code,
                    'to' => $this->code
                ];
            }
            
            if ($orderItem->uom != $this->uom) {
                $changes[] = [
                    'type' => 'uom_change',
                    'from' => $orderItem->uom,
                    'to' => $this->uom
                ];
            }
            
            if ($orderItem->unit_price != $this->unit_price) {
                $changes[] = [
                    'type' => 'unit_price_change',
                    'from' => $orderItem->unit_price,
                    'to' => $this->unit_price
                ];
            }
            
            if ($orderItem->inv_curr_id != $this->currency_id) {
                $oldCurrency = collect($this->currencies)->firstWhere('id', $orderItem->inv_curr_id);
                $newCurrency = collect($this->currencies)->firstWhere('id', $this->currency_id);
                
                $changes[] = [
                    'type' => 'currency_change',
                    'from' => $oldCurrency ? $oldCurrency['name'] : 'Unknown',
                    'to' => $newCurrency ? $newCurrency['name'] : 'Unknown'
                ];
            }
        }
        
        return $changes;
    }

    private function loadBudgets(int $areaId)
    {
        $this->budgets = InvOrderBudget::where('inv_area_id', $areaId)
            ->where('is_active', true)
            ->with('inv_curr')
            ->get()
            ->toArray();
    }

    public function updatedQty()
    {
        $this->calculateAmounts();
    }

    public function updatedUnitPrice()
    {
        $this->calculateAmounts();
    }

    public function updatedCurrencyId()
    {
        $this->calculateAmounts();
    }

    public function updatedBudgetId()
    {
        $this->calculateAmounts();
    }

    private function calculateAmounts()
    {
        $totalAmount = $this->qty * $this->unit_price;
        
        if ($this->budget_id && $this->currency_id) {
            $budget = collect($this->budgets)->firstWhere('id', $this->budget_id);
            $itemCurrency = collect($this->currencies)->firstWhere('id', $this->currency_id);
            
            if ($budget && $itemCurrency) {
                $budgetCurrencyRate = $budget['inv_curr']['rate'];
                $itemCurrencyRate = $itemCurrency['rate'];
                
                $exchangeRate = $budgetCurrencyRate / $itemCurrencyRate;
                $amountBudget = $totalAmount * $exchangeRate;
                
                $this->order_item['total_amount'] = $totalAmount;
                $this->order_item['amount_budget'] = $amountBudget;
                $this->order_item['exchange_rate_used'] = $exchangeRate;
            }
        }
    }

    public function update()
    {
        // Clean up inputs
        $this->name = trim($this->name);
        $this->desc = trim($this->desc);
        $this->code = strtoupper(trim($this->code));
        $this->purpose = trim($this->purpose);
        $this->uom = strtoupper(trim($this->uom));
        $this->eval_message = trim($this->eval_message);

        $rules = [
            'purpose' => ['required', 'max:500'],
            'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'budget_id' => ['required', 'exists:inv_order_budget,id'],
            'eval_message' => ['required', 'max:256'],
        ];

        // Add validation rules for item details if editable
        if ($this->can_edit_item_details) {
            $rules = array_merge($rules, [
                'name' => ['required', 'max:128'],
                'desc' => ['required', 'max:256'],
                'code' => ['required', 'alpha_dash', 'size:11'],
                'uom' => ['required', 'alpha_dash', 'max:5'],
                'unit_price' => ['required', 'numeric', 'min:0', 'max:1000000000'],
                'currency_id' => ['required', 'exists:inv_currs,id'],
            ]);
        }

        $this->validate($rules);

        try {
            // Get current state from database (Option A)
            $orderItem = InvOrderItem::with(['inv_order_budget.inv_curr'])->find($this->order_item['id']);
            
            if (!$orderItem || !is_null($orderItem->inv_order_id)) {
                $this->js('toast("' . __('Butir pesanan tidak dapat diedit') . '", { type: "danger" })');
                return;
            }

            // Detect changes before updating
            $changes = $this->detectChanges($orderItem, $orderItem->inv_order_budget);

            // Store original quantity for evaluation tracking
            $qtyBefore = $orderItem->qty;

            // Update basic fields
            $orderItem->purpose = $this->purpose;
            $orderItem->qty = $this->qty;
            $orderItem->inv_order_budget_id = $this->budget_id;

            // Update item details if editable
            if ($this->can_edit_item_details) {
                $orderItem->name = $this->name;
                $orderItem->desc = $this->desc;
                $orderItem->code = $this->code;
                $orderItem->uom = $this->uom;
                $orderItem->unit_price = $this->unit_price;
                $orderItem->inv_curr_id = $this->currency_id;
            }

            // Recalculate amounts
            $this->calculateAmounts();
            $orderItem->total_amount = $this->order_item['total_amount'];
            $orderItem->amount_budget = $this->order_item['amount_budget'];
            $orderItem->exchange_rate_used = $this->order_item['exchange_rate_used'];

            $orderItem->save();

            // Create evaluation record with change detection
            InvOrderEval::create([
                'inv_order_item_id' => $orderItem->id,
                'user_id' => Auth::id(),
                'qty_before' => $qtyBefore,
                'qty_after' => $this->qty,
                'message' => $this->eval_message,
                'data' => json_encode($changes)
            ]);

            $this->dispatch('order-item-updated');
            $this->js('window.dispatchEvent(escKey)');
            $this->js('toast("' . __('Butir pesanan berhasil diperbarui') . '", { type: "success" })');

        } catch (\Exception $e) {
            $this->js('toast("' . __('Terjadi kesalahan saat menyimpan') . '", { type: "danger" })');
        }
    }

    private function formatChangeDescription($change)
    {
        switch ($change['type']) {
            case 'qty_increase':
                return __('Qty bertambah dari :from menjadi :to', [
                    'from' => $change['from'],
                    'to' => $change['to']
                ]);
               
            case 'qty_decrease':
                return __('Qty berkurang dari :from menjadi :to', [
                    'from' => $change['from'],
                    'to' => $change['to']
                ]);
               
            case 'budget_change':
                return __('Anggaran berubah dari :from_budget (:from_currency :from_amount) menjadi :to_budget (:to_currency :to_amount)', [
                    'from_budget' => $change['from_budget_name'],
                    'to_budget' => $change['to_budget_name'],
                    'from_currency' => $change['from_currency'],
                    'from_amount' => number_format($change['from_amount_budget'], 2),
                    'to_currency' => $change['to_currency'],
                    'to_amount' => number_format($change['to_amount_budget'], 2)
                ]);
               
            case 'purpose_change':
                return __('Keperluan berubah dari ":from" menjadi ":to"', [
                    'from' => $change['from'],
                    'to' => $change['to']
                ]);
               
            case 'name_change':
                return __('Nama berubah dari ":from" menjadi ":to"', [
                    'from' => $change['from'],
                    'to' => $change['to']
                ]);
               
            case 'desc_change':
                return __('Deskripsi berubah dari ":from" menjadi ":to"', [
                    'from' => $change['from'],
                    'to' => $change['to']
                ]);
               
            case 'code_change':
                return __('Kode berubah dari ":from" menjadi ":to"', [
                    'from' => $change['from'],
                    'to' => $change['to']
                ]);
               
            case 'uom_change':
                return __('Satuan berubah dari ":from" menjadi ":to"', [
                    'from' => $change['from'],
                    'to' => $change['to']
                ]);
               
            case 'unit_price_change':
                return __('Harga satuan berubah dari :from menjadi :to', [
                    'from' => number_format($change['from'], 2),
                    'to' => number_format($change['to'], 2)
                ]);
               
            case 'currency_change':
                return __('Mata uang berubah dari :from menjadi :to', [
                    'from' => $change['from'],
                    'to' => $change['to']
                ]);
               
            default:
                return __('Jenis perubahan tidak dikenal: :type', ['type' => $change['type']]);
        }
    }

    public function deleteOrderItem()
    {
        try {
            $orderItem = InvOrderItem::find($this->order_item['id']);
            
            if ($orderItem && is_null($orderItem->inv_order_id)) {
                $orderItem->delete();
                
                $this->dispatch('order-item-updated');
                $this->js('slideOverOpen = false');
                $this->js('toast("' . __('Butir pesanan berhasil dihapus') . '", { type: "success" })');
            } else {
                $this->js('toast("' . __('Butir pesanan tidak dapat dihapus') . '", { type: "danger" })');
            }
        } catch (\Exception $e) {
            $this->js('toast("' . __('Terjadi kesalahan saat menghapus') . '", { type: "danger" })');
        }
    }

    public function handleNotFound()
    {
        $this->js('slideOverOpen = false');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
    }

    public function getBudgetCurrency()
    {
        if ($this->budget_id) {
            $budget = collect($this->budgets)->firstWhere('id', $this->budget_id);
            return $budget ? $budget['inv_curr']['name'] : '';
        }
        return '';
    }

    public function getItemCurrency()
    {
        if ($this->currency_id) {
            $currency = collect($this->currencies)->firstWhere('id', $this->currency_id);
            return $currency ? $currency['name'] : '';
        }
        return '';
    }
}

?>

<div class="relative h-full flex flex-col">
    <div class="p-6 border-b border-neutral-200 dark:border-neutral-700">
        <div class="flex justify-between items-start mb-4">
            <h2 class="text-lg font-medium">
                {{ __('Pesanan') }}
            </h2>
            <x-text-button type="button" x-on:click="window.dispatchEvent(escKey)">
                <i class="icon-x"></i>
            </x-text-button>
        </div>

        {{-- Order Item Info --}}
        <div class="flex gap-x-3 mb-4">
            <div class="rounded-sm overflow-hidden relative flex w-12 h-12 bg-neutral-200 dark:bg-neutral-700">
                <div class="m-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="block w-6 h-6 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793">
                        <path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" />
                    </svg>
                </div>
                @if($order_item['photo'])
                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-items/' . $order_item['photo'] }}" />
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-medium truncate">{{ $order_item['name'] }}</div>
                <div class="text-sm text-neutral-500 truncate">{{ $order_item['desc'] }}</div>
                <div class="text-xs text-neutral-400">{{ $order_item['code'] ?: __('Tidak ada kode') }}</div>
            </div>
        </div>

        {{-- Tab Navigation --}}
        <div x-data="{
                tabSelected: @entangle('active_tab'),
                tabButtonClicked(tabButton){
                    this.tabSelected = tabButton.dataset.tab;
                }
            }" class="relative w-full">
            
            <div class="relative inline-grid items-center justify-center w-full h-10 grid-cols-2 p-1 text-neutral-500 bg-neutral-100 dark:bg-neutral-800 rounded-lg select-none">
                <button data-tab="edit" @click="tabButtonClicked($el);" type="button" 
                        :class="tabSelected === 'edit' ? 'text-neutral-900 dark:text-neutral-100' : ''"
                        class="relative z-10 inline-flex items-center justify-center w-full h-8 px-3 text-sm font-medium transition-all rounded-md cursor-pointer whitespace-nowrap">
                    <i class="icon-edit mr-2"></i>{{ __('Edit') }}
                </button>
                <button data-tab="evals" @click="tabButtonClicked($el);" type="button" 
                        :class="tabSelected === 'evals' ? 'text-neutral-900 dark:text-neutral-100' : ''"
                        class="relative z-10 inline-flex items-center justify-center w-full h-8 px-3 text-sm font-medium transition-all rounded-md cursor-pointer whitespace-nowrap">
                    <i class="icon-message mr-2"></i>{{ __('Evaluasi') }} ({{ count($evaluations) }})
                </button>
                
                {{-- Marker positioned with CSS based on active tab --}}
                <div class="absolute left-0 h-full duration-300 ease-out transition-transform" 
                    :class="tabSelected === 'evals' ? 'translate-x-full' : 'translate-x-0'"
                    style="width: calc(50% - 4px); margin: 2px;">
                    <div class="w-full h-full bg-white dark:bg-neutral-700 rounded-md shadow-sm"></div>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="px-6 pt-4">
            <div class="text-center">
                <x-input-error :messages="$errors->first()" />
            </div>
        </div>
    @endif

    <div class="flex-1 overflow-y-auto" x-data="{
            tabSelected: @entangle('active_tab')
        }">
        
        {{-- Edit Tab Content --}}
        <div x-show="tabSelected === 'edit'" class="p-6 space-y-6">
            
            {{-- Item Type Info --}}
            <div class="text-sm text-neutral-500">
                @if($can_edit_item_details)
                    <i class="icon-unlink-2 text-neutral-500 mr-2"></i>{{ __('Tidak bertaut - info barang dapat diedit') }}
                @else
                    <i class="icon-link-2 text-caldy-500 mr-2"></i>{{ __('Bertaut - info barang tak dapat diedit') }}
                @endif
            </div>

            {{-- Item Details (editable for manual entries only) --}}
            @if($can_edit_item_details)
                <div class="grid grid-cols-1 gap-y-4">
                    <div>
                        <label for="name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                        <x-text-input id="name" wire:model="name" type="text" class="w-full" />
                    </div>

                    <div>
                        <label for="desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Deskripsi') }}</label>
                        <x-text-input id="desc" wire:model="desc" type="text" class="w-full" />
                    </div>

                    <div>
                        <label for="code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                        <x-text-input id="code" wire:model="code" type="text" class="w-full" maxlength="11" />
                    </div>
                </div>
            @endif

            {{-- Pricing (editable for manual entries only) --}}
            @if($can_edit_item_details)
                <div class="grid grid-cols-2 gap-x-3">
                    <div>
                        <label for="currency" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mata uang') }}</label>
                        <x-select wire:model.live="currency_id" class="w-full">
                            <option value="">{{ __('Pilih mata uang...') }}</option>
                            @foreach($currencies as $currency)
                                <option value="{{ $currency['id'] }}">{{ $currency['name'] }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div>
                        <label for="unit_price" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Harga satuan') }}</label>
                        <x-text-input id="unit_price" wire:model.live="unit_price" type="number" step="0.01" min="0" class="w-full" />
                    </div>
                </div>
            @endif

            {{-- Quantity and UOM --}}
            <div class="grid grid-cols-2 gap-x-3">
                <div>
                    <label for="qty" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Qty') }}</label>
                    <x-text-input id="qty" wire:model.live="qty" type="number" min="1" class="w-full" />
                </div>

                @if($can_edit_item_details)
                    <div>
                        <label for="uom" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Satuan') }}</label>
                        <x-text-input id="uom" wire:model="uom" type="text" class="w-full" maxlength="5" />
                    </div>
                @else
                    <div>
                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Satuan') }}</label>
                        <x-text-input value="{{ $order_item['uom'] }}" type="text" class="w-full" disabled />
                    </div>
                @endif
            </div>

            {{-- Purpose --}}
            <div>
               <label for="purpose" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Keperluan') }}</label>
               <x-text-input id="purpose" wire:model="purpose" type="text" class="w-full" />
            </div>

            {{-- Budget Selection --}}
            <div>
               <label for="budget" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                  {{ __('Anggaran') }}
               </label>
               <div class="mx-3">
                    @foreach($budgets as $budget)
                    <x-radio 
                        wire:model.change="budget_id" 
                        id="budget-{{ $budget['id'] }}" 
                        name="budget-selection" 
                        value="{{ $budget['id'] }}">
                        {{ $budget['name'] }}
                    </x-radio>
                    @endforeach
               </div>
            </div>

            {{-- Amount Summary --}}
            @if($qty > 0 && isset($order_item['total_amount']))
                <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>{{ __('Total amount') }}:</span>
                        <span>{{ $this->getItemCurrency() }} {{ number_format($order_item['total_amount'], 2) }}</span>
                    </div>                
                    
                    @if($budget_id && isset($order_item['amount_budget']))
                        <div class="flex justify-between font-medium">
                            <span>{{ __('Amount budget') }}:</span>
                            <span>{{ $this->getBudgetCurrency() }} {{ number_format($order_item['amount_budget'], 2) }}</span>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Evaluation Message --}}
            <div>
                <label for="eval_message" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Alasan perubahan') }}</label>
                <x-text-input id="eval_message" wire:model="eval_message" type="text" class="w-full" />
            </div>

            {{-- Actions --}}
            <div class="flex justify-between">
                <x-text-button type="button" wire:click="deleteOrderItem" 
                    wire:confirm="{{ __('Yakin ingin menghapus butir pesanan ini?') }}">
                    <i class="icon-trash text-red-500"></i>
                </x-text-button>
                
                <x-primary-button type="button" wire:click="update">
                    {{ __('Simpan') }}
                </x-primary-button>
            </div>
        </div>

        {{-- Evaluations Tab Content --}}
        <div x-show="tabSelected === 'evals'" class="flex-1 flex flex-col h-full" x-cloak>
            @if(count($evaluations) > 0)
                <div class="p-6 flex-1 overflow-y-auto">
                    <!-- Timeline Container -->
                    <div class="relative">
                        <!-- Timeline Line -->
                        <div class="absolute left-1.5 top-4 bottom-0 w-0.5 bg-neutral-200 dark:bg-neutral-700"></div>
                        
                        <!-- Timeline Items -->
                        <div class="space-y-8">
                            @foreach($evaluations as $eval)
                                <div class="relative">
                                    <!-- Timeline Dot -->
                                    <div class="absolute top-3 -left-px w-4 h-4 bg-white dark:bg-neutral-900 border-2 border-neutral-300 dark:border-neutral-600 rounded-full z-10"></div>
                                    
                                    <!-- Content Card -->
                                    <div class="ml-8">
                                        {{-- User Info and Timestamp --}}
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="flex items-center gap-x-3">
                                                <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden flex-shrink-0">
                                                    @if ($eval['user_photo'])
                                                        <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/' . $eval['user_photo'] }}" />
                                                    @else
                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                            class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                                            viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                                            <path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div>
                                                    <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $eval['user_name'] }}</div>
                                                    <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ $eval['user_emp_id'] }}</div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ $eval['created_at'] }}</div>
                                                <div class="text-xs text-neutral-400 dark:text-neutral-500">{{ $eval['created_at_diff'] }}</div>
                                            </div>
                                        </div>

                                        {{-- Quantity Change --}}
                                        <div class="mb-4">
                                            <div class="text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-2 uppercase tracking-wide">{{ __('Perubahan quantity') }}</div>
                                            <div class="flex items-center gap-x-3">
                                                <span class="font-mono text-lg font-medium text-neutral-800 dark:text-neutral-200">{{ $eval['qty_before'] }}</span>
                                                <div class="grow h-px bg-neutral-200 dark:bg-neutral-700"></div>
                                                <i class="icon-arrow-right text-neutral-400"></i>
                                                <div class="grow h-px bg-neutral-200 dark:bg-neutral-700"></div>
                                                <span class="font-mono text-lg font-medium text-neutral-800 dark:text-neutral-200">{{ $eval['qty_after'] }}</span>
                                                @if($eval['quantity_change'] != 0)
                                                    <span class="px-3 py-1 rounded-full text-sm font-medium {{ $eval['quantity_change'] > 0 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                                        {{ $eval['quantity_change'] > 0 ? '+' : '' }}{{ $eval['quantity_change'] }}
                                                    </span>
                                                @else
                                                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                                                        0
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Detailed Changes --}}
                                        @if(count($eval['changes']) > 0)
                                            <div class="mb-4">
                                                <div class="text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-3 uppercase tracking-wide">{{ __('Detail perubahan') }}</div>
                                                <ul class="space-y-2">
                                                    @foreach($eval['changes'] as $change)
                                                        <li class="flex items-start gap-x-2">
                                                            <div class="w-1.5 h-1.5 bg-neutral-400 dark:bg-neutral-500 rounded-full mt-1.5 flex-shrink-0"></div>
                                                            <span class="text-xs text-neutral-700 dark:text-neutral-300 leading-relaxed">{{ $change }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        {{-- Message --}}
                                        @if($eval['message'])
                                            <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4">
                                                <div class="text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1 uppercase tracking-wide">{{ __('Alasan') }}</div>
                                                <div class="text-sm text-neutral-700 dark:text-neutral-300 leading-relaxed">{{ $eval['message'] }}</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="flex flex-col gap-y-3 items-center justify-center my-auto text-neutral-400 dark:text-neutral-600">
                    <i class="icon-message-square text-4xl opacity-50"></i>
                    <div class="text-lg font-medium">{{ __('Belum ada evaluasi') }}</div>
                </div>
            @endif
        </div>
    </div>

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>