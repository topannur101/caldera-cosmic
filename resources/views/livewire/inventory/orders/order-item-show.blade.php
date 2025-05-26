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

    #[On('order-item-show')]
    public function loadOrderItem(int $id)
    {
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

            // Load evaluations
            $this->evaluations = $orderItem->inv_order_evals()
                ->with('user')
                ->orderByDesc('created_at')
                ->get()
                ->toArray();

            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
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
            $orderItem = InvOrderItem::find($this->order_item['id']);
            
            if (!$orderItem || !is_null($orderItem->inv_order_id)) {
                $this->js('toast("' . __('Butir pesanan tidak dapat diedit') . '", { type: "danger" })');
                return;
            }

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

            // Create evaluation record
            InvOrderEval::create([
                'inv_order_item_id' => $orderItem->id,
                'user_id' => Auth::id(),
                'qty_before' => $qtyBefore,
                'qty_after' => $this->qty,
                'message' => $this->eval_message,
            ]);

            $this->dispatch('order-item-updated');
            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Butir pesanan berhasil diperbarui') . '", { type: "success" })');

        } catch (\Exception $e) {
            $this->js('toast("' . __('Terjadi kesalahan saat menyimpan') . '", { type: "danger" })');
        }
    }

    public function deleteOrderItem()
    {
        try {
            $orderItem = InvOrderItem::find($this->order_item['id']);
            
            if ($orderItem && is_null($orderItem->inv_order_id)) {
                $orderItem->delete();
                
                $this->dispatch('order-item-updated');
                $this->js('$dispatch("close")');
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
        $this->js('$dispatch("close")');
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

<div class="h-full flex flex-col">
    <div class="p-6 flex justify-between items-start border-b border-neutral-200 dark:border-neutral-700">
        <h2 class="text-lg font-medium">
            {{ __('Edit butir pesanan') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')">
            <i class="icon-x"></i>
        </x-text-button>
    </div>

    @if ($errors->any())
        <div class="px-6 pt-4">
            <div class="text-center">
                <x-input-error :messages="$errors->first()" />
            </div>
        </div>
    @endif

    <div class="flex-1 overflow-y-auto">
        <div class="p-6 space-y-6">
            
            {{-- Item Photo and Basic Info --}}
            <div class="flex gap-x-3">
                <div class="rounded-sm overflow-hidden relative flex w-16 h-16 bg-neutral-200 dark:bg-neutral-700">
                    <div class="m-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="block w-8 h-8 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793">
                            <path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" />
                        </svg>
                    </div>
                    @if($order_item['photo'])
                        <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-order-items/' . $order_item['photo'] }}" />
                    @endif
                </div>
                <div class="flex-1">
                    <div class="text-sm text-neutral-500 mb-1">
                        @if($can_edit_item_details)
                            <i class="icon-edit text-green-500 mr-1"></i>{{ __('Manual entry - dapat diedit') }}
                        @else
                            <i class="icon-database text-blue-500 mr-1"></i>{{ __('Dari inventaris - tidak dapat diedit') }}
                        @endif
                    </div>
                    <div class="font-medium">{{ $order_item['name'] }}</div>
                    <div class="text-sm text-neutral-500">{{ $order_item['desc'] }}</div>
                </div>
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

            {{-- Purpose --}}
            <div>
                <label for="purpose" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Keperluan') }}</label>
                <textarea id="purpose" wire:model="purpose" 
                         class="w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                         rows="3" maxlength="500"></textarea>
            </div>

            {{-- Quantity and UOM --}}
            <div class="grid grid-cols-2 gap-x-4">
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

            {{-- Pricing (editable for manual entries only) --}}
            @if($can_edit_item_details)
                <div class="grid grid-cols-1 gap-y-4">
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

            {{-- Budget Selection --}}
            <div>
                <label for="budget" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Budget') }}</label>
                <x-select wire:model.live="budget_id" class="w-full">
                    <option value="">{{ __('Pilih budget...') }}</option>
                    @foreach($budgets as $budget)
                        <option value="{{ $budget['id'] }}">
                            {{ $budget['name'] }} ({{ $budget['inv_curr']['name'] }} {{ number_format($budget['balance'], 2) }})
                        </option>
                    @endforeach
                </x-select>
            </div>

            {{-- Amount Summary --}}
            @if($qty > 0 && isset($order_item['total_amount']))
                <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>{{ __('Total amount') }}:</span>
                        <span>{{ $this->getItemCurrency() }} {{ number_format($order_item['total_amount'], 2) }}</span>
                    </div>
                    
                    @if($budget_id && isset($order_item['exchange_rate_used']) && $order_item['exchange_rate_used'] != 1)
                        <div class="flex justify-between text-neutral-600 dark:text-neutral-400">
                            <span>{{ __('Kurs') }}:</span>
                            <span>{{ number_format($order_item['exchange_rate_used'], 4) }}</span>
                        </div>
                    @endif
                    
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
                <x-text-input id="eval_message" wire:model="eval_message" type="text" class="w-full" placeholder="{{ __('Jelaskan alasan perubahan...') }}" />
            </div>

            {{-- Evaluation History --}}
            @if(count($evaluations) > 0)
                <div>
                    <h3 class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-3">{{ __('Riwayat evaluasi') }}</h3>
                    <div class="space-y-3 max-h-40 overflow-y-auto">
                        @foreach($evaluations as $eval)
                            <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-3 text-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="font-medium">{{ $eval['user']['name'] }}</div>
                                    <div class="text-xs text-neutral-500">{{ \Carbon\Carbon::parse($eval['created_at'])->format('d/m/Y H:i') }}</div>
                                </div>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Qty') }}:</span>
                                    <span>{{ $eval['qty_before'] }} → {{ $eval['qty_after'] }}</span>
                                </div>
                                @if($eval['message'])
                                    <div class="text-neutral-600 dark:text-neutral-400">{{ $eval['message'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Actions --}}
    <div class="border-t border-neutral-200 dark:border-neutral-700 px-6 py-4">
        <div class="flex justify-between">
            <x-secondary-button type="button" wire:click="deleteOrderItem" 
                wire:confirm="{{ __('Yakin ingin menghapus butir pesanan ini?') }}"
                class="text-red-600 hover:text-red-700">
                <i class="icon-trash mr-2"></i>{{ __('Hapus') }}
            </x-secondary-button>
            
            <div class="flex space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Batal') }}
                </x-secondary-button>
                
                <div wire:loading>
                    <x-primary-button type="button" disabled>
                        <i class="icon-save mr-2"></i>{{ __('Simpan') }}
                    </x-primary-button>
                </div>
                <div wire:loading.remove>
                    <x-primary-button type="button" wire:click="update">
                        <i class="icon-save mr-2"></i>{{ __('Simpan') }}
                    </x-primary-button>
                </div>
            </div>
        </div>
    </div>

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>