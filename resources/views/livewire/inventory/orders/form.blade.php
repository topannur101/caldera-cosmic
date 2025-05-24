<?php

use Livewire\Volt\Component;

use App\Models\User;
use App\Models\InvArea;

new class extends Component {

   public string $q = '';

   public array $areas = [];

   public int $area_id = 0;

   public function mount()
   {
      $this->areas = Auth::user()->auth_inv_areas();

      if (count($this->areas) === 1)
      {
         $this->area_id = $this->areas[0]['id'];
      }
   }
   
};

?>



<div>
   <div class="flex justify-between items-start p-6">
      <h2 class="text-lg font-medium ">
         {{ __('Pesanan baru') }}
      </h2>
      <x-text-button type="button" @click="slideOverOpen = false">
         <i class="icon-x"></i>
      </x-text-button>
   </div>

   <div class="flex flex-col gap-y-3 p-6">
      <x-select wire:model="area_id" class="w-full mt-4">
         <option value=""></option>
         @foreach ($areas as $area)
            <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
         @endforeach
      </x-select>
      <x-text-input-icon icon="icon-search" id="item-search" placeholder="{{ __('Cari barang...') }}"></x-text-input-icon>
   </div>

   <div class="flex flex-col h-full overflow-y-auto p-6">
      <div class="grid gap-y-4">
         <div>
            <label for="item-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="item-name" type="text" />
         </div>
         <div>
            <label for="item-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Deskripsi') }}</label>
            <x-text-input id="item-desc" type="text" />                        
         </div>
         <div>
            <label for="item-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
            <x-text-input id="item-desc" type="text" />                        
         </div>
      </div>
   </div>
</div>