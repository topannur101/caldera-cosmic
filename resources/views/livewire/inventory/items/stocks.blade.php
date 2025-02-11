<?php

use Livewire\Volt\Component;

new class extends Component
{

};

?>


<div class="mt-6 bg-white dark:bg-neutral-800 shadow rounded-none sm:rounded-lg px-6 divide-y divide-neutral-200 dark:divide-neutral-700">
   <div class="py-6">
      <div class="text-sm text-center text-neutral-500 border-b border-neutral-200 dark:border-neutral-700">
         <ul class="flex flex-wrap gap-x-4 uppercase">
               <li class="me-2">
                  <a href="#" class="inline-block pb-3 text-neutral-800 font-bold dark:text-neutral-200 border-b-2 border-caldy-500 rounded-t-lg active dark:border-caldy-500" aria-current="page">321 USD / PCK</a>
               </li>
               <li class="me-2">
                  <a href="#" class="inline-block pb-3 border-b-2 border-transparent hover:text-neutral-600 hover:border-neutral-300 dark:hover:text-neutral-300">0.5 USD / EA</a>
               </li>
         </ul>
      </div> 
      <div class="mt-6">
         <div class="flex flex-col md:flex-row gap-y-4 justify-between items-center">
               <div class="flex gap-x-2 items-baseline">
                  <div class="text-4xl">90</div>
                  <div class="text-sm font-bold">PCK</div>
               </div>
               <div class="relative sm:static flex gap-x-3">
                  <!-- Tambah -->
                  <x-popover-button focus="circ-deposit-qty" icon="fa-plus text-green-500">
                     <div class="grid grid-cols-1 gap-y-4">
                           <div>
                              <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-withdrawal-qty"><span>{{ __('Jumlah') . ': ' }}</span><span>123.24 USD</span></label>
                              <x-text-input-suffix suffix="PCK" id="circ-deposit-qty" class="text-center" name="circ-deposit-qty"
                              type="number" value="" min="1" placeholder="Qty"></x-text-input>
                           </div>
                           <div>
                              <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-deposit-remarks">{{ __('Keterangan') }}</label>
                              <x-text-input id="circ-deposit-remarks"></x-text-input-t>
                           </div>
                           <div >
                              <label for="circ-deposit-user"
                                 class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengguna') }}</label>
                              <x-text-input-icon id="circ-deposit-user" icon="fa fa-fw fa-user" type="text" autocomplete="off"
                                 placeholder="{{ __('Pengguna') }}" />
                           </div>
                           <div class="text-right">
                              <x-secondary-button type="button"><span class="text-green-500"><i class="fa fa-fw fa-plus mr-2"></i>{{ __('Tambah') . ' ' }}</span></x-secondary-button>
                           </div>
                     </div>
                  </x-popover-button>     
                  <!-- Catat -->                           
                  <x-popover-button focus="circ-capture-remarks" icon="fa-code-commit text-yellow-600">
                     <div class="grid grid-cols-1 gap-y-4">
                           <div>
                              <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-capture-remarks">{{ __('Keterangan') }}</label>
                              <x-text-input id="circ-capture-remarks"></x-text-input-t>
                           </div>
                           <div >
                              <label for="circ-capture-user"
                                 class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengguna') }}</label>
                              <x-text-input-icon id="circ-capture-user" icon="fa fa-fw fa-user" type="text" autocomplete="off"
                                 placeholder="{{ __('Pengguna') }}" />
                           </div>
                           <div class="text-right">
                              <x-secondary-button type="button"><span class="text-yellow-600"><i class="fa fa-fw fa-code-commit mr-2"></i>{{ __('Catat') . ' ' }}</span></x-secondary-button>
                           </div>
                     </div>
                  </x-popover-button>
                  <!-- Ambil -->
                  <x-popover-button focus="circ-withdrawal-qty" icon="fa-minus text-red-500">
                     <div class="grid grid-cols-1 gap-y-4">
                           <div>
                              <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-withdrawal-qty"><span>{{ __('Jumlah') . ': ' }}</span><span>123.24 USD</span></label>
                              <x-text-input-suffix suffix="PCK" id="circ-withdrawal-qty" class="text-center" name="circ-withdrawal-qty"
                              type="number" value="" min="1" placeholder="Qty"></x-text-input>
                           </div>
                           <div>
                              <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-withdrawal-remarks">{{ __('Keterangan') }}</label>
                              <x-text-input id="circ-withdrawal-remarks"></x-text-input-t>
                           </div>
                           <div >
                              <label for="circ-withdrawal-user"
                                 class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengguna') }}</label>
                              <x-text-input-icon id="circ-withdrawal-user" icon="fa fa-fw fa-user" type="text" autocomplete="off"
                                 placeholder="{{ __('Pengguna') }}" />
                           </div>
                           <div class="text-right">
                              <x-secondary-button type="button"><span class="text-red-500"><i class="fa fa-fw fa-minus mr-2"></i>{{ __('Ambil') . ' ' }}</span></x-secondary-button>
                           </div>
                     </div>
                  </x-popover-button>
               </div>
         </div>
      </div>
   </div>  
   <div class="py-6">
      <div class="flex justify-between items-center mb-4">
         <div class="uppercase text-neutral-500 text-sm">
               {{ __('Sirkulasi') }}
         </div>
         <div class="btn-group">
               <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'inv-item-circs-chart')"><i
                  class="fa fa-chart-line"></i></x-secondary-button>
               <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'inv-item-circs-download')"><i
                  class="fa fa-download"></i></x-secondary-button>
         </div>
      </div>
      <div class="truncate">
         <div wire:key="circ-button-xx" class="py-3 text-sm truncate hover:bg-caldy-500 hover:bg-opacity-10"
               x-on:click.prevent="$dispatch('open-modal', 'circ-edit-xx')">
               <div class="flex items-center">
                  <div>
                     <div class="w-24 truncate text-base">
                           <i class="fa fa-fw fa-minus mr-1 "></i>23 PCK
                     </div>
                  </div>
                  <div>
                     <div
                           class="w-8 h-8 mr-2 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                           <svg xmlns="http://www.w3.org/2000/svg"
                              class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                              viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                              <path
                                 d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                           </svg>
                     </div>
                  </div>
                  <div class="truncate">
                     <div class="truncate">
                           <div class="text-xs truncate text-neutral-400 dark:text-neutral-600">
                              Andi 
                              <span title="{{ __('Didelegasikan oleh:') . ' ' . 'Edwin' . ' (' . 'TT17110594' . ')' }}"><i class="fa fa-handshake-angle"></i></span>
                              <span
                                 class="mx-1">•</span>3 bulan yang lalu</div>
                           <div class="text-base truncate">
                              Untuk pemakaian di mesin
                           </div>
                     </div>
                  </div>
                  <div class="ml-auto pl-4 text-sm">
                     <i class="fa fa-fw fa-hourglass"></i>
                  </div>
               </div>
         </div>
      </div>
   </div>
</div>