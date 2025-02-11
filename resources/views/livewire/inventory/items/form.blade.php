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
      'loc_parent'      => '',
      'loc_bin'        => '',
      'tags'            => [],  
      'updated_at'      => '',
      'last_withdrawal' => '',
   ];

   public array $areas        = [];
   public array $tags         = [];
   public array $loc_parents  = [];
   public array $loc_bins     = [];
   public array $stocks       = [];

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
                <div class="grid gap-y-3 py-6">
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
                     <x-inv-loc-selector :$loc_parents :$loc_bins />
                     <x-inv-tag-selector :$tags />
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
               currency_input: '',
               unit_price_input: 0,
               uom_input: '',
               editingIndex: null, // Track which stock is being edited
               addStock() {
                  // Trim inputs
                  const currency = this.currency_input.trim();
                  const uom = this.uom_input.trim().toUpperCase().substring(0, 5); // Uppercase and limit to 5 chars

                  // Validation
                  if (!currency || !['USD', 'IDR', 'KRW'].includes(currency)) {
                     toast('{{ __('Mata uang harus USD, IDR, atau KRW') }}', { type: 'danger' });
                     return;
                  }
                  if (this.unit_price_input < 0) {
                     toast('{{ __('Harga satuan harus angka positif') }}', { type: 'danger' });
                     return;
                  }
                  if (!uom) {
                     toast('{{ __('UoM wajib diisi') }}', { type: 'danger' });
                     return;
                  }
                  if (this.stocks.some((stock, index) => stock.currency === currency && stock.uom === uom && index !== this.editingIndex)) {
                     toast('{{ __('Kombinasi mata uang dan UoM tersebut sudah ada') }}', { type: 'danger' });
                     return;
                  }

                  // Add or update stock
                  if (this.editingIndex !== null) {
                     // Update existing stock
                     this.stocks[this.editingIndex] = {
                     currency: currency,
                     unit_price: this.unit_price_input,
                     uom: uom
                     };
                  } else {
                     // Add new stock
                     this.stocks.push({
                     currency: currency,
                     unit_price: this.unit_price_input,
                     uom: uom
                     });
                  }

                  // Reset form and close modal
                  this.resetForm();
                  this.$dispatch('close');
               },
               editStock(index) {
                  // Load stock data into the form
                  this.currency_input = this.stocks[index].currency;
                  this.unit_price_input = this.stocks[index].unit_price;
                  this.uom_input = this.stocks[index].uom;
                  this.editingIndex = index;
                  // Open the modal
                  this.$dispatch('open-modal', 'stock-creator');
               },
               resetForm() {
                  // Clear form and reset editing state
                  this.currency_input = '';
                  this.unit_price_input = 0;
                  this.uom_input = '';
                  this.editingIndex = null;
               },
               removeStock(index) {
                  this.stocks.splice(index, 1);
               },
               formatPrice(price) {
                  return price.toLocaleString(); // Add thousands separators
               }
               }" class="bg-white dark:bg-neutral-800 shadow rounded-none sm:rounded-lg p-6 mt-6">
               <!-- Modal Form -->
               <x-modal name="stock-creator">
                  <div class="p-6">
                     <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100" x-text="editingIndex !== null ? '{{ __('Edit unit') }}' : '{{ __('Unit baru') }}'"></h2>
                     <div class="grid gap-y-6 mt-6">
                        <div>
                           <label for="stock-currency" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Currency') }}</label>
                           <x-select id="stock-currency" x-model="currency_input">
                              <option value=""></option>
                              <option value="USD">USD</option>
                              <option value="IDR">IDR</option>
                              <option value="KRW">KRW</option>
                           </x-select>
                        </div>
                        <div>
                           <label for="stock-unit-price" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Unit Price') }}</label>
                           <x-text-input id="stock-unit-price" type="number" x-model="unit_price_input" placeholder="Unit Price" min="0"></x-text-input>
                        </div>
                        <div>
                           <label for="stock-uom" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('UOM') }}</label>
                           <x-text-input id="stock-uom" type="text" x-model="uom_input" maxlength="5"></x-text-input>
                        </div>
                        <div class="flex justify-end">
                           <x-primary-button type="button" x-on:click="addStock">
                              <span x-text="editingIndex !== null ? '{{ __('Terapkan') }}' : '{{ __('Buat') }}'"></span>
                           </x-primary-button>
                        </div>
                     </div>
                  </div>
               </x-modal>

               <!-- Stock List -->
               <div class="flex flex-wrap gap-2 text-sm">
                  <template x-for="(stock, index) in stocks" :key="index">
                     <div class="hover:opacity-80 bg-neutral-200 dark:bg-neutral-900 rounded-full border border-neutral-300 dark:border-neutral-700 px-3 py-1 cursor-pointer" x-on:click="editStock(index)">
                        <span x-text="`${stock.currency} ${formatPrice(stock.unit_price)} / ${stock.uom}`"></span>
                        <x-text-button type="button" x-on:click.stop="removeStock(index)" class="ml-2">
                           <i class="fa fa-times"></i>
                        </x-text-button>
                     </div>
                  </template>
                  <x-text-button type="button" x-on:click="$dispatch('open-modal', 'stock-creator')" class="rounded-full border border-neutral-300 dark:border-neutral-700 px-3 py-1"><i class="fa fa-plus me-2"></i>{{ __('Tambah unit') }}</x-text-button>

               </div>
               </div>

            @else
               <livewire:inventory.items.stocks />

            @endif
        </div>
    </div>
</div>
