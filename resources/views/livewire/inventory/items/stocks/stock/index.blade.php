<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;

new class extends Component {

   #[Reactive]
   public int $stock_id = 0;

   #[Reactive]
   public int $qty = 0;

   #[Reactive]
   public string $uom = '';

}

?>

<div class="flex flex-col md:flex-row gap-y-4 justify-between items-center">
   <div class="flex gap-x-2 items-baseline">
      <div class="text-4xl">{{ $qty }}</div>
      <div class="text-sm font-bold">{{ $uom }}</div>
   </div>
   <div class="relative sm:static flex gap-x-3">
      <livewire:inventory.items.stocks.stock.circ.form type="deposit" :$stock_id :$uom />
      <livewire:inventory.items.stocks.stock.circ.form type="capture" :$stock_id :$uom />
      <livewire:inventory.items.stocks.stock.circ.form type="withdrawal" :$stock_id :$uom />
   </div>
</div>