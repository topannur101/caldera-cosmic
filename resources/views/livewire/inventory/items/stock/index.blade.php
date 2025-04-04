<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;

new class extends Component {

   #[Reactive]
   public int $stock_id = 0;

   #[Reactive]
   public int $stock_qty = 0;

   #[Reactive]
   public string $stock_uom = '';

   #[Reactive]
   public int $curr_id = 1;

   #[Reactive]
   public float $curr_rate = 1;

   #[Reactive]
   public float $unit_price = 0;   
   
   #[Reactive]
   public bool $can_create = false;

   #[Reactive]
   public bool $can_eval = false;

}

?>

<div class="flex flex-col md:flex-row gap-y-4 justify-between items-center">
   <div class="flex gap-x-2 items-baseline">
      <div class="text-4xl">{{ $stock_qty }}</div>
      <div class="text-sm font-bold">{{ $stock_uom }}</div>
   </div>
   <div class="relative sm:static flex gap-x-2">
      @if($can_create)
         <livewire:inventory.items.stock.create-circ type="deposit"    :$stock_id :$stock_uom :$curr_id :$curr_rate :$unit_price :$can_eval />
         <livewire:inventory.items.stock.create-circ type="capture"    :$stock_id :$stock_uom :$curr_id :$curr_rate :$unit_price :$can_eval />
         <livewire:inventory.items.stock.create-circ type="withdrawal" :$stock_id :$stock_uom :$curr_id :$curr_rate :$unit_price :$can_eval />
      @endif
   </div>
</div>