<?php

use Livewire\Volt\Component;
use App\Models\InvItem;
use App\Models\InvArea;

new class extends Component
{
   public int $id = 0;

   public bool $is_editing = false;

   public array $item = [
      'id'              => '',
      'photo'           => '',
      'area_id'         => 0,
      'area_name'       => '',
      'is_active'       => true,
      'name'            => '',
      'desc'            => '',
      'code'            => '',
      'loc_id'          => 0,
      'tags_list'       => '',
      'updated_at'      => '',
      'last_withdrawal' => '',
   ];

   public string $loc_parent  = '';
   public string $loc_bin     = '';
   public array $areas        = [];
   public array $tags         = [];
   public array $loc_parents  = [];
   public array $loc_bins     = [];
   public array $stocks       = [];
   public array $currencies   = [
      [
         'name' => 'USD',
         'rate' => 1
      ],[
         'name' => 'IDR',
         'rate' => 16300
      ],

   ];

   public function mount()
   {
      

      $item = InvItem::find($this->id);
      if($item) {
         $this->item['id'] = $item->id;
         
      } else {
         $this->areas = InvArea::all()->toArray();
         $this->is_editing = true;

      }
   }

};

?>

<div>
   @if($is_editing)
      <div class="px-4 sm:px-0">
         <div class="flex items-center justify-between gap-x-4 p-4 mb-8 text-sm text-neutral-800 border border-neutral-300 rounded-lg bg-neutral-50 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-600" role="alert">
               <div class="flex items-center">
                  <i class="fa fa-pen me-3"></i>
                  <span class="sr-only">Info</span>
                  <div>
                     <span class="font-medium">{{ __('Mode edit') . ': ' }}</span> {{ __('Klik bidang yang hendak di edit, klik simpan bila sudah selesai.') }}
                  </div>
               </div>
               <div>
                  <x-primary-button type="button"><i class="fa fa-save me-2"></i>{{ __('Simpan') }}</x-primary-button>
               </div>
         </div>
      </div>
   @endif
    <div class="block sm:flex gap-x-6">
        <div wire:key="photo">
            <div class="sticky top-5 left-0">
                <livewire:inventory.items.photo :$is_editing />
                <div class="grid grid-cols-1 divide-y divide-neutral-200 dark:divide-neutral-800 px-4 my-6 text-sm">
                  @if($is_editing)
                     <div class="flex items-center gap-x-3 py-3">
                        <i class="text-neutral-500 fa fa-fw fa-tent me-2"></i>
                        <x-select wire:model="item.area_id" class="w-full">
                           <option value="">{{ __('Pilih area') }}</option>
                           @foreach($areas as $area)
                              <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
                           @endforeach
                        </x-select>
                     </div>
                     <div x-data="{ is_active: @entangle('item.is_active') }" class="flex items-center gap-x-3 py-3">
                        <i x-show="is_active" class="text-neutral-500 fa fa-fw fa-check-circle me-2"></i>
                        <i x-show="!is_active" class="text-neutral-500 fa fa-fw fa-ban me-2"></i>
                        <x-toggle id="item_is_active" x-model="is_active"><span x-show="is_active">{{ __('Aktif') }}</span><span x-show="!is_active">{{ __('Nonaktif') }}</span></x-toggle>
                     </div>
                  @else
                     <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-tent me-2"></i>{{ $item['area_name']}}</div>
                     <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-check-circle me-2"></i>{{ $item['is_active'] ? __('Aktif') : __('Nonaktif')}}</div>
                     <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-pen me-2"></i>{{ __('Edit barang') }}</div>
                     <div class="py-3">{{ __('Terakhir diperbarui') . ': ' . $item['updated_at'] }}</div>
                     <div class="py-3">{{ __('Pengambilan terakhir') . ': ' . $item['last_withdrawal'] }}</div>
                  @endif
                </div>
            </div>
        </div>
        <div class="grow">
            <div class="bg-white dark:bg-neutral-800 shadow rounded-none sm:rounded-lg px-6 divide-y divide-neutral-200 dark:divide-neutral-700">
                <div class="grid gap-y-6 py-6">
                  @if($is_editing)
                     <div>
                        <label for="item-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                        <x-text-input id="item-name" wire:model="item.desc" type="text" />
                     </div>
                     <div>
                        <label for="item-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Deskripsi') }}</label>
                        <x-text-input id="item-desc" wire:model="item.desc" type="text" />                        
                     </div>
                  @else
                     <h1 class="text-2xl font-medium text-neutral-900 dark:text-neutral-100">{{ $item['name'] }}</h1>
                     <p>{{ $item['desc'] }}</p>
                  @endif
                </div>
                @if($is_editing)                
                  <div class="py-6 grid grid-cols-1 gap-y-3">
                     <div>
                        <label for="item-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                        <x-text-input id="item-code" wire:model="item.code" type="text" />
                     </div>
                     <div class="px-3 mt-3">
                        <x-inv-loc-selector />
                     </div>
                     <div class="px-3 mt-3">
                        <x-inv-tag-selector />
                     </div>
                  </div>
                @else
                  <div class="py-6 flex flex-col lg:flex-row gap-x-6 gap-y-3 text-neutral-500 text-sm">                    
                     <div>{{ $item['code'] ?: __('TAK ADA KODE') }}</div>
                     <div><i class="fa fa-fw fa-map-marker-alt me-2"></i>{{ $item['loc_name'] ?: __('Tak ada lokasi') }}</div>
                     <div><i class="fa fa-fw fa-tag me-2"></i>{{ $item['tags_list'] ?: __('Tak ada tag') }}</div>
                  </div>
                @endif

            </div>
            @if($is_editing)
            <div x-data="{
               stocks: @entangle('stocks'),
               currencies: @entangle('currencies'),
               
               // Currency-related state
               main_currency: '',
               main_unit_price: 0,
               secondary_currency: '',
               secondary_unit_price: 0,
               is_secondary_currency: false,
               
               // Other state
               uom_input: '',
               editingIndex: null,

               // Initialize the component
               init() {
                  // Set main currency from first element
                  if (this.currencies.length > 0) {
                     this.main_currency = this.currencies[0].name;
                  }

                  // Watch for changes in secondary_unit_price
                  this.$watch('secondary_unit_price', (value) => {
                     if (value && this.is_secondary_currency) {
                        this.calculateMainPrice();
                     }
                  });
               },

               // Calculate main price based on secondary price and exchange rates
               calculateMainPrice() {
                  const mainRate = this.currencies.find(c => c.name === this.main_currency)?.rate || 1;
                  const secondaryRate = this.currencies.find(c => c.name === this.secondary_currency)?.rate || 1;
                  this.main_unit_price = ((this.secondary_unit_price * mainRate) / secondaryRate).toFixed(2);
               },

               // Get available secondary currencies (excluding main currency)
               getSecondaryCurrencies() {
                  return this.currencies.filter(c => c.name !== this.main_currency);
               },
               
               addStock() {
                  // Trim inputs
                  const uom = this.uom_input.trim().toUpperCase().substring(0, 5);
                  const currency = this.is_secondary_currency ? this.secondary_currency : this.main_currency;
                  const unit_price = this.is_secondary_currency ? this.secondary_unit_price : this.main_unit_price;

                  if (!uom) {
                     toast('{{ __('Uom wajib diisi') }}', { type: 'danger' });
                     return;
                  }
                  if (this.stocks.some((stock, index) => stock.currency === currency && stock.uom === uom && index !== this.editingIndex)) {
                     toast('{{ __('Mata uang dan uom tersebut sudah ada') }}', { type: 'danger' });
                     return;
                  }

                  const stockData = {
                     currency: currency,
                     unit_price: unit_price,
                     uom: uom
                  };

                  if (this.editingIndex !== null) {
                     this.stocks[this.editingIndex] = stockData;
                  } else {
                     this.stocks.push(stockData);
                  }

                  this.resetForm();
                  this.$dispatch('close');
               },

               editStock(index) {
                  const stock = this.stocks[index];
                  this.uom_input = stock.uom;
                  
                  if (stock.currency === this.main_currency) {
                     this.main_unit_price = stock.unit_price;
                     this.is_secondary_currency = false;
                  } else {
                     this.secondary_currency = stock.currency;
                     this.secondary_unit_price = stock.unit_price;
                     this.is_secondary_currency = true;
                     this.calculateMainPrice();
                  }
                  
                  this.editingIndex = index;
                  this.$dispatch('open-modal', 'stock-creator');
               },

               resetForm() {
                  this.main_unit_price = 0;
                  this.secondary_unit_price = 0;
                  this.uom_input = '';
                  this.is_secondary_currency = false;
                  this.editingIndex = null;
               },

               removeStock(index) {
                  this.stocks.splice(index, 1);
               },

               formatPrice(price) {
                  return price.toLocaleString();
               }
            }" class="bg-white dark:bg-neutral-800 shadow rounded-none sm:rounded-lg p-6 mt-6">
               <!-- Modal Form -->
               <x-modal name="stock-creator">
                  <div class="p-6">
                     <div class="flex justify-between items-start">
                        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100" 
                           x-text="editingIndex !== null ? '{{ __('Edit unit') }}' : '{{ __('Unit baru') }}'"></h2>
                        <x-text-button type="button" x-on:click="$dispatch('close')">
                           <i class="fa fa-times"></i>
                        </x-text-button>
                     </div>                    
                     <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3 gap-y-6 mt-6">
                        <div>
                           <label for="stock-uom" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('UOM') }}</label>
                           <x-text-input id="stock-uom" type="text" x-model="uom_input" maxlength="5"></x-text-input>
                        </div>    
                        <div class="col-span-1 sm:col-span-2">
                           <label for="stock-unit-price" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Harga satuan') }}</label>
                           <x-text-input-curr curr="main_currency" ::disabled="is_secondary_currency" 
                                          id="stock-unit-price" type="number" x-model="main_unit_price" min="0"></x-text-input>
                        </div>

                        <div x-show="is_secondary_currency">
                           <label for="stock-secondary-currency" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mata uang') }}</label>
                           <x-select id="stock-secondary-currency" class="w-full" x-model="secondary_currency">
                              <option value=""></option>   
                              <template x-for="currency in getSecondaryCurrencies()" :key="currency.name">                                 
                                 <option :value="currency.name" x-text="currency.name"></option>
                              </template>
                           </x-select>
                        </div>
                        <div x-show="is_secondary_currency" class="col-span-1 sm:col-span-2">
                           <label for="stock-secondary-unit-price" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Harga satuan') }}</label>
                           <x-text-input-curr curr="secondary_currency" id="stock-secondary-unit-price" 
                                          type="number" x-model="secondary_unit_price" min="0"></x-text-input>
                        </div>                       
                        
                        <div class="px-3 col-span-1 sm:col-span-2">
                           <x-toggle x-model="is_secondary_currency">{{ __('Gunakan mata uang sekunder') }}</x-toggle>
                        </div>
                     </div>
                     <div class="flex justify-end mt-6">
                        <x-primary-button type="button" x-on:click="addStock">
                           <span x-text="editingIndex !== null ? '{{ __('Terapkan') }}' : '{{ __('Buat') }}'"></span>
                        </x-primary-button>
                     </div>
                  </div>
               </x-modal>

               <!-- Stock List -->
               <div class="flex flex-wrap gap-2 text-sm">
                  <template x-for="(stock, index) in stocks" :key="index">
                     <div class="hover:opacity-80 bg-neutral-200 dark:bg-neutral-900 rounded-full border border-neutral-300 dark:border-neutral-700 px-3 py-1 cursor-pointer" 
                        x-on:click="editStock(index)">
                        <span x-text="`${stock.currency} ${formatPrice(stock.unit_price)} / ${stock.uom}`"></span>
                        <x-text-button type="button" x-on:click.stop="removeStock(index)" class="ml-2">
                           <i class="fa fa-times"></i>
                        </x-text-button>
                     </div>
                  </template>
                  <x-text-button type="button" x-on:click="$dispatch('open-modal', 'stock-creator')" 
                                 class="rounded-full border border-neutral-300 dark:border-neutral-700 px-3 py-1">
                     <i class="fa fa-plus me-2"></i>{{ __('Tambah unit') }}
                  </x-text-button>
               </div>
            </div>

            @else
               <livewire:inventory.items.stocks />

            @endif
        </div>
    </div>
</div>
