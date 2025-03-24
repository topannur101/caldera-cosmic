<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InvStock;
use App\Models\InvCirc;

new #[Layout('layouts.app')] class extends Component {

   public string $view = 'initial';
    
   public array $circ_ids = [];

   public string $eval_remarks = '';

   public array $results_grouped = [
      'success' => [],
      'failure' => []
   ];

   #[On('eval-circ-ids')]
   public function load($ids)
   {
      $this->customReset();
      $this->circ_ids = $ids;
   }

   public function evaluate(string $type)
   {      
      if(!count($this->circ_ids)) {
         $this->js('toast("' . __('Tidak ada sirkulasi yang dipilih'). '", { type: "danger" })');
         $this->js('$dispatch("close")');
         return;
      }

      $results = [];
      foreach ($this->circ_ids as $id) {      
         
         $circ = InvCirc::find($id);

         if (!$circ) {
            $results[] = [
               'success' => false,
               'message' => __('Sirkulasi tidak ditemukan'),
            ];
            break;
         }
         
         $stock = $circ->inv_stock;
         $results[] = $stock->updateByCirc($type, $circ->toArray(), $this->eval_remarks);
      }

     
      foreach ($results as $result) {
         $key = $result['success'] ? 'success' : 'failure';
         $message = $result['message'];
      
         if (isset($this->results_grouped[$key][$message])) {
               $this->results_grouped[$key][$message]++;
         } else {
               $this->results_grouped[$key][$message] = 1;
         }
      }

      $this->dispatch('circ-evaluated');
      $this->view = 'results';
   }

   public function customReset()
   {
      $this->reset(['view', 'circ_ids', 'eval_remarks', 'results_grouped']);
   }



};

?>

<div class="p-6 flex flex-col gap-y-6">
   <div class="flex justify-between items-start">
      <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
         <i class="fa fa-gavel mr-2"></i>
         {{ __('Evaluasi') }}
      </h2>
      <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
   </div>
   @switch($view)
      @case('initial')
         <div class="text-sm">
            <x-pill>{{ count($circ_ids) }}</x-pill>{{ ' ' . __('sirkulasi akan dievaluasi.') }}
         </div>
         <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="evalRemarks">{{ __('Keterangan evaluasi') }}</label>
            <x-text-input wire:model="eval_remarks" id="eval-remarks" autocomplete="eval-remarks" />
         </div>
         <div class="px-3 text-neutral-500 text-sm text-wrap"><i class="fa fa-exclamation-triangle mr-2"></i>{{ __('Sirkulasi yang telah dievaluasi tidak dapat diralat.') }}</div>
         <div class="flex justify-end">
            <div class="btn-group">
               <x-secondary-button type="button" wire:click="evaluate('approve')"><i class="fa fa-thumbs-up mr-2"></i>{{ __('Setujui') }}</x-secondary-button>
               <x-secondary-button type="button" wire:click="evaluate('reject')" class="h-full"><i class="fa fa-thumbs-down"></i></x-secondary-button>
            </div>
         </div>
         @break

      @case('results')
         <div>
            <h3 class="font-bold text-xs uppercase tracking-wide">{{ __('Berhasil') }}</h3>
            <ul class="list-inside text-sm text-neutral-500">
               @foreach($results_grouped['success'] as $message => $count)
                  <li>
                     <x-pill class="font-mono" color="green">{{ $count }}</x-badge><span class="ml-2">{{ $message }}</span>
                  </li>
               @endforeach
               @if(!$results_grouped['success'])
                  <li>{{ __('Tidak ada yang berhasil') }}</li>
               @endif
            </ul>
         </div>
         <div>
            <h3 class="font-bold text-xs uppercase tracking-wide">{{ __('Gagal') }}</h3>
            <ul class="list-inside text-sm text-neutral-500">
               @foreach($results_grouped['failure'] as $message => $count)
                  <li>
                     <x-pill class="font-mono" color="red">{{ $count }}</x-badge><span class="ml-2">{{ $message }}</span>
                  </li>
               @endforeach
               @if(!$results_grouped['failure'])
                  <li>{{ __('Tidak ada yang gagal') }}</li>
               @endif
            </ul>
         </div>

         <div class="flex justify-end">
            <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Tutup') }}</x-secondary-button>
         </div>
         @break
         
   @endswitch

   <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>