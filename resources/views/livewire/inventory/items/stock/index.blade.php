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
   public bool $can_eval = false;

}

?>

<div class="flex flex-col md:flex-row gap-y-4 justify-between items-center">
   <div class="flex gap-x-2 items-baseline">
      <div class="text-4xl">{{ $stock_qty }}</div>
      <div class="text-sm font-bold">{{ $stock_uom }}</div>
   </div>
   <div class="relative sm:static flex gap-x-3">
      <livewire:inventory.items.stock.circ-form type="deposit"    :$stock_id :$stock_uom :$can_eval />
      <livewire:inventory.items.stock.circ-form type="capture"    :$stock_id :$stock_uom :$can_eval />
      <livewire:inventory.items.stock.circ-form type="withdrawal" :$stock_id :$stock_uom :$can_eval />
   </div>
</div>