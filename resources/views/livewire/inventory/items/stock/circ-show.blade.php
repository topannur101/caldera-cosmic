<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InvCirc;
use App\Models\InvItem;
use App\Models\InvStock;

new class extends Component
{
   public array $circ = [
      'id' => 0,
      'color' => '',
      'icon' => '',
      'qty_relative' => '',
      'inv_stock' => [
         'uom' => '',
         'inv_item_id' => 0
      ],
      'inv_curr' => [
         'name' => ''
      ],
      'eval_icon' => '',
      'user' => [
         'name' => '',
      'photo' => '',
      ],
      'is_delegated' => '',
      'created_at' => '',
      'remarks' => '',
      'amount' => 0,
      'unit_price' => 0,
      'eval_user' => [
         'name' => '',
         'emp_id' => ''
      ],
      'eval_status'  => '',
      'eval_remarks' => '',
      'eval_friendly' => ''
   ];

   public bool $is_evaluating = false;
   public string $remarks = '';

   #[On('circ-show')]
   public function loadCirc(int $id)
   {
       $circ = InvCirc::with(['user', 'inv_stock', 'inv_curr', 'eval_user'])->find($id);
       if ($circ) {
            $this->circ['color'] = $circ->type_color();
            $this->circ['icon']  = $circ->type_icon();
            $this->circ['eval_icon'] = $circ->eval_icon();
            $this->circ['eval_friendly'] = $circ->eval_friendly();

            $circ = $circ->toArray();
            $this->circ = array_merge($this->circ, $circ);

            $this->resetValidation();
       } else {
           $this->handleNotFound();
       }
       $this->reset(['is_evaluating', 'remarks']);
   }

   public function with(): array
   {
       return [
           'is_superuser' => Gate::allows('superuser'),
       ];
   }

   public function evaluate($eval)
   {
      // caldera: validate first
      $stock = InvStock::find($this->circ['inv_stock_id']);
      $result = $stock->updateByCirc($eval, $this->circ, $this->remarks);

      if($result['success']) {
         $this->dispatch('circ-evaluated');
         $this->js('$dispatch("close")');
         $this->js('toast("' . $result['message'] . '", { type: "success" })');

      } else {
         $this->js('toast("' . $result['message'] . '", { type: "danger" })');

      }

   }

   public function customReset()
   {
       $this->reset(['circ', 'is_evaluating', 'remarks']);
   }

   public function handleNotFound()
   {
       $this->js('$dispatch("close")');
       $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
       $this->dispatch('updated');
   }
}

?>

<div x-data="{ is_evaluating: @entangle('is_evaluating') }" class="p-6 flex flex-col gap-y-4">

   <!-- Quantity and UOM Section -->
   <div class="flex items-center justify-between">
      <div class="flex items-center">
         <span class="{{ $circ['color'] }} text-base">
            <i class="fa fa-fw {{ $circ['icon'] }} mr-1"></i>{{ $circ['qty_relative'] . ' ' . $circ['inv_stock']['uom'] }}
         </span>
      </div>
      <div>
         <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-button>
      </div>
   </div>

   <!-- User Details Section -->
   <div class="flex gap-x-3">
      <div>
         <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
         @if ($circ['user']['photo'])
            <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/' . $circ['user']['photo'] }}" />
         @else
            <svg xmlns="http://www.w3.org/2000/svg"
                  class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                  viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
               <path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
            </svg>
         @endif
         </div>
      </div>
      <div>
         <div class="text-xs text-neutral-500">
            <span>{{ $circ['user']['name'] }}</span>
            @if($circ['is_delegated'])
               <span title="Didelegasikan"><i class="fa fa-handshake-angle"></i></span>
            @endif
         </div>
         <div class="text-base text-wrap">
            {{ $circ['remarks'] }}
         </div>
      </div>
   </div>

   <hr class="border-neutral-300 dark:border-neutral-700" />
   <div class="flex flex-col gap-y-2 text-sm">
      <div class="flex items-center gap-x-4 w-full">
         <div>
            <span class="text-neutral-500 hidden sm:inline">{{ __('Dibuat') . ': ' }}</span>
            <span class="font-medium">{{ $circ['created_at'] }}</span>
         </div>
         <div class="grow flex items-center justify-between">
            <div>
               <span class="text-neutral-500">{{ __('Status') . ': ' }}</span>
               <span class="font-medium">{{ $circ['eval_friendly'] ?? 'Disetujui' }}</span>
            </div>
            <div>
               <i class="fa fa-fw {{ $circ['eval_icon'] }}"></i>
            </div>
         </div>
      </div>
   </div>
   
   <!-- amount $circ['amount'] and unit_price info $circ['unit_price'] and currency $circ['inv_curr']['name'] info -->
   <hr class="border-neutral-300 dark:border-neutral-700" />
   <div class="grid grid-cols-2 gap-4">
      <div class="flex flex-col">
         <span class="text-sm text-neutral-500">{{ __('Harga satuan') }}</span>
         <span class="text-base">{{ $circ['unit_price'] }} {{ $circ['inv_curr']['name'] }}</span>
      </div>
      <div class="flex flex-col">
         <span class="text-sm text-neutral-500">{{ __('Amount') }}</span>
         <span class="text-base">{{ $circ['amount'] }} {{ $circ['inv_curr']['name'] }}</span>
      </div>
   </div>

   <!-- evaluation info evaluator and their remarks $circ['eval_user']['name'] and $circ['eval_remarks'] -->
   @if($circ['eval_status'] !== 'pending')
   <hr class="border-neutral-300 dark:border-neutral-700" />
   <div class="flex flex-col gap-y-2 text-sm">
      <div>
         <span class="text-neutral-500">{{ __('Evaluator') . ': ' }}</span>
         <span class="font-medium" title="{{ $circ['eval_user']['emp_id'] ?? '' }}">{{ $circ['eval_user']['name'] ?? 'Edwin' }}</span>
      </div>
      <div>
         <span>{{  $circ['eval_remarks'] ?? 'Salah isi barang eh malah' }}</span>
      </div>
   </div>
   @endif

   <hr x-show="is_evaluating" class="border-neutral-300 dark:border-neutral-700" />
   <div x-show="is_evaluating">
      <div class="mt-4">
         <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="evalRemarks">{{ __('Keterangan evaluasi') }}</label>
         <x-text-input wire:model="remarks" id="eval-remarks" x-ref="evalRemarks" />
      </div>
   </div>
   <div x-show="is_evaluating" class="flex justify-between mt-4">
      <div>
         <x-secondary-button type="button" x-on:click="is_evaluating = !is_evaluating;">{{ __('Batal') }}</x-secondary-button>
      </div>
      <div>
         <x-primary-button type="button" wire:click="evaluate('approve')"><i class="fa fa-thumbs-up mr-2"></i>{{ __('Setujui') }}</x-secondary-button>
         <x-primary-button type="button" wire:click="evaluate('reject')" class="h-full"><i class="fa fa-thumbs-down"></i></x-secondary-button>
      </div>
   </div>

   @if($circ['eval_status'] === 'pending')
   <!-- Buttons -->
   <div x-show="!is_evaluating" class="flex justify-between mt-4">
      <div>
         <x-secondary-button type="button">{{ __('Edit') }}</x-secondary-button>
      </div>
      <div>
         <x-primary-button type="button" x-on:click="is_evaluating = !is_evaluating; setTimeout(function(){ $refs.evalRemarks.focus()}, 100)">{{ __('Evaluasi') }}</x-secondary-button>
      </div>
   </div>
   @endif

   <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>