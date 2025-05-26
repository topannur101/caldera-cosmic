<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;

use App\InvQuery;
use App\Models\User;
use App\Models\InvArea;

new class extends Component {

   use WithPagination;

   public int $perPage = 24;

   public string $view = 'item_search';
   
   public array $areas = [];
   
   public int $area_id = 0;

   public string $q = '';

   public array $items = [
      0 => [
         'id'              => 0,
         'name'            => '',
         'desc'            => '',
         'code'            => '',
         'loc_id'          => 0,
         'loc_name'        => '',
         'tags_list'       => '',
         'photo'           => '',
         'area_id'         => 0,
         'area_name'       => '',
         'is_active'       => false,
         'updated_at'      => '',
         'last_deposit'    => '',
         'last_withdrawal' => '',
         'comments_count' => 0
      ]
   ];

   public function mount()
   {
      $this->areas = Auth::user()->auth_inv_areas();

      if (count($this->areas) === 1)
      {
         $this->area_id = $this->areas[0]['id'];
      }
   }

   public function with(): array
   {
      $inv_stocks_query = new InvQuery([
         'type'      => 'stocks',
         'search'    => trim($this->q),
         'is_linked' => true,
         'area_ids'  => [$this->area_id],
         'sort'      => 'updated'
      ]);

      return [
         'inv_stocks' => $inv_stocks_query->build()->paginate($this->perPage),
      ];
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

   @switch($view)
      @case('item_search')
         <div class="flex flex-col gap-y-3 px-6">
            <x-select wire:model.live="area_id" class="w-full">
               <option value=""></option>
               @foreach ($areas as $area)
                  <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
               @endforeach
            </x-select>
            <x-text-input-icon wire:model.live="q" icon="icon-search" id="item-search" placeholder="{{ __('Cari barang...') }}"></x-text-input-icon>
         </div>
         <div class="grow overflow-y-auto">
            @foreach($inv_stocks as $stock)
            <div class="px-6 mb-6 flex gap-x-3 text-sm">
               <div>
                  <div class="rounded-sm overflow-hidden relative flex w-28 h-28 bg-neutral-200 dark:bg-neutral-700">
                     <div class="m-auto">
                        <svg xmlns="http://www.w3.org/2000/svg"  class="block w-6 h-6 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793"><path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" /></svg>
                     </div>
                     @if($stock->inv_item->photo)
                        <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-items/' . $stock->inv_item->photo }}" />
                     @endif
                  </div> 
               </div>
               <div class="grow truncate">
                  <div class="p-1 truncate font-semibold" title="{{ $stock->inv_item->name }}">
                     {{ $stock->inv_item->name }}
                  </div>
                  <div class="p-1 truncate text-neutral-500" title="{{ $stock->inv_item->desc }}">
                     {{ $stock->inv_item->desc }}
                  </div>
                  <div class="p-1 flex gap-x-1 items-center text-xs text-neutral-500">
                     <div class="uppercase">
                           {{ $stock->inv_item->code ? $stock->inv_item->code : __('Tak ada kode')}}
                     </div>
                     <div>•</div>
                     <div title="{{ $stock->unit_price ? ($stock->inv_curr->name  . ' ' . number_format($stock->unit_price, 0) . ' / ' . $stock->uom) : (' • ' .__('Tak ada harga')) }}">
                           {{ $stock->unit_price ? ($stock->inv_curr->name  . ' ' . number_format($stock->unit_price, 0)) : __('Tak ada harga') }}
                     </div>
                  </div>
                  <div class="p-1 flex gap-x-2 items-center text-xs text-neutral-500">
                     <div title="{{ $stock->inv_item->inv_loc_id ? ($stock->inv_item->inv_loc->parent . '-' . $stock->inv_item->inv_loc->bin ) : __('Tak ada lokasi') }}">
                           <i class="icon-map-pin mr-1"></i>{{ $stock->inv_item->inv_loc_id ? ($stock->inv_item->inv_loc->parent . '-' . $stock->inv_item->inv_loc->bin ) : __('Tak ada lokasi') }}
                     </div>
                     <div class="truncate" title="{{ $stock->inv_item->tags_facade() ?: __('Tak ada tag') }}">
                           <i class="icon-tag mr-1"></i>{{ $stock->inv_item->tags_facade() ?: __('Tak ada tag') }}
                     </div>
                  </div>
               </div>
               <div class="text-right">
                  <div class="p-1">{{ $stock->qty }}</div>
                  <div class="p-1 text-nowrap text-neutral-500">{{ $stock->uom }}</div>
                  <div class="p-1 text-xs text-neutral-500">
                     <i class="icon-chevrons-down-up mr-1"></i>{{ $stock->qty_min . '-' . $stock->qty_max }}
                  </div>
               </div>
            </div>   
            @endforeach
         </div>
         @break

      @case('item_form')    
         <!-- <div class="flex flex-col h-full overflow-y-auto p-6"></div>     
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
                  <label for="item-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                  <x-text-input id="item-code" type="text" />                        
               </div>
            </div>  
         </div>   -->
         @break    

   @endswitch
</div>