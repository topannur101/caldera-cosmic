<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InvOrderItem;
use App\Models\InvOrderBudget;
use App\Models\InvOrderEval;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public array $order_item_ids = [];
    public array $order_items = [];
    public array $budgets = [];
    
    // Form fields
    public int $qty = 0;
    public int $budget_id = 0;
    public string $eval_message = '';
    
    // Update options
    public bool $update_qty = false;
    public bool $update_budget = false;
    
    public bool $can_edit = false;
    public string $validation_message = '';

    #[On('bulk-edit-order-items')]
    public function loadOrderItems(array $orderItemIds)
    {
        $this->order_item_ids = $orderItemIds;
        
        $orderItems = InvOrderItem::with([
            'inv_area',
            'inv_order_budget'
        ])
        ->whereIn('id', $orderItemIds)
        ->whereNull('inv_order_id')
        ->get();

        if ($orderItems->count() === 0) {
            $this->can_edit = false;
            $this->validation_message = __('Tidak ada item yang dapat diedit.');
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
                'area_id' => $item->inv_area_id,
                'area_name' => $item->inv_area->name,
                'budget_id' => $item->inv_order_budget_id,
                'budget_name' => $item->inv_order_budget->name,
            ];
        })->toArray();

        // Validate same area requirement
        $areas = collect($this->order_items)->pluck('area_id')->unique();
        if ($areas->count() > 1) {
            $this->can_edit = false;
            $this->validation_message = __('Semua butir pesanan harus dari area yang sama untuk dapat diedit secara massal.');
        } else {
            $this->can_edit = true;
            $this->validation_message = '';
            
            // Load budgets for the area
            $areaId = $areas->first();
            $this->loadBudgets($areaId);
            
            // Set default values based on most common values
            $this->setDefaultValues();
        }
    }

    private function loadBudgets(int $areaId)
    {
        $this->budgets = InvOrderBudget::where('inv_area_id', $areaId)
            ->where('is_active', true)
            ->with('inv_curr')
            ->get()
            ->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'name' => $budget->name,
                    'balance' => $budget->balance,
                    'currency_name' => $budget->inv_curr->name,
                ];
            })
            ->toArray();
    }

    private function setDefaultValues()
    {
        // Set most common quantity as default
        $quantities = collect($this->order_items)->pluck('qty');
        $this->qty = $quantities->mode()->first() ?? 1;
        
        // Set most common budget as default
        $budgetIds = collect($this->order_items)->pluck('budget_id');
        $this->budget_id = $budgetIds->mode()->first() ?? 0;
    }

    public function bulkUpdate()
    {
        if (!$this->can_edit) {
            $this->js('toast("' . $this->validation_message . '", { type: "danger" })');
            return;
        }

        $this->eval_message = trim($this->eval_message);

        $rules = [
            'eval_message' => ['required', 'max:256'],
        ];

        if ($this->update_qty) {
            $rules['qty'] = ['required', 'integer', 'min:1', 'max:1000000'];
        }

        if ($this->update_budget) {
            $rules['budget_id'] = ['required', 'exists:inv_order_budget,id'];
        }

        // Check if at least one update option is selected
        if (!$this->update_qty && !$this->update_budget) {
            $this->js('toast("' . __('Pilih minimal satu opsi untuk diperbarui') . '", { type: "danger" })');
            return;
        }

        $this->validate($rules);

        try {
            $updatedCount = 0;

            foreach ($this->order_item_ids as $orderItemId) {
                $orderItem = InvOrderItem::find($orderItemId);
                
                if (!$orderItem || !is_null($orderItem->inv_order_id)) {
                    continue; // Skip items that are already finalized
                }

                $qtyBefore = $orderItem->qty;
                $hasChanges = false;

                // Update quantity
                if ($this->update_qty && $orderItem->qty != $this->qty) {
                    $orderItem->qty = $this->qty;
                    $hasChanges = true;
                }

                // Update budget
                if ($this->update_budget && $orderItem->inv_order_budget_id != $this->budget_id) {
                    $orderItem->inv_order_budget_id = $this->budget_id;
                    $hasChanges = true;
                }

                if ($hasChanges) {
                    // Recalculate amounts
                    $orderItem->updateBudgetAllocation();
                    
                    // Create evaluation record
                    InvOrderEval::create([
                        'inv_order_item_id' => $orderItem->id,
                        'user_id' => Auth::id(),
                        'qty_before' => $qtyBefore,
                        'qty_after' => $orderItem->qty,
                        'message' => $this->eval_message,
                    ]);

                    $updatedCount++;
                }
            }

            $this->dispatch('order-items-bulk-edited');
            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Berhasil memperbarui ') . $updatedCount . __(' butir pesanan') . '", { type: "success" })');

        } catch (\Exception $e) {
            $this->js('toast("' . __('Terjadi kesalahan saat memperbarui') . '", { type: "danger" })');
        }
    }

    public function getSelectedItemsCount(): int
    {
        return count($this->order_items);
    }

    public function getAreaName(): string
    {
        return collect($this->order_items)->first()['area_name'] ?? '';
    }
}

?>

<div class="p-6 flex flex-col gap-y-6 max-h-[80vh] overflow-hidden">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            <i class="icon-edit mr-2"></i>{{ __('Edit massal butir pesanan') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')">
            <i class="icon-x"></i>
        </x-text-button>
    </div>

    @if(!$can_edit)
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
        {{-- Summary --}}
        <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4">
            <h3 class="font-medium mb-3">{{ __('Ringkasan') }}</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-neutral-500">{{ __('Total item') }}:</span>
                    <span class="font-medium ml-2">{{ $this->getSelectedItemsCount() }}</span>
                </div>
                <div>
                    <span class="text-neutral-500">{{ __('Area') }}:</span>
                    <span class="font-medium ml-2">{{ $this->getAreaName() }}</span>
                </div>
            </div>
        </div>

        {{-- Update Options --}}
        @if($can_edit)
            <div class="space-y-4">
                <h3 class="font-medium">{{ __('Pilih field yang akan diperbarui') }}</h3>

                {{-- Quantity Update --}}
                <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-4">
                    <div class="flex items-center gap-x-3 mb-3">
                        <x-checkbox id="update_qty" wire:model.live="update_qty">
                            {{ __('Perbarui quantity') }}
                        </x-checkbox>
                    </div>
                    
                    @if($update_qty)
                        <div class="ml-6">
                            <label for="qty" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Quantity baru') }}</label>
                            <x-text-input id="qty" wire:model="qty" type="number" min="1" class="w-full" />
                            <div class="text-xs text-neutral-500 mt-1">
                                {{ __('Semua item yang dipilih akan diubah ke quantity ini') }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Budget Update --}}
                <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-4">
                    <div class="flex items-center gap-x-3 mb-3">
                        <x-checkbox id="update_budget" wire:model.live="update_budget">
                            {{ __('Perbarui budget') }}
                        </x-checkbox>
                    </div>
                    
                    @if($update_budget)
                        <div class="ml-6">
                            <label for="budget_id" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Budget baru') }}</label>
                            <x-select wire:model="budget_id" class="w-full">
                                <option value="">{{ __('Pilih budget...') }}</option>
                                @foreach($budgets as $budget)
                                    <option value="{{ $budget['id'] }}">
                                        {{ $budget['name'] }} ({{ $budget['currency_name'] }} {{ number_format($budget['balance'], 2) }})
                                    </option>
                                @endforeach
                            </x-select>
                            <div class="text-xs text-neutral-500 mt-1">
                                {{ __('Semua item yang dipilih akan dialokasikan ke budget ini') }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Evaluation Message --}}
                <div>
                    <label for="eval_message" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Alasan perubahan') }}</label>
                    <x-text-input id="eval_message" wire:model="eval_message" type="text" class="w-full" placeholder="{{ __('Jelaskan alasan perubahan massal...') }}" />
                    <div class="text-xs text-neutral-500 mt-1">
                        {{ __('Alasan ini akan dicatat untuk semua item yang diperbarui') }}
                    </div>
                </div>
            </div>
        @endif

        {{-- Selected Items List --}}
        <div>
            <h3 class="font-medium mb-3">{{ __('Item yang akan diperbarui') }}</h3>
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
                                    <div class="text-xs text-neutral-500">{{ $item['budget_name'] }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Preview Changes --}}
        @if($can_edit && ($update_qty || $update_budget))
            <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">{{ __('Preview perubahan') }}</h4>
                <div class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                    @if($update_qty)
                        <div>• {{ __('Quantity semua item akan diubah menjadi: ') }}<strong>{{ $qty }}</strong></div>
                    @endif
                    @if($update_budget && $budget_id)
                        <div>• {{ __('Budget semua item akan dialokasikan ke: ') }}<strong>{{ collect($budgets)->firstWhere('id', $budget_id)['name'] ?? '' }}</strong></div>
                    @endif
                    <div>• {{ __('Evaluasi akan dicatat dengan alasan: ') }}<strong>{{ $eval_message ?: __('(belum diisi)') }}</strong></div>
                </div>
            </div>
        @endif
    </div>

    {{-- Actions --}}
    <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
        <div class="flex justify-end space-x-3">
            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                {{ __('Batal') }}
            </x-secondary-button>
            
            @if($can_edit)
                <div wire:loading>
                    <x-primary-button type="button" disabled>
                        <i class="icon-save mr-2"></i>{{ __('Perbarui') }}
                    </x-primary-button>
                </div>
                <div wire:loading.remove>
                    <x-primary-button type="button" wire:click="bulkUpdate">
                        <i class="icon-save mr-2"></i>{{ __('Perbarui') }}
                    </x-primary-button>
                </div>
            @else
                <x-primary-button type="button" disabled>
                    <i class="icon-save mr-2"></i>{{ __('Perbarui') }}
                </x-primary-button>
            @endif
        </div>
    </div>

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>