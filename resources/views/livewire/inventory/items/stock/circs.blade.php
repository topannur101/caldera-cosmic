<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\On;
use App\Models\InvCirc;

new class extends Component
{
   use WithPagination;

   public $perPage = 5;

   #[Reactive]
   public $stock_id = 0;

   public $stock_id_old = 0;

   #[On('circ-created')]
   public function with(): array
   {
      $this->stock_id_old == $this->stock_id ?: $this->resetPage();
      $this->stock_id_old = $this->stock_id;
      
      $circs = InvCirc::latest('created_at')
      ->where('inv_stock_id', $this->stock_id)
      ->paginate($this->perPage);

      return [
         'circs' => $circs,
      ];
   }

}

?>

<div>
   <div wire:key="circs-modal">
      <x-modal name="circ-edit">
         <div class="p-6">
            Editing...
         </div>
      </x-modal>
   </div>
   @if ($circs->count())
      <table wire:key="circs" class="w-full [&_td]:py-2 [&_tr_td:first-child]:w-[1%] [&_tr_td:last-child]:w-[1%]">
         @foreach ($circs as $circ)
            <x-inv-circ-tr wire:key="circ-{{ $circ->id }}"
            color="{{ $circ->type_color() }}" 
            icon="{{ $circ->type_icon() }}" 
            qty_relative="{{ $circ->qty_relative }}" 
            uom="{{ $circ->inv_stock->uom }}" 
            user_name="{{ $circ->user->name }}" 
            user_emp_id="{{ $circ->user->emp_id }}"
            user_photo="{{ $circ->user->photo }}"
            is_delegated="{{ $circ->is_delegated }}" 
            eval_user_name="{{ $circ->eval_user?->name }}" 
            eval_user_emp_id="{{ $circ->eval_user?->emp_id }}" 
            created_at_friendly="{{ $circ->created_at->diffForHumans() }}" 
            remarks="{{ $circ->remarks }}" 
            eval_icon="{{ $circ->eval_icon() }}"></x-inv-circ-tr>      
         @endforeach
      </table>
      <div class="px-3 my-1">
         {{ $circs->onEachSide(1)->links(data: ['scrollTo' => false]) }}
      </div>
   @else
      <div class="py-4 text-neutral-500 text-center">
            {{ __('Tak ada sirkulasi') }}
      </div>
   @endif
</div>