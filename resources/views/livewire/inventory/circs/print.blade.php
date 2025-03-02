<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InvCirc;

new class extends Component {


   public array $circ_ids = [];


    #[On('print-circ-ids')]
    public function load($ids)
    {
        $this->circ_ids = $ids;
        
    }

    public function with()
    {
      $circs = InvCirc::whereIn('id', $this->circ_ids)->get();
      if ($circs) {
         $this->dispatch('print-ready');
      }

      return [
         'circs' => $circs,
      ];
    }
};

?>

<div id="print-container" class="w-[1200px] mx-auto p-8 aspect-[210/297] bg-white text-neutral-900 cal-offscreen">
   <table class="w-full table text-xs [&_th]:p-2 [&_td]:px-2 [&_td]:py-1">
      <tr class="uppercase text-xs">
         <th>{{ __('Qty') }}</th>
         <th></th>
         <th>{{ __('Nama') }}</th>
         <th>{{ __('Deskripsi') }}</th>
         <th>{{ __('Kode') }}</th>
         <th>{{ __('Lokasi') }}</th>
         <th>{{ __('Pengguna') }}</th>
         <th>{{ __('Keterangan') }}</th>
         <th>{{ __('Diperbarui') }}</th>
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
            item_photo="{{ $circ->inv_stock->inv_item->photo }}"
            item_name="{{ $circ->inv_stock->inv_item->name }}"
            item_desc="{{ $circ->inv_stock->inv_item->desc }}"
            item_code="{{ $circ->inv_stock->inv_item->code }}"
            item_loc="{{ $circ->inv_stock->inv_item->inv_loc_id ? ($circ->inv_stock->inv_item->inv_loc->parent . '-' . $circ->inv_stock->inv_item->inv_loc->bin) : null }}">
         </x-inv-circ-circs-tr>     
      @endforeach
   </table>
</div>
