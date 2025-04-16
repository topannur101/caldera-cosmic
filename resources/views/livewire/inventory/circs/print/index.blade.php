<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use App\Models\InvCirc;
use Carbon\Carbon;

new class extends Component {

   public string $print_page = '';
   
   public array $circ_ids = [];

   #[On('print-circ-ids')]
   public function load($ids, $print_page)
   {
      $this->circ_ids   = $ids;      
      $this->print_page = $print_page;
   }

   public function with()
   {
      $circs = InvCirc::with(['inv_stock', 'user', 'eval_user', 'inv_stock.inv_item', 'inv_stock.inv_item.inv_area'])->whereIn('id', $this->circ_ids)->get();
      if ($circs) {
         $this->dispatch('print-ready');
      }

      return [
         'circs' => $circs,
      ];

   }
};

?>

<div id="print-container" class="w-[1200px] mx-auto p-4 aspect-[210/297] bg-white text-neutral-900 cal-offscreen">
   @switch($print_page)
      @case('approval-form')
         <h1 class="text-2xl font-bold">{{ __('Persetujuan sirkulasi') }}</h1>
            <div>{{ Carbon::now()->format('l, d M Y, H:i:s') }}</div>
            <div class="flex justify-between py-4">
               <div>{{ $circs->count() . ' sirkulasi' }}</div>
               <div class="grid grid-cols-3 gap-x-3 uppercase text-xs text-center">
                  <div class="border-black px-4 py-1 border-t">{{ __('Pengguna') }}</div>
                  <div class="border-black px-4 py-1 border-t">{{ __('Penyetuju') . ' 1' }}</div>
                  <div class="border-black px-4 py-1 border-t">{{ __('Penyetuju') . ' 2' }}</div>
               </div>
            </div>
            <table class="w-full table text-xs [&_th]:p-1 [&_td]:p-1">
               <tr class="uppercase text-xs">
                  <th>{{ __('Qty') }}</th>
                  <th colspan="2">{{ __('Nama') . ' & ' . __('Deskripsi') }}</th>
                  <th>{{ __('Kode') }}</th>
                  <th><i class="fa fa-map-marker-alt"></i></th>
                  <th><i class="fa fa-user"></i></th>
                  <th>{{ __('Keterangan') }}</th>
                  <th>{{ __('Area') }}</th>
               </tr>
               @foreach ($circs as $circ)
                  <x-inv-circ-circs-tr
                     is_print="true"
                     id="{{ $circ->id }}"
                     color="{{ $circ->type_color() }}" 
                     icon="{{ $circ->type_icon() }}" 
                     qty_relative="{{ $circ->qty_relative }}" 
                     uom="{{ $circ->inv_stock->uom }}" 
                     user_name="{{ $circ->user->name }}" 
                     user_emp_id="{{ $circ->user->emp_id }}"
                     user_photo="{{ $circ->user->photo }}"
                     is_delegated="{{ $circ->is_delegated }}" 
                     eval_status="{{ $circ->eval_status }}"
                     eval_user_name="{{ $circ->eval_user?->name }}" 
                     eval_user_emp_id="{{ $circ->eval_user?->emp_id }}" 
                     updated_at="{{ $circ->updated_at }}" 
                     remarks="{{ $circ->remarks }}" 
                     eval_icon="{{ $circ->eval_icon() }}"
                     item_id="{{ $circ->inv_stock->inv_item->id }}"
                     item_photo="{{ $circ->inv_stock->inv_item->photo }}"
                     item_name="{{ $circ->inv_stock->inv_item->name }}"
                     item_desc="{{ $circ->inv_stock->inv_item->desc }}"
                     item_code="{{ $circ->inv_stock->inv_item->code }}"
                     item_loc="{{ $circ->inv_stock->inv_item->inv_loc_id ? ($circ->inv_stock->inv_item->inv_loc->parent . '-' . $circ->inv_stock->inv_item->inv_loc->bin) : null }}"
                     area_name="{{ $circ->inv_stock->inv_item->inv_area->name }}">
                  </x-inv-circ-circs-tr>     
               @endforeach
            </table>
         @break
      @case('label-small')
         <div class="text-center">
            @foreach ($circs as $circ)
               <div class="inline-block w-[6cm] h-[2.5cm] border border-neutral-500">
                  <div class="flex flex-col gap-y-2 text-center w-full h-full">
                     <div class="font-bold truncate">
                        {{ $circ->inv_stock->inv_item->name }}
                     </div>
                     <div class="grow line-clamp-3 text-xs">
                        {{ $circ->inv_stock->inv_item->desc }}
                     </div>
                     <div>
                        {{ $circ->inv_stock->inv_item->code }}
                     </div>
                  </div>
               </div>
            @endforeach
            @if($circs->count() % 3 === 1)
               <div class="inline-block w-[6cm] h-[2.5cm]">.</div><div class="inline-block w-[6cm] h-[2.5cm]">.</div>
            @elseif($circs->count() % 3 === 2)
               <div class="inline-block w-[6cm] h-[2.5cm]">.</div>
            @endif
         </div>
         @break
      @case('label-large')
         <div class="text-center">
            @foreach ($circs as $circ)
               <div class="inline-block w-[8.5cm] h-[5.5cm] border border-neutral-500 p-2">
                  <div class="flex flex-col gap-y-4 text-center w-full h-full text-xl">
                     <div class="font-bold line-clamp-2">
                        {{ $circ->inv_stock->inv_item->name }}
                     </div>
                     <div class="grow line-clamp-3">
                        {{ $circ->inv_stock->inv_item->desc }}
                     </div>
                     <div>
                        {{ $circ->inv_stock->inv_item->code }}
                     </div>
                  </div>
               </div>
            @endforeach
            @if($circs->count() % 3 === 1)
               <div class="inline-block w-[8.5cm] h-[5.5cm]">.</div><div class="inline-block w-[8.5cm] h-[5.5cm]">.</div>
            @elseif($circs->count() % 3 === 2)
               <div class="inline-block w-[8.5cm] h-[5.5cm]">.</div>
            @endif
         </div>
         @break
   @endswitch
</div>
