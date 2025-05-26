<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;

use App\InvQuery;

new class extends Component {

   use WithPagination;

   public int $perPage = 24;
   
   public array $areas = [];
   
   public int $area_id = 0;

   public string $q = '';


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

<div class="h-full flex flex-col gap-y-6 pt-6">
   <div class="flex justify-between items-start px-6">
      <h2 class="text-lg font-medium ">
         {{ __('Pesanan baru') }}
      </h2>
      <x-text-button type="button" @click="slideOverOpen = false">
         <i class="icon-x"></i>
      </x-text-button>
   </div>


</div>