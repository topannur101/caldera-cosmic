<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\InvArea;
use App\Models\InvItem;
use App\Models\User;
use App\Inv;


new #[Layout('layouts.app')]
class extends Component
{
   public array $areas = [];

   public int $area_id = 0;

   public int $progress = 0;

   public array $items = [];

   public string $gw_username = '';

   public int $step = 0;

   public array $indexes = [];

   public array $results_grouped = [
      'success' => [],
      'failure' => []
   ];

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
            $response = Gate::inspect('store', $item);

            if ($response->allowed()) {
               $area_ids[] = $area->id;
            }
         }
      }

      $this->areas = InvArea::whereIn('id', $area_ids)->get()->toArray();
   }

   public function checkItems()
   {
      foreach ($this->items as $key => $item) {
         if ($item['id'] || !$item['code']) {
            continue;
         }
         
         try {
            $inv_item = InvItem::where('inv_area_id', $this->area_id)->where('code', $item['code'])->first();

            if ($inv_item) {
               $this->items[$key]['code']    = strtoupper(trim($inv_item->code));
               $this->items[$key]['id']      = $inv_item->id;
               $this->items[$key]['name']    = $inv_item->name;
               $this->items[$key]['desc']    = $inv_item->desc;
               $this->items[$key]['photo']   = $inv_item->photo;
            } else {
               $this->items[$key]['name']    = __('Barang tidak ditemukan');
            }

         } catch (\Throwable $th) {
               $this->items[$key]['name']    = $th->getMessage();
         }
         
      }
   }

   public function checkUsername()
   {
      $this->gw_username = strtolower(trim($this->gw_username));
      $this->validate([
         'gw_username' => ['required', 'string', 'max:20'],
      ]);

      $this->step = 2;
      $this->js('window.dispatchEvent(escKey)'); 
      $this->js('$wire.pullPhotos()');
   }

   public function pullPhotos()
   {
      $ci_session = Inv::getCiSession($this->gw_username);
      
      if ($ci_session && $this->gw_username && count($this->items) > 0) {

         foreach ($this->items as $key => $item) {

            $inv_item = InvItem::where('inv_area_id', $this->area_id)
               ->where('id', $item['id'])
               ->first();

            if(!$inv_item) {
               $this->items[$key]['status'] = __('Barang tidak ditemukan');
               continue;
            }

            if (!$inv_item->code) {
               $this->items[$key]['status'] = __('Kode barang tidak sah');
               continue;
            }

            $result = Inv::photoSniff($inv_item->code, $ci_session);
            if ($result['success']) {
               $this->items[$key]['photo_new'] = $result['photo'];
               $this->items[$key]['status'] = __('Foto berhasil ditarik');
               $this->indexes[] = $item['index'];
            } else {
               $this->items[$key]['status'] = $result['message'];
            }   

            $progress = round(($key + 1) / count($this->items) * 100, 0);
            
            $this->stream(
               to: 'progress',
               content: $progress,
               replace: true
            );      
         }     
      }
      $this->step = 3;
   }

   public function updateItems()
   {
      $results = [];
      foreach ($this->items as $item) {

         if (!$item['index'] || !count($this->indexes)) {
            continue;
         }
         
         if (!in_array($item['index'], $this->indexes)) {
            continue;
         }

         try {
            $inv_item = InvItem::where('id', $item['id'])->where('inv_area_id', $this->area_id)->first();

            if (!$inv_item) {
               throw new Exception(__('Barang tidak ditemukan'), 1);
            }

            $store = Gate::inspect('store', $inv_item);
            if ($store->denied()) {
                throw new \Exception(__('Tak ada wewenang untuk mengelola barang ini'));
            };

            $inv_item->update([
               'photo' => $item['photo_new']
            ]);

            $results[] = [
               'success' => true,
               'message' => __('Foto barang berhasil diperbarui'),
            ];


         } catch (\Throwable $th) {
            $results[] = [
               'success' => false,
               'message' => $th->getMessage(),
            ];
         }
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

      $this->step = 4;
      $this->js('window.dispatchEvent(escKey)');
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
         <x-modal name="ask-gw-username" focusable>
            <form wire:submit="checkUsername" class="p-6">
               <div class="flex justify-between items-center text-lg mb-6 font-medium text-neutral-900 dark:text-neutral-100">
                  <h2>
                     {{ __('Username groupware') }}
                  </h2>
                  <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
               </div>
               <div class="py-3 text-5xl text-center">
                     <i class="icon-image relative text-neutral-300 dark:text-neutral-600">
                        <i class="icon-download absolute bottom-0 right-2 text-lg text-neutral-900 dark:text-neutral-100"></i>
                     </i>
               </div>
               <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                     {{ __('Caldera membutuhkan username groupware untuk menarik gambar dari sistem TTCons.') }}
               </p>
               <div class="mt-6">
                  <label for="gw_username" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Username groupware') }}</label>
                  <x-text-input wire:model="gw_username" id="gw_username" type="text" />
                  <div wire:key="error-gw_username">
                     @error('gw_username')
                        <x-input-error messages="{{ $message }}" class="mt-2" />
                     @enderror
                  </div>
               </div>
               <div class="mt-6 flex justify-end">
                  <x-primary-button type="submit" class="ml-3">
                     {{ __('Tarik foto') }}
                  </x-primary-button>
               </div>
            </form>
            <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
         </x-modal>
         <x-modal name="confirm-update-items" focusable>
            <form wire:submit="updateItems" class="p-6">
               <div class="flex justify-between items-center text-lg mb-6 font-medium text-neutral-900 dark:text-neutral-100">
                  <h2>
                     {{ __('Konfirmasi') }}
                  </h2>
                  <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
               </div>
               <div class="py-3 text-5xl text-center">
                  <i class="icon-box relative text-neutral-300 dark:text-neutral-600">
                     <i class="icon-image absolute bottom-0 right-2 text-lg text-neutral-900 dark:text-neutral-100"></i>
                  </i>
               </div>
               <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                  {{ __('Barang yang dipilih akan diperbarui fotonya dengan foto yang berhasil ditarik dari sistem TTCons.') }}
               </p>
               <div class="mt-6 flex justify-end">
                  <x-primary-button type="submit" class="ml-3">
                     {{ __('Lanjutkan') }}
                  </x-primary-button>
               </div>
            </form>
            <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
         </x-modal>
      </div>
      <div x-data="{ ...app(), code:'', areas:@entangle('areas'), area_id:@entangle('area_id'), items: @entangle('items'), step: @entangle('step'), progress: @entangle('progress'), indexes: @entangle('indexes') }" x-init="observeProgress()" class="px-4 sm:px-0">
         <div class="flex flex-col items-center gap-y-8 sm:flex-row">
            <h1 class="grow text-2xl text-neutral-900 dark:text-neutral-100 px-8">
               <i class="icon-images mr-2"></i>
               {{ __('Tarik foto') }}</h1>
            <x-link-secondary-button class="flex items-center h-full" href="{{ route('inventory.items.bulk-operation.pull-photos') }}">
               <i class="icon-rotate-cw mr-2"></i>{{ __('Ulangi dari awal') }}
            </x-link-secondary-button>
         </div>
         <div class="flex gap-x-6 w-full mt-6">
            <div>
               <ol class="sticky w-72 mt-6 top-6 text-neutral-500 border-s border-neutral-200 dark:border-neutral-700 dark:text-neutral-400">                  
                  <li class="mb-10 ms-6">            
                     <span class="absolute flex items-center justify-center w-8 h-8 bg-green-200 rounded-full -start-4 ring-4 ring-white dark:ring-neutral-900 dark:bg-green-900">
                        <i class="icon-house text-sm"></i>
                     </span>
                     <h3 class="font-medium leading-tight">{{ __('Pilih area')}}</h3>
                     <div class="mt-2">
                        <x-select x-model="area_id" ::class="step > 0 ? 'hidden' : ''" class="w-full" x-on:change="step = 1">
                           <option value=""></option>
                           <template x-for="area in areas">
                              <option :value="area.id" x-text="area.name"></option>
                           </template>
                        </x-select>
                        <div x-cloak :class="step > 0 ? '' : 'hidden'" x-text="area_id ? areas.find(area => area.id == area_id).name : ''">
                        </div>
                     </div>
                  </li>
                  <li class="mb-10 ms-6">
                     <span :class="step > 0 ? 'bg-green-200 dark:bg-green-900' : 'bg-neutral-100 dark:bg-neutral-700'" class="absolute flex items-center justify-center w-8 h-8 rounded-full -start-4 ring-4 ring-white dark:ring-neutral-900 ">
                        <i class="icon-box text-sm"></i>
                     </span>
                     <h3 class="font-medium leading-tight">{{ __('Masukkan kode')}}</h3>
                     <div x-cloak :class="step == 1 ? '' : 'hidden'" class="mt-2">
                        <form class="btn-group" x-on:submit.prevent="addItems()">
                           <x-text-input x-model="code" id="item-code" placeholder="{{ __('Kode barang') }}"></x-text-input->
                           <x-secondary-button type="submit">
                              <i class="icon-chevron-right"></i>
                           </x-secondary-button>
                        </form>
                        <x-primary-button type="button" class="mt-2" x-on:click="$dispatch('open-modal', 'ask-gw-username')">
                           {{ __('Lanjut')}}
                        </x-primary-button>
                     </div>
                     <div 
                        x-cloak 
                        :class="step > 1 ? '' : 'hidden'" 
                        class="mt-2" x-text="items.length + '{{ __(' barang') }}'">                      
                     </div>
                  </li>
                  <li class="mb-10 ms-6">
                     <span :class="step > 1 ? 'bg-green-200 dark:bg-green-900' : 'bg-neutral-100 dark:bg-neutral-700'" class="absolute flex items-center justify-center w-8 h-8 rounded-full -start-4 ring-4 ring-white dark:ring-neutral-900 ">
                        <i class="icon-image text-sm"></i>
                     </span>
                     <h3 class="font-medium leading-tight">{{ __('Tarik foto')}}</h3>
                     <div x-cloak :class="step == 2 ? '' : 'hidden'" class="mt-2">
                        <div class="flex justify-between text-sm">
                           <div>
                              {{ __('Menarik foto...') }}
                           </div>
                           <div><span wire:stream="progress">{{ $progress }}</span>%</div>
                        </div>
                        <div class="cal-shimmer mt-2 relative w-full bg-neutral-200 rounded-full h-1.5 dark:bg-neutral-700">
                           <div 
                              class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500 transition-all duration-200"
                              :style="'width:' + progress + '%'" 
                              style="width:0%;">
                           </div>
                        </div>
                     </div>
                     <div 
                        x-cloak 
                        :class="step > 2 ? '' : 'hidden'" 
                        class="mt-2" x-text="`${items.filter(item => item.photo_new).length}{{ __(' foto') }}`">                      
                     </div>
                  </li>
                  <li class="mb-10 ms-6">
                     <span :class="step > 2 ? 'bg-green-200 dark:bg-green-900' : 'bg-neutral-100 dark:bg-neutral-700'" class="absolute flex items-center justify-center w-8 h-8 rounded-full -start-4 ring-4 ring-white dark:ring-neutral-900 ">
                        <i class="icon-list-check text-sm"></i>
                     </span>
                     <h3 class="font-medium leading-tight">{{ __('Tinjau foto')}}</h3>
                     <div x-cloak :class="step == 3 ? '' : 'hidden'" class="mt-2">
                        <x-primary-button type="button" x-on:click="$dispatch('open-modal', 'confirm-update-items')">
                           {{ __('Perbarui yang dipilih')}}
                        </x-primary-button>
                     </div>
                     <div
                        x-cloak 
                        :class="step > 3 ? '' : 'hidden'" 
                        class="mt-2" x-text="indexes.length + '{{ __(' dipilih') }}'">                      
                     </div>
                  </li>
                  <li class="ms-6">
                     <span :class="step > 3 ? 'bg-green-200 dark:bg-green-900' : 'bg-neutral-100 dark:bg-neutral-700'" class="absolute flex items-center justify-center w-8 h-8 rounded-full -start-4 ring-4 ring-white dark:ring-neutral-900 ">
                        <i class="icon-circle-check text-sm"></i>
                     </span>
                     <h3 class="font-medium leading-tight">{{ __('Selesai')}}</h3>
                  </li>
               </ol>
            </div>
            <div wire:key="list" class="grow flex items-center justify-center bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto">
               <div :class="items.length > 0 ? 'hidden' : ''">
                  <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                     <i class="icon-box relative"><i class="icon-circle-help absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                  </div>
                  <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Tidak ada barang') }}</div>
               </div>
               <table x-cloak :class="items.length > 0 && step < 4 ? '' : 'hidden'" class="text-neutral-600 dark:text-neutral-400 w-full table text-sm [&_th]:text-center [&_th]:px-2 [&_th]:py-3 [&_td]:px-2 [&_td]:py-1">
                  <tr class="uppercase text-xs">
                     <th class="w-[1%]"></th>
                     <th class="max-w-32 truncate">{{ __('Kode') }}</th>
                     <th class="w-[1%]">{{ __('Caldera') }}</th>
                     <th class="w-[1%]">{{ __('TTCons') }}</th>
                     <th class="w-[1%]">{{ __('ID') }}</th>
                     <th class="max-w-40 text-wrap">{{ __('Nama') }}</th>
                     <th class="w-[240px] text-wrap">{{ __('Status') }}</th>
                  </tr>
                  <template x-for="item in items"> class="max-w-32"
                     <tr class="text-nowrap">
                        <td class="pl-3 text-center">
                           <label :for="item.index">
                              <input 
                                 type="checkbox"
                                 class="w-5 h-5 text-caldy-600 bg-neutral-100 border-neutral-300 rounded focus:ring-2 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-neutral-800 dark:bg-neutral-700 dark:border-neutral-600"
                                 :id="item.index"
                                 :value="item.index"
                                 :disabled="item.photo_new ? false : true"
                                 x-model="indexes">
                           </label>
                        </td>
                        <td x-text="item.code" class="max-w-32 truncate"></td>
                        <td class="w-[1%]">
                           <div class="rounded-sm overflow-hidden relative flex w-32 h-24 bg-neutral-200 dark:bg-neutral-700">
                              <div class="m-auto">
                                 <svg xmlns="http://www.w3.org/2000/svg"  class="block w-8 h-8 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793"><path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" /></svg>
                              </div>
                              <template x-if="item.photo">
                                 <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" :src="'/storage/inv-items/' + item.photo" />
                              </template>
                           </div>   
                        </td>
                        <td class="w-[1%]">
                           <div class="rounded-sm overflow-hidden relative flex w-32 h-24 bg-neutral-200 dark:bg-neutral-700">
                              <div class="m-auto">
                                 <i class="far icon-circle-help text-neutral-800 dark:text-neutral-200 opacity-25 text-3xl"></i>
                              </div>
                              <template x-if="item.photo_new">
                                 <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" :src="'/storage/inv-items/' + item.photo_new" />
                              </template>
                           </div>   
                        </td>                        
                        <td x-text="item.id" class="w-[1%]"></td>
                        <td class="max-w-40 text-wrap">
                           <div x-text="item.name" class="text-wrap"></div>
                           <div x-text="item.desc" class="text-wrap text-xs text-neutral-500"></div>
                        </td>
                        <td x-text="item.status" class="w-[240px] text-wrap"></td>
                     </tr>
                  </template>
               </table>
               <div  x-cloak :class="step == 4 ? '' : 'hidden'" class="flex flex-col gap-y-3">
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
               </div>
            </div>
         </div>

      </div>

   @else
      <div class="text-center w-72 py-20 mx-auto">
         <i class="icon-octagon-minus text-5xl mb-8 text-neutral-400 dark:text-neutral-600"></i>
         <div class="text-neutral-500">{{ __('Kamu tidak memiliki wewenang untuk mengelola barang di area manapun.') }}</div>
      </div>

   @endif
   <script>
      function app() {
         return {
            addItems() {
               const input = this.code.trim();
               const codes = input.split(' ');

               codes.forEach(code => {
                  this.addItem(code);
               });
         
               this.code = '';
               this.$wire.checkItems();
            },

            addItem(code) {
               const codeClean = code.trim().toUpperCase();
               const existingItem = this.items.find(item => item.code == codeClean);

               if (existingItem) {
                  return;
               }

               if (codeClean) {
                  this.items.unshift({
                     code: codeClean,
                     id: null,
                     name: '',
                     desc: '',
                     photo: null,
                     photo_new: null,
                     status: null,
                     index: this.items.length + 1
                  });
               }
            },

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
         };
      }
   </script>
</div>