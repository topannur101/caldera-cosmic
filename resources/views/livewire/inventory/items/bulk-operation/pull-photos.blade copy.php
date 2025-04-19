<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\InvArea;
use App\Models\InvItem;
use App\Models\User;


new #[Layout('layouts.app')]
class extends Component
{
   public array $areas = [];

   public int $area_id = 0;

   public int $progress = 0;

   public array $items = [];

   public function mount()
   {
      $area_ids = [];
      $user = User::find(Auth::user()->id);

      // superuser uses id 1
      if ($user->id === 1) {
         $area_ids = InvArea::all()->pluck('id');

      } else {
         $areas = $user->inv_areas;

         foreach ($areas as $area) {
            $item = new InvItem;
            $item->inv_area_id = $area->id;
            $response = Gate::inspect('download', $item);

            if ($response->allowed()) {
               $area_ids[] = $area->id;
            }
         }
      }

      $this->areas = InvArea::whereIn('id', $area_ids)->get()->toArray();
   }

   public function begin()
   {
       while ($this->progress < 100) {
           // Stream the current count to the browser...
           $this->stream(  
               to: 'progress',
               content: $this->progress,
               replace: true,
           );

           // Pause for 1 second between numbers...
           usleep(5000);
           $this->progress++;
       };
   }

};

?>

<x-slot name="title">{{ __('Tarik foto') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Operasi massal barang') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-700 dark:text-neutral-200">
   @if (count($areas))
      <div wire:key="modals">

      </div>
      <div>
         <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6 mb-8">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100"><i class="fa fa-fw fa-images mr-3"></i>{{ __('Tarik foto') }}</h1>
            <div class="flex gap-x-2">
               <div class="px-2 my-auto">
                  <span>99</span><span>{{ ' ' . __('foto ditemukan') }}</span>
               </div>
               <div class="btn-group">
                  <x-secondary-button type="button" x-on:click="editorDownload"><i class="fa fa-fw fa-download"></i></x-secondary-button>
                  <x-secondary-button type="button" x-on:click="editorReset" class="rounded-none"><i class="fa fa-fw fa-undo"></i></x-secondary-button>
                  <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'guide')"><i class="far fa-fw fa-question-circle"></i></x-secondary-button>
               </div>
               <x-secondary-button type="button" wire:click="begin">
                  <div class="relative">
                     <span wire:loading.class="opacity-0" wire:target="apply"><i class="fa fa-check mr-2"></i>{{ __('Terapkan') }}</span>
                     <x-spinner wire:loading.class.remove="hidden" wire:target="apply" class="hidden sm mono"></x-spinner>                
                  </div>                
               </x-secondary-button>
            </div>
         </div>
         <div>

         </div>
         <div x-data="{ ...app(), progress: @entangle('progress') }" x-init="observeProgress()" class="bg-white dark:bg-neutral-800 shadow rounded-lg text-sm">
            <div class="relative w-full bg-neutral-200 rounded-full h-1.5 dark:bg-neutral-700">
               <div class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500 transition-all duration-200"
                  :style="'width:' + progress + '%'" style="width:0%;"></div>
            </div>
            <div><span wire:stream="progress">{{ $progress }}</span>%</div>
         </div>
      </div>

   @else
      <div class="text-center w-72 py-20 mx-auto">
         <i class="fa fa-hand text-5xl mb-8 text-neutral-400 dark:text-neutral-600"></i>
         <div class="text-neutral-500">{{ __('Kamu tidak memiliki wewenang untuk mengelola barang di area manapun.') }}</div>
      </div>

   @endif

   <script>
      function app() {
         return {
            observeProgress() {               
               const streamElement = document.querySelector('[wire\\:stream="progress"]');
               
               if (streamElement) {
                  const observer = new MutationObserver((mutations) => {
                        mutations.forEach(mutation => {
                           if (mutation.type === 'characterData' || mutation.type === 'childList') {
                              const currentValue = streamElement.textContent;
                              console.log('Stream value updated:', currentValue);
                              
                              // Do something with the captured value
                              this.handleProgress(currentValue);
                           }
                        });
                  });
                  
                  observer.observe(streamElement, { 
                     characterData: true, 
                     childList: true,
                     subtree: true 
                  });
               }

            },
            handleProgress(value) {
               this.progress = value;
            }
         }
      }
   </script>

</div>