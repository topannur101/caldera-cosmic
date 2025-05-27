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
   public int $qty = 0;
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
         'qty' => ['required', 'integer', 'min:0', 'max:1000000'],
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
         $this->dispatch('order-item-created');
         $this->dispatch('remove-photo');
         $this->resetForm();
         $this->js('window.dispatchEvent(escKey)');

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

<div class="relative flex flex-col h-full">
   <div class="flex justify-between items-start pt-6 pb-3 px-6">
      <h2 class="text-lg font-medium">
         {{ __('Pesanan baru') }}
      </h2>
      <x-text-button type="button" x-on:click="window.dispatchEvent(escKey)">
         <i class="icon-x"></i>
      </x-text-button>
   </div>
   <div class="grow overflow-y-auto">      
      @if(!$area_id)
         <div class="flex flex-col h-full justify-center gap-y-4 px-6 mx-auto">
            <div class="py-3 text-center">
               <i class="text-7xl icon-house relative text-neutral-300 dark:text-neutral-600">
                  <i class="icon-circle-help absolute bottom-0 right-2 text-lg text-neutral-900 dark:text-neutral-100"></i>
               </i>
            </div>
            <div class="text-sm text-center pb-6">{{ __('Akunmu memiliki wewenang ke lebih dari satu area inventaris. Pilih satu area untuk melanjutkan.') }}</div>
            {{-- Area Selection --}}
            <div>
               <label for="form-area" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Area') }}</label>
               <x-select id="form-area" wire:model.change="area_id" class="w-full">
                  <option value="0"></option>
                  @foreach($areas as $area)
                     <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
                  @endforeach
               </x-select>
            </div>
         </div>
         @else
         <div class="flex flex-col gap-y-6 py-4 px-6">
            {{-- Photo upload --}}
            <div>
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Foto') }}</label>
               <livewire:inventory.items.photo size="sm" :id="0" :is_editing="true" :photo_url="$photo ? ('/storage/inv-order-items/' . $photo) : ''" />
            </div>
            {{-- Item Details --}}
            <div class="grid grid-cols-1 gap-y-4">
               <div>
                  <label for="form-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                  <x-text-input id="form-code" wire:model="code" type="text" class="w-full" maxlength="11" />
               </div>
               <div>
                  <label for="form-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                  <x-text-input id="form-name" wire:model="name" type="text" class="w-full" />
               </div>
               <div>
                  <label for="form-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Deskripsi') }}</label>
                  <x-text-input id="form-desc" wire:model="desc" type="text" class="w-full" />
               </div>
            </div>
            {{-- Currency and Unit Price --}}
            <div>
               <label for="form-unit_price" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Harga dan satuan') }}</label>
               <div class="btn-group">
                  <x-select id="form-unit-price" wire:model.change="currency_id">
                     <option value=""></option>
                     @foreach($currencies as $currency)
                        <option value="{{ $currency['id'] }}">{{ $currency['name'] }}</option>
                     @endforeach
                  </x-select>
                  <x-text-input id="form-unit_price" wire:model.change="unit_price" type="number" step="0.01" min="0" class="w-full rounded-none border-x-0" />
                  <div class="block p-2 border-y border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 shadow-sm">/</div>
                  <x-text-input id="form-uom" wire:model="uom" type="text" class="border-l-0 w-24" :fullWidth="false" placeholder="{{ __('Satuan') }}" maxlength="5" />
               </div>
            </div>
            {{-- Quantity and Purpose --}}
            <div class="grid grid-cols-3 gap-x-3 gap-y-4">
               <div>
                  <label for="form-qty" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Qty') }}</label>
                  <x-text-input id="form-qty" wire:model.change="qty" type="number" min="0" class="w-full" />
               </div>
               <div class="col-span-2">
                  <label for="form-purpose" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Keperluan') }}</label>
                  <x-text-input id="form-purpose" wire:model="purpose" type="text" class="w-full" />
               </div>
            </div>

            {{-- Budget Selection (Optional) --}}         
            <div>
               <label for="form-budget" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                  {{ __('Anggaran') }}
               </label>
               <div class="mx-3">
                  @if(count($budgets) > 0)
                     @foreach($budgets as $budget)
                        <x-radio 
                              wire:model.change="budget_id" 
                              id="form-budget-{{ $budget['id'] }}" 
                              name="budget-selection" 
                              value="{{ $budget['id'] }}">
                              {{ $budget['name'] }}
                        </x-radio>
                     @endforeach
                  @else
                     <div class="text-sm text-neutral-500">{{ __('Tidak ada anggaran terdaftar') }}</div>
                  @endif
               </div>
            </div>         
            {{-- Amount Summary --}}
            @if($qty > 0 && $unit_price > 0 && $currency_id)
            <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4 space-y-2 text-sm">
               <div class="flex justify-between">
                  <span>{{ __('Amount') }}:</span>
                  <span>{{ $this->getItemCurrency() }} {{ number_format($total_amount, 2) }}</span>
               </div>
               
               @if($budget_id)
                  @if($exchange_rate_used != 1)
                  <div class="flex justify-between">
                     <span>{{ __('Amount anggaran') }}:</span>
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
            {{-- Error message --}}
            @if ($errors->any())
               <div class="px-6">
                  <div class="text-center">
                     <x-input-error :messages="$errors->first()" />
                  </div>
               </div>
            @endif
            {{-- Save button --}}
            <div class="flex justify-between items-center">
               @if(count($areas) > 1)
               <x-text-button type="button" class="rounded-full text-xs px-1 bg-caldy-600 bg-opacity-40 text-white" x-on:click="$wire.set('area_id', 0);">{{ collect($areas)->firstWhere('id', $area_id)['name'] ?? __('Area belum dipilih') }} <i class="icon-x ml-1"></i></x-text-button>
               @else
               <div class="text-neutral-500 text-xs">{{ collect($areas)->firstWhere('id', $area_id)['name'] ?? __('Area belum dipilih') }}</div>

               @endif
               <x-primary-button type="button" wire:click="save">
                  {{ __('Simpan') }}
               </x-primary-button>
            </div>
         </div>
      @endif
      
   </div>
   <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>