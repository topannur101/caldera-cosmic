<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\InvArea;
use App\Models\User;


new class extends Component {

   use WithPagination;

   public int $perPage = 24;

   public string $q = '';   
   
   public array $qwords = []; // caldera: do you need it?

   public array $users = [];

   public string $purpose = '';

   public int $user_id = 0;

   public array $areas = [];

    
   #[Url]
   public array $area_ids = [];

   public function mount()
   {        
      $user_id = Auth::user()->id;

      if ($user_id === 1) {
            $areas = InvArea::all();
      } else {
            $user = User::find($user_id);
            $areas = $user->inv_areas;
      }

      $this->areas = $areas->toArray();

      $ordersParams = session('inv_orders_params', []);

      if ($ordersParams) {
         $this->q                = $ordersParams['q']                 ?? '';
         $this->area_ids         = $ordersParams['area_ids']          ?? [];
         $this->purpose          = $ordersParams['purpose']           ?? '';
      }

      $areasParam  = session('inv_areas_param', []);

      $areasParam 
      ? $this->area_ids = $areasParam ?? [] 
      : $this->area_ids = $areas->pluck('id')->toArray();

   }

   public function with(): array
   {
      $q       = trim($this->q);
      $purpose = trim($this->purpose);

      $inv_orders_params = [
         'q'         => $q,
         'user_id'   => $this->user_id,
         'purpose'   => $purpose
      ];

      session(['inv_orders_params' => $inv_orders_params]);
      session(['inv_areas_param' => $this->area_ids]);

      return [];

   }

   public function resetQuery()
   {
      session()->forget('inv_orders_params');
      $this->redirect(route('inventory.orders.index'), navigate: true);
   }

};

?>

<div>
   <div class="static lg:sticky top-0 z-10 py-6">
      <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2">
         <div class="flex gap-x-2 items-center px-8 py-2 lg:px-4 lg:py-0">
               <i wire:loading.remove class="icon-search {{ $q ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600' }}"></i>
               <i wire:loading class="w-4 relative">
                  <x-spinner class="sm mono"></x-spinner>
               </i>
               <div class="w-full md:w-32">
                  <x-text-input-t wire:model.live="q" id="inv-q" name="inv-q" class="h-9 py-1 placeholder-neutral-400 dark:placeholder-neutral-600"
                     type="search" list="qwords" placeholder="{{ __('Cari...') }}" autofocus autocomplete="inv-q" />
                  <datalist id="qwords">
                     @if (count($qwords))
                           @foreach ($qwords as $qword)
                              <option value="{{ $qword }}">
                           @endforeach
                     @endif
                  </datalist>
               </div>
         </div> 
         
         <div class="flex items-center gap-x-4 p-4 lg:py-0 ">
            <x-inv-user-selector isQuery="true" class="text-xs font-semibold uppercase" />
         </div>

         <div class="grow flex items-center gap-x-4 p-4 lg:py-0 ">
            <x-inv-purpose-filter class="text-xs font-semibold uppercase" />
         </div>

         <div class="flex items-center justify-between gap-x-4 p-4 lg:py-0">
            <x-inv-area-selector is_grow="true" class="text-xs font-semibold uppercase" :$areas />
            <div>
               <x-slide-over>
                  <x-slot name="trigger">
                     <x-primary-button type="button" @click="slideOverOpen=true"><i class="icon-pencil"></i></x-secondary-button>
                  </x-slot>
                  <x-slot name="content">
                     <livewire:inventory.orders.form />
                  </x-slot>
               </x-slide-over>
            </div>
            <div>
               <x-dropdown align="right" width="60">
                  <x-slot name="trigger">
                     <x-text-button><i class="icon-ellipsis"></i></x-text-button>
                  </x-slot>
                  <x-slot name="content">
                     <x-dropdown-link href="#" wire:click.prevent="resetQuery">
                        <i class="icon-rotate-cw me-2"></i>{{ __('Reset')}}
                     </x-dropdown-link>
                  </x-slot>
               </x-dropdown>
            </div>
         </div>
      </div>
   </div>

</div>
