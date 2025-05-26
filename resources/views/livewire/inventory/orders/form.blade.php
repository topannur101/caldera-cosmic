<?php

use Livewire\Volt\Component;
use App\Models\InvCurr;
use App\Models\InvOrderBudget;
use App\Models\InvOrderItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\On;

new class extends Component {

   public array $areas = [];
   public array $budgets = [];
   public array $currencies = [];
   
   public int $area_id = 0;
   public int $budget_id = 0;
   public int $currency_id = 0;
   
   public string $name = '';
   public string $desc = '';
   public string $code = '';
   public string $photo = '';
   public string $purpose = '';
   public int $qty = 1;
   public string $uom = '';
   public float $unit_price = 0;
   
   public float $total_amount = 0;
   public float $amount_budget = 0;
   public float $exchange_rate_used = 1.00;

   public function mount()
   {
      $user_id = Auth::user()->id;
      
      if ($user_id === 1) {
         $areas = \App\Models\InvArea::all();
      } else {
         $user = \App\Models\User::find($user_id);
         $areas = $user->inv_areas;
      }
      
      $this->areas = $areas->toArray();
      $this->currencies = InvCurr::where('is_active', true)->get()->toArray();

      if (count($this->areas) === 1) {
         $this->area_id = $this->areas[0]['id'];
         $this->loadBudgets();
      }
   }

   public function updatedAreaId()
   {
      $this->budget_id = 0;
      $this->loadBudgets();
      $this->calculateAmounts();
   }

   public function updatedBudgetId()
   {
      $this->calculateAmounts();
   }

   public function updatedCurrencyId()
   {
      $this->calculateAmounts();
   }

   public function updatedQty()
   {
      $this->calculateAmounts();
   }

   public function updatedUnitPrice()
   {
      $this->calculateAmounts();
   }

   private function loadBudgets()
   {
      if ($this->area_id) {
         $this->budgets = InvOrderBudget::where('inv_area_id', $this->area_id)
            ->where('is_active', true)
            ->with('inv_curr')
            ->get()
            ->toArray();
      } else {
         $this->budgets = [];
      }
   }

   private function calculateAmounts()
   {
      $this->total_amount = $this->qty * $this->unit_price;
      
      // Only calculate budget amounts if budget is selected
      if ($this->budget_id && $this->currency_id) {
         $budget = collect($this->budgets)->firstWhere('id', $this->budget_id);
         $itemCurrency = collect($this->currencies)->firstWhere('id', $this->currency_id);
         
         if ($budget && $itemCurrency) {
            $budgetCurrencyRate = $budget['inv_curr']['rate'];
            $itemCurrencyRate = $itemCurrency['rate'];
            
            $this->exchange_rate_used = $budgetCurrencyRate / $itemCurrencyRate;
            $this->amount_budget = $this->total_amount * $this->exchange_rate_used;
         }
      } else {
         // Reset budget calculations if no budget selected
         $this->amount_budget = 0;
         $this->exchange_rate_used = 1.00;
      }
   }

   #[Renderless]
   #[On('photo-updated')]
   public function insertPhoto($photo)
   {
      $this->photo = $photo;
   }

   public function save()
   {
      // Clean up inputs
      $this->name = trim($this->name);
      $this->desc = trim($this->desc);
      $this->code = strtoupper(trim($this->code));
      $this->purpose = trim($this->purpose);
      $this->uom = strtoupper(trim($this->uom));

      $this->validate([
         'area_id' => ['required', 'exists:inv_areas,id'],
         'budget_id' => ['required', 'exists:inv_order_budget,id'], // Made nullable
         'currency_id' => ['required', 'exists:inv_currs,id'],
         'name' => ['required', 'max:128'],
         'desc' => ['required', 'max:256'],
         'code' => ['required', 'alpha_dash', 'size:11'],
         'purpose' => ['required', 'max:500'],
         'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
         'uom' => ['required', 'alpha_dash', 'max:5'],
         'unit_price' => ['required', 'numeric', 'min:0', 'max:1000000000'],
      ]);

      // Check budget availability only if budget is selected
      if ($this->budget_id) {
         $budget = InvOrderBudget::find($this->budget_id);
         if (!$budget->hasSufficientFunds($this->amount_budget)) {
            $this->js('toast("' . __('Budget tidak mencukupi') . '", { type: "danger" })');
            return;
         }
      }

      try {
         // Recalculate amounts to ensure accuracy
         $this->calculateAmounts();

         $orderItem = InvOrderItem::create([
            'inv_order_id' => null, // Open order
            'inv_item_id' => null, // Always null for manual entry
            'inv_area_id' => $this->area_id,
            'inv_curr_id' => $this->currency_id,
            'inv_order_budget_id' => $this->budget_id ?: null, // Allow null
            'name' => $this->name,
            'desc' => $this->desc,
            'code' => $this->code,
            'photo' => $this->photo,
            'purpose' => $this->purpose,
            'qty' => $this->qty,
            'uom' => $this->uom,
            'unit_price' => $this->unit_price,
            'total_amount' => $this->total_amount,
            'amount_budget' => $this->amount_budget,
            'exchange_rate_used' => $this->exchange_rate_used,
         ]);

         $this->js('toast("' . __('Pesanan berhasil dibuat') . '", { type: "success" })');
         $this->dispatch('orderItemCreated');
         $this->dispatch('remove-photo');
         $this->resetForm();
         $this->js('slideOverOpen = false');

      } catch (\Exception $e) {
         $this->js('toast("' . __('Terjadi kesalahan saat menyimpan') . '", { type: "danger" })');
      }
   }

   private function resetForm()
   {
      $this->reset([
         'budget_id', 'currency_id',
         'name', 'desc', 'code', 'photo', 'purpose', 'qty', 'uom', 'unit_price',
         'total_amount', 'amount_budget', 'exchange_rate_used'
      ]);
      $this->qty = 1;
   }

   public function getBudgetBalance()
   {
      if ($this->budget_id) {
         $budget = collect($this->budgets)->firstWhere('id', $this->budget_id);
         return $budget ? $budget['balance'] : 0;
      }
      return 0;
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
};

?>

<div class="h-full flex flex-col gap-y-6 pt-6">
   <div class="flex justify-between items-start px-6">
      <h2 class="text-lg font-medium">
         {{ __('Butir pesanan baru') }}
      </h2>
      <x-text-button type="button" @click="slideOverOpen = false">
         <i class="icon-x"></i>
      </x-text-button>
   </div>

   @if ($errors->any())
      <div class="px-6">
         <div class="text-center">
            <x-input-error :messages="$errors->first()" />
         </div>
      </div>
   @endif

   <div class="flex-1 overflow-y-auto px-6 space-y-6">
      
      {{-- Area Selection --}}
      <div>
         <label for="area" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Area') }}</label>
         <x-select wire:model.live="area_id" class="w-full">
            <option value="">{{ __('Pilih area...') }}</option>
            @foreach($areas as $area)
               <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
            @endforeach
         </x-select>
      </div>

      {{-- Photo Upload --}}
      @if($area_id)
      <div>
         <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Foto') }}</label>
         <livewire:inventory.items.photo :id="0" :is_editing="true" :photo_url="$photo ? ('/storage/inv-order-items/' . $photo) : ''" />
      </div>

      {{-- Item Details --}}
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

         <div>
            <label for="purpose" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Keperluan') }}</label>
            <textarea id="purpose" wire:model="purpose" 
                     class="w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                     rows="3" maxlength="500"></textarea>
         </div>
      </div>

      {{-- Quantity and Pricing --}}
      <div class="grid grid-cols-2 gap-x-4 gap-y-4">
         <div>
            <label for="qty" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Qty') }}</label>
            <x-text-input id="qty" wire:model.live="qty" type="number" min="1" class="w-full" />
         </div>

         <div>
            <label for="uom" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Satuan') }}</label>
            <x-text-input id="uom" wire:model="uom" type="text" class="w-full" maxlength="5" />
         </div>
      </div>

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

      {{-- Budget Selection (Optional) --}}
      <div>
         <label for="budget" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
            {{ __('Budget') }} <span class="text-xs text-neutral-400">({{ __('Opsional') }})</span>
         </label>
         @if($area_id && count($budgets) > 0)
            <x-select wire:model.live="budget_id" class="w-full">
               <option value="">{{ __('Tanpa budget...') }}</option>
               @foreach($budgets as $budget)
                  <option value="{{ $budget['id'] }}">
                     {{ $budget['name'] }} ({{ $budget['inv_curr']['name'] }} {{ number_format($budget['balance'], 2) }})
                  </option>
               @endforeach
            </x-select>
         @elseif($area_id)
            <div class="text-sm text-neutral-500 px-3 py-2 border border-neutral-200 dark:border-neutral-700 rounded-md bg-neutral-50 dark:bg-neutral-900">
               {{ __('Tidak ada budget tersedia untuk area ini') }}
            </div>
         @else
            <div class="text-sm text-neutral-500 px-3 py-2 border border-neutral-200 dark:border-neutral-700 rounded-md bg-neutral-50 dark:bg-neutral-900">
               {{ __('Pilih area terlebih dahulu') }}
            </div>
         @endif
      </div>

      {{-- Amount Summary --}}
      @if($qty > 0 && $unit_price > 0 && $currency_id)
      <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4 space-y-2 text-sm">
         <div class="flex justify-between">
            <span>{{ __('Total amount') }}:</span>
            <span>{{ $this->getItemCurrency() }} {{ number_format($total_amount, 2) }}</span>
         </div>
         
         @if($budget_id)
            @if($exchange_rate_used != 1)
            <div class="flex justify-between text-neutral-600 dark:text-neutral-400">
               <span>{{ __('Kurs') }}:</span>
               <span>{{ number_format($exchange_rate_used, 4) }}</span>
            </div>
            <div class="flex justify-between">
               <span>{{ __('Amount budget') }}:</span>
               <span>{{ $this->getBudgetCurrency() }} {{ number_format($amount_budget, 2) }}</span>
            </div>
            @endif

            <div class="flex justify-between text-neutral-600 dark:text-neutral-400">
               <span>{{ __('Sisa budget') }}:</span>
               <span>{{ $this->getBudgetCurrency() }} {{ number_format($this->getBudgetBalance(), 2) }}</span>
            </div>
            
            @if($amount_budget > $this->getBudgetBalance())
            <div class="text-red-600 text-xs mt-2">
               <i class="icon-triangle-alert mr-1"></i>{{ __('Budget tidak mencukupi') }}
            </div>
            @endif
         @else
            <div class="text-neutral-600 dark:text-neutral-400 text-xs">
               {{ __('Tanpa alokasi budget') }}
            </div>
         @endif
      </div>
      @endif
      @endif
   </div>

   {{-- Actions --}}
   <div class="border-t border-neutral-200 dark:border-neutral-700 px-6 py-4">
      <div class="flex justify-end space-x-3">
         <x-secondary-button type="button" @click="slideOverOpen = false">
            {{ __('Batal') }}
         </x-secondary-button>
         
         <div wire:loading>
            <x-primary-button type="button" disabled>
               <i class="icon-save mr-2"></i>{{ __('Simpan') }}
            </x-primary-button>
         </div>
         <div wire:loading.remove>
            <x-primary-button type="button" wire:click="save" :disabled="!$area_id || !$currency_id">
               <i class="icon-save mr-2"></i>{{ __('Simpan') }}
            </x-primary-button>
         </div>
      </div>
   </div>
</div>