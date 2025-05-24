<?php

use Livewire\Volt\Component;

new class extends Component
{
   public int $stock_id = 0;

   public function placeholder()
   {
       return view('livewire.layout.modal-placeholder');
   }

}

?>

<div>
   <div class="p-6 flex justify-between">
      <h2 class="text-lg font-medium">
         {{ __('Grafik sirkulasi') . $stock_id }}
      </h2>
      <div>
         <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
      </div>
   </div>
   <div class="p-6">{{ __('Fitur ini sedang dalam tahap pengembangan') }}</div>

   <!-- Buttons -->
   <div class="p-6 flex justify-end">
      <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Tutup') }}</x-secondary-button>
   </div>
   <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>