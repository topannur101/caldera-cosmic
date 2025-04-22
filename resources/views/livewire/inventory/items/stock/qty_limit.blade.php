<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;

use App\Models\InvStock;

new class extends Component {

   public string $type = '';

   #[Reactive]
   public int $stock_id = 0;

   #[Reactive]
   public int $stock_qty_limit = 0;

   public function save($qty_limit)
   {
      try {
         $validator = Validator::make(
            ['qty_limit' => $qty_limit ],
            ['qty_limit' => 'required|integer|lte:100000']
        );

        if ($validator->fails()) {
            throw new \Exception($validator->messages->first());
        }
         
         $stock = InvStock::find($this->stock_id);
         if (!$stock){
            throw new \Exception(__('Unit stok tidak ditemukan'));
         }

         $item = $stock->inv_item;

         $itemEval = Gate::inspect('store', $item);
         if ($itemEval->denied())
         {
             throw new \Exception(__('Tak ada wewenang untuk mengelola barang ini'));
         };

         switch ($this->type) {
            case 'min':
               $stock->update([
                  'qty_min' => $qty_limit
               ]);
               $this->js('toast("'. __('Qty minimum diperbarui') .'", { type: "success" })');
               $this->dispatch('limit-updated');
               break;
            
            case 'max':
               $stock->update([
                  'qty_max' => $qty_limit
               ]);
               $this->js('toast("'. __('Qty maksimum diperbarui') .'", { type: "success" })');
               $this->dispatch('limit-updated');
               break;
         }

      } catch (\Throwable $th) {
         $this->js('toast("'. $th->getMessage() .'", { type: "danger" })');
      } 
   

   }
}

?>

<div x-data="{ qty_limit:0, stock_qty_limit: @entangle('stock_qty_limit'), open: false }" x-on:click.away="open = false">
   <div x-show="!open" x-on:click="open = !open; qty_limit = stock_qty_limit" x-text="stock_qty_limit" class="px-2">0</div>
   <form x-show="open" x-cloak x-on:submit.prevent="$wire.save(qty_limit); open = false" class="w-32">
      <x-text-input type="number" x-model="qty_limit"></x-text-input>
   </form>
</div>