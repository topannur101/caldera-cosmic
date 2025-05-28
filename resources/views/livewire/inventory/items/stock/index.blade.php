<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;

new class extends Component {

   public int $item_id = 0;

   #[Reactive]
   public int $stock_id = 0;

   #[Reactive]
   public int $stock_qty = 0;

   #[Reactive]
   public int $stock_qty_min = 0;

   #[Reactive]
   public int $stock_qty_max = 0;

   #[Reactive]
   public string $stock_uom = '';

   #[Reactive]
   public float $stock_wf = 0;

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
   <div>
      <div wire:key="modals">
         <x-modal name="edit-qty-limit">
            <livewire:inventory.items.stock.edit-qty-limit :$stock_id :$stock_qty_min :$stock_qty_max />
         </x-modal>
         <x-modal name="create-used-unit" maxWidth="sm">
            <livewire:inventory.items.stock.create-used-unit :$stock_id />
         </x-modal>
      </div>
      <table class="text-sm text-neutral-500 mx-auto md:mx-0">
         <tr>
            <td>{{ __('Min') . ': ' }}</td>
            <td class="px-2">{{ $stock_qty_min }}</td>
            <td>{{ $stock_uom }}</td>
         </tr>
         <tr>
            <td>{{ __('Maks') . ': ' }}</td>
            <td class="px-2">{{ $stock_qty_max }}</td>
            <td>{{ $stock_uom }}</td>
         </tr>
      </table>
      <div class="text-sm text-neutral-500 mt-2">
         @if ($stock_wf < 1 && $stock_wf > 0)
            {{ __('Diambil :days kali sehari', ['days' => round(1 / $stock_wf, 1)]) }}
         @elseif ($stock_wf > 0)
            {{ __('Diambil :days hari sekali', ['days' => round($stock_wf, 0)]) }}
         @endif         
      </div>
   </div>
   <div class="relative sm:static flex items-center gap-x-2">
      @if($can_create)
         <livewire:inventory.items.stock.create-circ 
            type="deposit"    
            :$stock_id 
            :$stock_uom 
            :$curr_id 
            :$curr_rate 
            :$unit_price 
            :$can_eval />
         <livewire:inventory.items.stock.create-circ 
            type="capture"    
            :$stock_id 
            :$stock_uom 
            :$curr_id 
            :$curr_rate 
            :$unit_price 
            :$can_eval />
         <livewire:inventory.items.stock.create-circ 
            type="withdrawal" 
            :$stock_id 
            :$stock_uom 
            :$curr_id 
            :$curr_rate 
            :$unit_price 
            :$can_eval />
      @endif
      <livewire:inventory.items.stock.create-order 
         :$stock_id 
         :$stock_uom 
         :$curr_id 
         :$curr_rate 
         :$unit_price />
      <div>
         <x-dropdown align="right" width="60">
            <x-slot name="trigger">
               <x-text-button><i class="icon-ellipsis-vertical hidden sm:inline"></i><i class="icon-ellipsis sm:hidden inline"></i></x-text-button>
            </x-slot>
            <x-slot name="content">
               <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'edit-qty-limit'); $wire.$dispatch('edit-qty-limit');">
                  <i class="icon-chevrons-down-up mr-2"></i>{{ __('Edit batas qty') }}
               </x-dropdown-link>
               <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'create-used-unit'); $wire.$dispatch('create-used-unit');">
                  <i class="icon-blank mr-2"></i>{{ __('Buat unit stok bekas') }}
               </x-dropdown-link>
            </x-slot>
         </x-dropdown>
      </div>
   </div>
</div>