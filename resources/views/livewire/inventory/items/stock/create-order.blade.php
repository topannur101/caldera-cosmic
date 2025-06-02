<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;
use App\Models\InvOrderItem;
use App\Models\InvOrderBudget;
use App\Models\InvStock;
use Illuminate\Support\Facades\Auth;

new class extends Component {

   #[Reactive]
   public int $stock_id = 0;

   #[Reactive]
   public string $stock_uom = '';

   #[Reactive]
   public int $curr_id = 1;

   #[Reactive]
   public float $curr_rate = 1;

   #[Reactive]
   public float $unit_price = 0;

   // Pre-populated data (loaded from stock)
   public int $item_id = 0;
   public string $item_name = '';
   public string $item_desc = '';
   public string $item_code = '';
   public string $item_photo = '';
   public int $item_area_id = 0;

   // Form fields (visible)
   public string $purpose = '';
   public int $qty = 0;
   public int $budget_id = 0;

   // Calculated fields
   public float $total_amount = 0;
   public float $amount_budget = 0;
   public float $exchange_rate_used = 1.00;

   public array $budgets = [];

   public function mount()
   {
      $this->loadStockData();
   }

   public function updated($property)
   {
      // Reload data when stock_id changes
      if ($property === 'stock_id') {
         $this->loadStockData();
      }
   }

   private function loadStockData()
   {
      // Load stock and item details
      if ($this->stock_id) {
         $stock = InvStock::with(['inv_item', 'inv_curr'])->find($this->stock_id);
         if ($stock && $stock->inv_item) {
            // Item data
            $this->item_id = $stock->inv_item_id;
            $this->item_name = $stock->inv_item->name;
            $this->item_desc = $stock->inv_item->desc;
            $this->item_code = $stock->inv_item->code ?? '';
            $this->item_photo = $stock->inv_item->photo;
            $this->item_area_id = $stock->inv_item->inv_area_id;
            
            // Load budgets for this area
            $this->loadBudgets();
            
            // Reset form when switching stocks
            $this->reset(['purpose', 'qty', 'budget_id', 'total_amount', 'amount_budget', 'exchange_rate_used']);
         }
      }
   }

   public function updatedQty()
   {
      $this->calculateAmounts();
   }

   public function updatedBudgetId()
   {
      $this->calculateAmounts();
   }

   private function loadBudgets()
   {
      if ($this->item_area_id) {
         $this->budgets = InvOrderBudget::where('inv_area_id', $this->item_area_id)
            ->where('is_active', true)
            ->with('inv_curr')
            ->get()
            ->toArray();
      }
   }

   private function calculateAmounts()
   {
      $this->total_amount = $this->qty * $this->unit_price;
      
      if ($this->budget_id) {
         $budget = collect($this->budgets)->firstWhere('id', $this->budget_id);
         
         if ($budget) {
            $budgetCurrencyRate = $budget['inv_curr']['rate'];
            $itemCurrencyRate = $this->curr_rate;
            
            $this->exchange_rate_used = $budgetCurrencyRate / $itemCurrencyRate;
            $this->amount_budget = $this->total_amount * $this->exchange_rate_used;
         }
      } else {
         $this->amount_budget = 0;
         $this->exchange_rate_used = 1.00;
      }
   }

   public function save()
   {
      $this->purpose = trim($this->purpose);

      $this->validate([
         'purpose' => ['required', 'max:500'],
         'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
         'budget_id' => ['required', 'exists:inv_order_budget,id'],
      ]);

      // Check budget availability
      $budget = InvOrderBudget::find($this->budget_id);
      if (!$budget->hasSufficientFunds($this->amount_budget)) {
         $this->js('toast("' . __('Budget tidak mencukupi') . '", { type: "danger" })');
         return;
      }

      try {
         // Recalculate amounts to ensure accuracy
         $this->calculateAmounts();

         $orderItem = InvOrderItem::create([
            'inv_order_id' => null, // Open order
            'inv_item_id' => $this->item_id, // Linked to inventory item
            'inv_area_id' => $this->item_area_id,
            'inv_curr_id' => $this->curr_id,
            'inv_order_budget_id' => $this->budget_id,
            'name' => $this->item_name,
            'desc' => $this->item_desc,
            'code' => $this->item_code,
            'photo' => $this->item_photo,
            'purpose' => $this->purpose,
            'qty' => $this->qty,
            'uom' => $this->stock_uom,
            'unit_price' => $this->unit_price,
            'total_amount' => $this->total_amount,
            'amount_budget' => $this->amount_budget,
            'exchange_rate_used' => $this->exchange_rate_used,
         ]);

         $this->dispatch('close-popover');
         $this->js('toast("' . __('Pesanan berhasil dibuat') . '", { type: "success" })');
         $this->reset(['purpose', 'qty', 'budget_id', 'total_amount', 'amount_budget', 'exchange_rate_used']);

      } catch (\Exception $e) {
         $this->js('toast("' . __('Terjadi kesalahan saat menyimpan') . '", { type: "danger" })');
      }
   }

   public function getBudgetCurrency()
   {
      if ($this->budget_id) {
         $budget = collect($this->budgets)->firstWhere('id', $this->budget_id);
         return $budget ? $budget['inv_curr']['name'] : '';
      }
      return '';
   }
}

?>

<x-popover-button focus="order-qty" icon="icon-shopping-cart">
   <div x-data="{
         qty: @entangle('qty'),
         budget_id: @entangle('budget_id'),
         budgets: @entangle('budgets'),
         unit_price: @entangle('unit_price'),
         curr_rate: @entangle('curr_rate'),
         
         // Calculated properties
         total_amount: 0,
         amount_budget: 0,
         budget_currency: '',
         
         // Calculate amounts
         calculate() {
            this.total_amount = this.qty * this.unit_price;
            
            if (this.budget_id) {
               const budget = this.budgets.find(b => b.id == this.budget_id);
               if (budget) {
                  this.budget_currency = budget.inv_curr.name;
                  const budget_rate = budget.inv_curr.rate;
                  const exchange_rate = budget_rate / this.curr_rate;
                  this.amount_budget = this.total_amount * exchange_rate;
               }
            } else {
               this.amount_budget = 0;
               this.budget_currency = '';
            }
         },
         
         // Format number for display
         formatNumber(num) {
            return new Intl.NumberFormat('en-US', {
               minimumFractionDigits: 2,
               maximumFractionDigits: 2
            }).format(num);
         }
      }" 
      x-init="calculate(); 
              $watch('qty', () => calculate());
              $watch('budget_id', () => calculate());
              $watch('unit_price', () => calculate());
              $watch('curr_rate', () => calculate());">
      
      <form wire:submit="save" class="grid grid-cols-1 gap-y-4">
         <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="order-qty">{{ __('Quantity') }}</label>
            <x-text-input-suffix x-model="qty" suffix="{{ $stock_uom }}" id="order-qty" class="text-center" name="order-qty"
            type="number" value="" min="1" placeholder="Qty" />
         </div>
         
         <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="order-purpose">{{ __('Keperluan') }}</label>
            <x-text-input wire:model="purpose" id="order-purpose" autocomplete="order-purpose" />
         </div>

         <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Budget') }}</label>
            <div class="mx-3">
               @if(count($budgets) > 0)
                  <template x-for="budget in budgets" :key="budget.id">
                     <div class="flex items-center">
                        <input 
                           x-model="budget_id"
                           :id="'order-budget-' + budget.id" 
                           name="order-budget-selection" 
                           type="radio"
                           :value="budget.id"
                           class="w-4 h-4 text-caldy-600 bg-neutral-100 border-neutral-300 focus:ring-2 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-neutral-800 dark:bg-neutral-700 dark:border-neutral-600">
                        <label :for="'order-budget-' + budget.id" class="p-2 text-sm" x-text="budget.name"></label>
                     </div>
                  </template>
               @else
                  <div class="text-sm text-neutral-500">{{ __('Tidak ada budget tersedia') }}</div>
               @endif
            </div>
         </div>

         {{-- Amount Summary (Alpine Preview) --}}
         <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-3 space-y-1 text-sm">
            <div class="flex justify-between">
               <span x-text="budget_currency"></span>
               <span class="font-mono" x-text="formatNumber(amount_budget)"></span>
            </div>
         </div>

         @if ($errors->any())
            <div>
               <x-input-error :messages="$errors->first()" />
            </div>
         @endif
         
         <div class="text-right">
            <x-secondary-button type="submit">
               <i class="icon-shopping-cart mr-2"></i>{{ __('Pesan') }}
            </x-secondary-button>
         </div>
      </form>
   </div>
   <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="purpose"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" wire:target.except="purpose" class="hidden"></x-spinner>
</x-popover-button>