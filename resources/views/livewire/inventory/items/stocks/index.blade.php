<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\InvStock;

new class extends Component
{
   public int $item_id = 0;

   #[Url]
   public int $stock_id = 0;

   public int $stock_qty = 0;

   public array $stocks = [];

   public bool $can_eval = false;

   public bool $can_create = false;

   #[On('circ-evaluated')]
   public function init()
   {
      $stocks = InvStock::with(['inv_curr', 'inv_item'])
      ->where('inv_item_id', $this->item_id)
      ->where('is_active', true)->get();
      
      $inv_item = $stocks->first()?->inv_item;
      $this->can_eval   = $inv_item ? Gate::inspect('circEval', $inv_item)->allowed() : false;
      $this->can_create = $inv_item ? Gate::inspect('circCreate', $inv_item)->allowed() : false;

      $this->stocks  = $stocks ? $stocks->toArray() : [];  
      $this->area_id = $stocks ? $stocks[0]['inv_item']['inv_area_id'] : 0;
   }

   public function mount()
   {
      $this->init();
   }
 
   public function with()
   {
      $stock_id   = 0;
      $stock_uom  = '';
      $curr_id = 1;
      $curr_rate = 1;
      $unit_price = 0;  
      
      if ($this->stock_id) {
         $stock = collect($this->stocks)->firstWhere('id', $this->stock_id);
         if ($stock) {
            $stock_id         = $stock['id'];
            $this->stock_qty  = $stock['qty'];
            $stock_uom        = $stock['uom'];
            $curr_id          = $stock['inv_curr_id'];
            $curr_rate        = $stock['inv_curr']['rate'];
            $unit_price       = $stock['unit_price'];
         }
      } else {
         $stock = collect($this->stocks)->first();
         if ($stock) {
            $this->stock_id   = $stock['id'];
            $stock_id         = $stock['id'];
            $this->stock_qty  = $stock['qty'];
            $stock_uom        = $stock['uom'];
            $curr_id          = $stock['inv_curr_id'];
            $curr_rate        = $stock['inv_curr']['rate'];
            $unit_price       = $stock['unit_price'];
         }
      }

      return [
         'stock_id'  => $stock_id,
         'stock_uom' => $stock_uom,
         'curr_id'   => $curr_id,
         'curr_rate' => $curr_rate,
         'unit_price' => $unit_price
      ];
   }
};

?>

<div class="relative mt-6 flex flex-col gap-6 py-6 bg-white dark:bg-neutral-800 shadow rounded-none sm:rounded-lg">
   <div class="flex flex-col gap-y-6">
      <div class="px-6 text-sm text-center text-neutral-500 border-b border-neutral-200 dark:border-neutral-700">
         <ul class="flex flex-wrap gap-x-4 uppercase">
            @foreach($stocks as $stock)
               <li class="me-2">
                  <div wire:click="$set('stock_id', {{ $stock['id'] }})"
                     class="cursor-pointer inline-block pb-3 border-b-2 
                     @if($stock['id'] == $stock_id) text-neutral-800 font-bold dark:text-neutral-200 border-caldy-500 rounded-t-lg active dark:border-caldy-500 
                     @else border-transparent hover:text-neutral-600 hover:border-neutral-300 dark:hover:text-neutral-300 @endif">
                        {{ $stock['unit_price'] . ' ' .$stock['inv_curr']['name'] . ' / ' . $stock['uom'] }}
                     </div>
               </li>
            @endforeach
         </ul>
      </div> 
      <div class="px-6">
         <livewire:inventory.items.stock.index :$stock_id :$stock_qty :$stock_uom :$curr_id :$curr_rate :$unit_price :$can_eval :$can_create  />
      </div>
      <div class="truncate">
         <livewire:inventory.items.stock.circs :$stock_id />
      </div>
         <!-- <div class="flex justify-between items-center mb-4">
         <div class="uppercase text-neutral-500 text-sm">
               {{ __('Sirkulasi') }}
         </div>
         <div class="btn-group">
            <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'inv-item-circs-chart')"><i
               class="fa fa-chart-line"></i></x-secondary-button>
            <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'inv-item-circs-download')"><i
               class="fa fa-download"></i></x-secondary-button>
         </div>
      </div> -->
   </div>  
   <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>