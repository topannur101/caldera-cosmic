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
               <i wire:loading.remove class="fa fa-fw fa-search {{ $q ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600' }}"></i>
               <i wire:loading class="fa fa-fw relative">
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
               <x-inv-area-selector class="text-xs font-semibold uppercase" :$areas />
               <div>
                  <div x-data="{ slideOverOpen: false }"
                     class="relative w-auto h-auto">
                     <x-primary-button type="button" @click="slideOverOpen=true">{{ __('Buat') }}</x-secondary-button>
                     <template x-teleport="body">
                        <div 
                           x-show="slideOverOpen"
                           @keydown.window.escape="slideOverOpen=false"
                           class="relative z-50">
                           <div x-show="slideOverOpen" class="fixed inset-0 transform transition-all"
                              x-on:click="slideOverOpen = false"
                              x-transition:enter="ease-out duration-300"
                              x-transition:enter-start="opacity-0"
                              x-transition:enter-end="opacity-100"
                              x-transition:leave="ease-in duration-200"
                              x-transition:leave-start="opacity-100"
                              x-transition:leave-end="opacity-0"
                           >
                              <div class="absolute inset-0 bg-neutral-500 dark:bg-neutral-900 opacity-75"></div>
                           </div>
                           <div class="fixed inset-0 overflow-hidden">
                              <div class="absolute inset-0 overflow-hidden">
                                 <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
                                    <div 
                                       x-show="slideOverOpen" 
                                       @click.away="slideOverOpen = false"
                                       x-transition:enter="transform transition ease-out duration-300" 
                                       x-transition:enter-start="translate-x-full" 
                                       x-transition:enter-end="translate-x-0" 
                                       x-transition:leave="transform transition ease-in duration-200" 
                                       x-transition:leave-start="translate-x-0" 
                                       x-transition:leave-end="translate-x-full" 
                                       class="w-screen max-w-md">
                                       <div class="bg-white dark:bg-neutral-800 flex flex-col h-full text-neutral-900 dark:text-neutral-100">
                                          <div class="flex justify-between items-start p-6">
                                             <h2 class="text-lg font-medium ">
                                                {{ __('Butir pesanan baru') }}
                                             </h2>
                                             <x-text-button type="button" @click="slideOverOpen = false">
                                                <i class="fa fa-times"></i>
                                             </x-text-button>
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
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </template>
                  </div>
               </div>
               <div>
                  <x-dropdown align="right" width="60">
                     <x-slot name="trigger">
                        <x-text-button><i class="fa fa-fw fa-ellipsis-h"></i></x-text-button>
                     </x-slot>
                     <x-slot name="content">
                        <x-dropdown-link href="#" wire:click.prevent="resetQuery">
                           <i class="fa fa-fw fa-undo me-2"></i>{{ __('Reset')}}
                        </x-dropdown-link>
                     </x-slot>
                  </x-dropdown>
               </div>
         </div>
      </div>
   </div>

</div>
