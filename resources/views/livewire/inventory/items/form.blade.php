<?php

use Livewire\Volt\Component;
use App\Models\InvArea;
use App\Models\InvItem;
use App\Models\InvCurr;
use App\Models\InvLoc;
use App\Models\InvTag;
use App\Models\InvStock;
use App\Models\InvItemTag;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

use Carbon\Carbon;

new class extends Component
{
   public int $id_new = 0;

   public bool $is_editing = false;

   public array $items = [
      0 => [
         'id'              => 0,
         'name'            => '',
         'desc'            => '',
         'code'            => '',
         'loc_id'          => 0,
         'loc_name'        => '',
         'tags_list'       => '',
         'photo'           => '',
         'area_id'         => 0,
         'area_name'       => '',
         'is_active'       => false,
         'updated_at'      => '',
         'last_deposit'    => '',
         'last_withdrawal' => '',
      ]
   ];

   public string $loc_parent  = '';
   public string $loc_bin     = '';
   public array $areas        = [];
   public array $tags         = [];
   public array $loc_parents  = [];
   public array $loc_bins     = [];
   public array $stocks       = [];
   public array $currencies   = [];
   public bool $can_store = false;

   public function mount()
   {
      if($this->is_editing) {
         $currencies = InvCurr::all();
         $this->currencies = $currencies ? $currencies->toArray() : [];
         $this->areas = Auth::user()->id === 1 ? InvArea::all()->toArray() : Auth::user()->inv_areas->toArray();
      }
   }

   public function save()
   {

      // clean up before validate
      $this->items[0]['name']    = trim($this->items[0]['name']);
      $this->items[0]['desc']    = trim($this->items[0]['desc']);
      $this->items[0]['code']    = strtoupper(trim($this->items[0]['code']));
      $this->loc_parent          = strtoupper(trim($this->loc_parent));
      $this->loc_bin             = strtoupper(trim($this->loc_bin));
      
      foreach($this->tags as $tag) {
         $tag = strtolower(trim($tag));
      }
      
      foreach($this->stocks as $stock) {
         $stock['currency'] = strtoupper(trim($stock['currency']));
         $stock['uom']      = strtoupper(trim($stock['uom']));
      }

      if (!$this->is_editing)
      {
         $item = InvItem::where('code', $this->items[0]['code'])->where('inv_area_id', $this->items[0]['area_id'])->count();
         if ($item) {
            $this->js('toast("' . __('Barang dengan item kode dan area tersebut sudah ada') . '", { type: "danger" } )');
         }
      }

      $this->validate([
         'items.*.name'    => ['required', 'max:128'],
         'items.*.desc'    => ['required', 'max:256'],
         'items.*.code'    => ['nullable', 'alpha_dash', 'size:11'],

         'loc_parent'   => ['required_with:loc_bin', 'alpha_dash','max:3'],
         'loc_bin'      => ['required_with:loc_parent', 'alpha_dash','max:7'],
         'tags'         => ['array', 'max:5'],
         'tags.*'       => ['required', 'alpha_dash', 'max:20'],

         'stocks'                => ['array','min:1', 'max:3'],
         'stocks.*.currency'     => ['required', 'exists:inv_currs,name'],
         'stocks.*.unit_price'   => ['required', 'numeric', 'min:0', 'max:1000000000'],
         'stocks.*.uom'          => ['required', 'alpha_dash', 'max:5'],

         'items.*.photo'      => ['nullable'],
         'items.*.area_id'    => ['required', 'exists:inv_areas,id'],
         'items.*.is_active'  => ['required', 'boolean'],
      ]);

      // prepare item model
      $item = null;
      if($this->items[0]['id']) {
         $item = InvItem::find($this->items[0]['id']);
      }

      if (!$item) {
         $item = new InvItem();
      }

      // prepare location id or null
      $loc_id = null;
      if ($this->loc_parent && $this->loc_bin) {
         $loc_id = InvLoc::firstOrCreate([
            'parent' => $this->loc_parent,
            'bin'    => $this->loc_bin,
         ])->id;
      }

      $item->name          = $this->items[0]['name'];
      $item->desc          = $this->items[0]['desc'];
      $item->code          = $this->items[0]['code'] ?: null;
      $item->photo         = $this->items[0]['photo'];
      $item->inv_area_id   = $this->items[0]['area_id'];
      $item->is_active     = $this->items[0]['is_active'];
      $item->inv_loc_id    = $loc_id;

      $response = Gate::inspect('store', $item);
 
      if ($response->denied()) {
         $this->js('toast("' . $response->message() . '", { type: "danger" })');
         return;
      }

      if (!$item->id && $item->code) {
         $duplicate = InvItem::where('code', $item->code)->where('inv_area_id', $item->inv_area_id)->count();
         if ($duplicate) {
            $this->js('toast("' . __('Barang dengan kode tersebut di area ini sudah ada.') . '", { type: "danger" })');
            return;
         }
      }

      $item->save();

      // detach tags
      $item_tag_ids = InvItemTag::where('inv_item_id', $item->id);
      $item_tag_ids->delete();

      // create tags
      $tag_ids = [];
      foreach ($this->tags as $tag) {
         $tag_ids[] = InvTag::firstOrCreate(['name' => $tag])->id;
      }

      // attach tags
      foreach($tag_ids as $tag_id) {
         InvItemTag::firstOrCreate([
            'inv_item_id' => $item->id,
            'inv_tag_id'  => $tag_id,
         ]);
      }

      // detach stocks
      $stocks = InvStock::where('inv_item_id', $item->id)->get();
      foreach ($stocks as $stock) {
         $stock->update([
            'is_active' => false
         ]);
      }
      
      // create and attach stocks
      foreach ($this->stocks as $stock) {
         $curr_id = InvCurr::where('name', $stock['currency'])->first()->id;
         $stocks = InvStock::updateOrCreate([
            'inv_item_id'  => $item->id,
            'inv_curr_id'  => $curr_id,
            'uom'          => $stock['uom'],
         ],[
            'unit_price'   => $stock['unit_price'],
            'is_active'    => true
         ]);
      }
      
      if ($this->is_editing)
      {  
         $this->redirect(route('inventory.items.show', ['id' => $item->id, 'is_updated' => true]), navigate: true);
      
      } else {      
         $this->id_new = $item->id;
         $this->reset(['items', 'loc_parent', 'loc_bin', 'tags', 'loc_parents', 'loc_bins', 'stocks']);
         $this->dispatch('remove-photo');   
         $this->js('$dispatch("open-modal", "item-created")');

      }

   }

   #[Renderless] 
   #[On('photo-updated')] 
   public function insertPhoto($photo)
   {
      $this->items[0]['photo'] = $photo;
   }

};

?>

<div>
   @if($is_editing)
      <div class="px-4 sm:px-0 mb-8 grid grid-cols-1 gap-y-4">
         <div class="flex items-center justify-between gap-x-4 p-4 text-sm text-neutral-800 border border-neutral-300 rounded-lg bg-neutral-50 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-600" role="alert">
            <div>
               {{ __('Klik simpan jika sudah selesai melengkapi informasi barang') }}
            </div>
            <div>
               <div wire:loading>
                  <x-primary-button type="button" disabled><i class="fa fa-save me-2"></i>{{ __('Simpan') }}</x-primary-button>
               </div>
               <div wire:loading.remove>
                  <x-primary-button type="button" wire:click="save"><i class="fa fa-save me-2"></i>{{ __('Simpan') }}</x-primary-button>
               </div>
            </div>
         </div>
         @if ($errors->any())
            <div class="text-center">
                <x-input-error :messages="$errors->first()" />
            </div>
        @endif
      </div>
      <div wire:key="modals">
         <x-modal name="item-created" maxWidth="sm">
            <div class="p-6">
               <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100"><i class="fa fa-check-circle text-green-500 mr-3"></i>{{ __('Barang dibuat') }}</h2>
               <div class="my-6 text-sm">
                  <p>{{ __('Apa yang akan kamu lakukan selanjutnya?') }}</p>
               </div>
               <div class="grid grid-cols-1 gap-y-3">
                  <x-link-secondary-button x-on:click="$dispatch('close')" href="{{ route('inventory.items.index') }}" wire:navigate>{{ __('Kembali ke pencarian') }}</x-link-secondary-button>
                  <x-link-secondary-button x-on:click="$dispatch('close')" href="{{ route('inventory.items.show', ['id' => $id_new]) }}" wire:navigate>{{ __('Lihat barang yang dibuat') }}</x-link-secondary-button>
                  <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Buat lagi') }}</x-primary-button>
               </div>
            </div>
         </x-modal>
      </div>
   @endif
    <div class="block sm:flex gap-x-6">
        <div wire:key="photo">
            <div class="sticky top-5 left-0">
                <livewire:inventory.items.photo :id="$items[0]['id']" :$is_editing :photo_url="$items[0]['photo'] ? ('/storage/inv-items/' . $items[0]['photo']) : ''" />
                <div class="grid grid-cols-1 divide-y divide-neutral-200 dark:divide-neutral-800 px-4 my-6 text-sm">
                  @if($is_editing)
                     <div class="flex items-center gap-x-3 py-3">
                        <i class="text-neutral-500 fa fa-fw fa-tent me-2"></i>
                        <x-select wire:model="items.0.area_id" class="w-full">
                           <option value=""></option>
                           @foreach($areas as $area)
                              <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
                           @endforeach
                        </x-select>
                     </div>
                     <div x-data="{ is_active: @entangle('items.0.is_active') }" class="flex items-center gap-x-3 py-3">
                        <i x-show="is_active" class="text-neutral-500 fa fa-fw fa-check-circle me-2"></i>
                        <i x-show="!is_active" class="text-neutral-500 fa fa-fw fa-ban me-2"></i>
                        <x-toggle id="item_is_active" x-model="is_active" ::checked="is_active"><span x-show="is_active">{{ __('Aktif') }}</span><span x-show="!is_active">{{ __('Nonaktif') }}</span></x-toggle>
                     </div>
                  @else
                     <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-tent me-2"></i>{{ $items[0]['area_name']}}</div>
                     <div class="py-3 {{ $items[0]['is_active'] ? '' :'text-red-500' }}"><i class="fa fa-fw {{ $items[0]['is_active'] ? 'fa-check-circle text-neutral-500' :'fa-ban' }} me-2"></i>{{ $items[0]['is_active'] ? __('Aktif') : __('Nonaktif')}}</div>
                     @if($can_store)
                        <div class="py-3"><x-link href="{{ route('inventory.items.edit', ['id' => $items[0]['id']] ) }}" wire:navigate><i class="text-neutral-500 fa fa-fw fa-pen me-2"></i>{{ __('Edit barang') }}</x-text-link></div>
                     @endif
                     <div class="py-3"><span class="text-neutral-500">{{ __('Terakhir diperbarui') . ': ' }}</span>{{  $items[0]['updated_at'] }}</div>
                     <div class="py-3"><span class="text-neutral-500">{{ __('Terakhir ditambah') . ': ' }}</span>{{ ($items[0]['last_deposit'] ?: __('Tak pernah')) }}</div>
                     <div class="py-3"><span class="text-neutral-500">{{ __('Terakhir diambil') . ': ' }}</span>{{ ($items[0]['last_withdrawal'] ?: __('Tak pernah')) }}</div>
                  @endif
                </div>
            </div>
        </div>
        <div class="grow">
            <div class="bg-white dark:bg-neutral-800 shadow rounded-none sm:rounded-lg divide-y divide-neutral-200 dark:divide-neutral-700">
               @if($is_editing)
                  <div class="grid gap-y-4 py-6">
                     <div class="px-6">
                        <label for="item-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                        <x-text-input id="item-name" wire:model="items.0.name" type="text" />
                     </div>
                     <div class="px-6">
                        <label for="item-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Deskripsi') }}</label>
                        <x-text-input id="item-desc" wire:model="items.0.desc" type="text" />                        
                     </div>
                  </div>
                  @else
                  <div class="grid gap-y-2 py-6">
                     <h1 class="px-6 text-2xl font-medium text-neutral-900 dark:text-neutral-100">{{ $items[0]['name'] }}</h1>
                     <p class="px-6">{{ $items[0]['desc'] }}</p>
                  </div>
                  @endif
                
                @if($is_editing)                
                  <div class="p-6 grid grid-cols-1 gap-y-3">
                     <div>
                        <label for="item-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                        <x-text-input id="item-code" wire:model="items.0.code" type="text" />
                     </div>
                     <div class="px-3 mt-3">
                        <x-inv-loc-selector />
                     </div>
                     <div class="px-3 mt-3">
                        <x-inv-tag-selector />
                     </div>
                  </div>
                @else
                  <div class="px-6 py-4 flex flex-col md:flex-row gap-x-6 gap-y-3 text-neutral-500 text-sm">                    
                     <div>{{ $items[0]['code'] ?: __('TAK ADA KODE') }}</div>
                     <div><i class="fa fa-fw fa-map-marker-alt me-2"></i>{{ $items[0]['loc_name'] ?: __('Tak ada lokasi') }}</div>
                     <div><i class="fa fa-fw fa-tag me-2"></i>{{ $items[0]['tags_list'] ?: __('Tak ada tag') }}</div>
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

                  this.$watch('secondary_currency', (value) => {
                     this.calculateMainPrice();
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
                  const unit_price = this.is_secondary_currency ? (this.secondary_unit_price ? this.secondary_unit_price : 0) : this.main_unit_price;

                  if (!uom) {
                     toast('{{ __('Uom wajib diisi') }}', { type: 'danger' });
                     return;
                  }
                  if (this.stocks.some((stock, index) => stock.currency === currency && stock.uom === uom && index !== this.editingIndex)) {
                     toast('{{ __('Mata uang dan uom tersebut sudah ada') }}', { type: 'danger' });
                     return;
                  }

                  if (this.is_secondary_currency && !this.secondary_currency) {
                     toast('{{ __('Mata uang sekunder wajib diisi') }}', { type: 'danger' });
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
               <x-modal name="stock-creator" focusable>
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
                           <x-text-input id="stock-uom" type="text" x-model="uom_input" maxlength="5" x-on:keydown.enter.prevent="$nextTick(() => { $refs.mainUnitPrice.focus() })"></x-text-input>
                        </div>    
                        <div class="col-span-1 sm:col-span-2">
                           <label for="main-unit-price" class="block px-3 mb-2 uppercase text-xs text-neutral-500" >{{ __('Harga satuan') }}<i class="fa fa-lock ml-3" x-show="is_secondary_currency" x-cloak></i></label>
                           <x-text-input-curr curr="main_currency" ::disabled="is_secondary_currency" x-ref="mainUnitPrice" x-on:keydown.enter.prevent=""
                                          id="stock-unit-price" type="number" x-model="main_unit_price" min="0"></x-text-input>
                        </div>

                        <div x-show="is_secondary_currency">
                           <label for="stock-secondary-currency" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mata uang') }}</label>
                           <x-select id="stock-secondary-currency" class="w-full" x-model="secondary_currency" x-on:change="secondary_currency ? $nextTick(() => { $refs.secondaryUnitPrice.focus() }) : (is_secondary_currency = false, secondary_unit_price = '')">
                              <option value=""></option>   
                              <template x-for="currency in getSecondaryCurrencies()" :key="currency.name">                                 
                                 <option :value="currency.name" x-text="currency.name"></option>
                              </template>
                           </x-select>
                        </div>
                        <div x-show="is_secondary_currency" class="col-span-1 sm:col-span-2">
                           <label for="secondary-unit-price" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Harga satuan') }}</label>
                           <x-text-input-curr curr="secondary_currency" id="secondary-unit-price" x-ref="secondaryUnitPrice" x-on:keydown.enter.prevent=""
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
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Unit stok') }}</label>
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
                  <x-text-button type="button" x-on:click="$dispatch('open-modal', 'stock-creator')" :disabled="!$currencies"
                                 class="rounded-full border border-neutral-300 dark:border-neutral-700 px-3 py-1">
                     <i class="fa fa-plus me-2"></i>{{ __('Tambah unit') }}
                  </x-text-button>
               </div>
               @if(!$currencies)
               <div class="mt-6 text-sm">
                  <i class="fa fa-circle-exclamation mr-2"></i>{{ __('Mata uang perlu diregistrasi sebelum menambahkan unit') }}
               </div> 
               @endif
            </div>

            @else
               <livewire:inventory.items.stocks.index :item_id="$items[0]['id']" />

            @endif
        </div>
    </div>
</div>
