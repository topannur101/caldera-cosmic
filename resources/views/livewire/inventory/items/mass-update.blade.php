<?php

use App\Livewire\Forms\LoginForm;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\InvArea;
use App\Models\InvItem;
use App\Models\User;
use App\Models\InvLoc;
use App\Models\InvItemTag;
use App\Models\InvTag;
use App\Models\InvStock;
use App\Models\InvCurr;


new #[Layout('layouts.app')]
class extends Component
{
   public LoginForm $form;

   public array $items = 
      [
         [
            'id' => 1,
            'name' => 'Barang uji coba',
            'desc' => 'Deskripsi barang uji coba',
            'code' => 'XXX10-19001',
            'location' => 'Z9-99-99',
            'tag_1' => 'Contoh tag 1',
            'tag_2' => 'Contoh tag 2',
            'tag_3' => 'Contoh tag 3',
            'curr_1' => 'IDR',
            'up_1' => 1.0,
            'uom_1' => 'EA',
            'curr_2' => 'USD',
            'up_2' => 2.0,
            'uom_2' => 'EA',
            'curr_3' => '',
            'up_3' => '',
            'uom_3' => ''
         ]
      ];

   public array $areas = [];
   public int $area_id = 0;

   public int $update_count = 0;
   public int $create_count = 0;

   public array $result = [
      'items' => [],
      'update' => [
         'success' => 0,
         'failure' => 0,
      ],
      'create' => [
         'success' => 0,
         'failure' => 0,
      ]
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
            $response = Gate::inspect('download', $item);

            if ($response->allowed()) {
               $area_ids[] = $area->id;
            }
         }
      }

      $this->form->emp_id = $user->emp_id;
      $this->areas = InvArea::whereIn('id', $area_ids)->get()->toArray();
   }
    
   public function apply(bool $is_confirmed = false)
   {
      if ($is_confirmed) {

         if ($this->create_count) {
            $this->validate([
               'area_id' => ['required', 'exists:inv_areas,id']
            ]);
         }

         foreach ($this->items as $item) {
            $this->result['items'][] = $item['id'] ? $this->updateOrCreateItem($item) : $this->updateOrCreateItem($item, false);
         }

         $this->js('toast("' . __('Unduhan dimulai...') . '", { type: "success" })');         

         $this->reset(['items']);
         $this->js('$dispatch("editor-reset")');

      } else {

         if(count($this->items) > 100) {
            $this->js('toast("' . __('Hanya maksimal 100 entri yang diperbolehkan') . '", { type: "danger" })');
            return;
   
         }
   
         $this->reset(['update_count', 'create_count', 'area_id', 'result']);

         foreach ($this->items as $key => $item) {

            // Iterate over the children of $item
            foreach ($item as $childKey => $childValue) {
                // Trim all child items
                $this->items[$key][$childKey] = trim($childValue);
        
                // Convert specific keys to uppercase
                if (in_array($childKey, ['code', 'location', 'curr_1', 'uom_1', 'curr_2', 'uom_2', 'curr_3', 'uom_3'])) {
                    $this->items[$key][$childKey] = strtoupper($this->items[$key][$childKey]);
                }
        
                // Convert specific keys to lowercase
                if (in_array($childKey, ['tag_1', 'tag_2', 'tag_3'])) {
                    $this->items[$key][$childKey] = strtolower($this->items[$key][$childKey]);
                }
        
                // Cast value with key 'id' to integer
                if ($childKey === 'id') {
                    $this->items[$key][$childKey] = (int)$this->items[$key][$childKey];
                }
        
                // Cast values with keys 'up_1', 'up_2', 'up_3' to float
                if (in_array($childKey, ['up_1', 'up_2', 'up_3'])) {
                    $this->items[$key][$childKey] = (float)$this->items[$key][$childKey];
                }
            }
        
            // Increment update or create count after cleaning
            if ($this->items[$key]['id']) {
                $this->update_count++;
            } else {
                $this->create_count++;
            }
        }
   
         $this->js('$dispatch("open-modal", "apply-confirm")');
      }

   }

   private function updateOrCreateItem(array $item, bool $is_updating = true)
   {      
      try {
         if ($is_updating) {
            $inv_item = InvItem::find($item['id']);

            if(!$inv_item) {
               throw new Exception(__('Barang dengan ID ini tidak ditemukan'));

            }
         } else {
            $inv_item = new InvItem;
            $inv_item->inv_area_id = $this->area_id;

         }


         $response = Gate::inspect('store', $inv_item);
         if($response->denied()) {
            throw new Exception ($response->message());
         }

         if ($item['code']) {
            $inv_item_exist = InvItem::where('code', $item['code'])->where('inv_area_id', $inv_item->inv_area_id)->first();
            if ($inv_item_exist) {
               if ($inv_item_exist->id !== $inv_item->id) {
                  throw new Exception(__('Barang dengan kode tersebut sudah ada'));
                  
               }
            }
         }

         $parts = explode('-', $item['location'], 2);
         $item['loc_parent'] = $parts[0];
         $item['loc_bin'] = isset($parts[1]) ? $parts[1] : '';

         $item['tags'] = [];
         $item['tag_1'] ? array_push($item['tags'], $item['tag_1']) : false;
         $item['tag_2'] ? array_push($item['tags'], $item['tag_2']) : false;
         $item['tag_3'] ? array_push($item['tags'], $item['tag_3']) : false;

         $item['stocks'] = [];

         $curr_1  = $item['curr_1']    ?? '';
         $up_1    = $item['up_1']      ?? '';
         $uom_1   = $item['uom_1']     ?? '';

         if($curr_1 || $up_1 || $uom_1) {
            $stock = [ 
               'currency' => $item['curr_1'],
               'unit_price' => $item['up_1'],
               'uom' => $item['uom_1']
            ];
            array_push($item['stocks'], $stock);
         }

         $curr_2  = $item['curr_2']    ?? '';
         $up_2    = $item['up_2']      ?? '';
         $uom_2   = $item['uom_2']     ?? '';

         if($curr_2 || $up_2 || $uom_2) {
            $stock = [ 
               'currency' => $item['curr_2'],
               'unit_price' => $item['up_2'],
               'uom' => $item['uom_2']
            ];
            array_push($item['stocks'], $stock);
         }

         $curr_3  = $item['curr_3']    ?? '';
         $up_3    = $item['up_3']      ?? '';
         $uom_3   = $item['uom_3']     ?? '';

         if($curr_3 || $up_3 || $uom_3) {
            $stock = [ 
               'currency' => $item['curr_3'],
               'unit_price' => $item['up_3'],
               'uom' => $item['uom_3']
            ];
            array_push($item['stocks'], $stock);
         }

         $validator = Validator::make(
            $item,
            [
               'name'   => ['required', 'max:128'],
               'desc'   => ['required', 'max:256'],
               'code'   => ['nullable', 'alpha_dash', 'size:11'],

               'loc_parent'   => ['required_with:loc_bin', 'alpha_num','max:3'],
               'loc_bin'      => ['required_with:loc_parent', 'alpha_dash','max:7'],
               'tags'         => ['array', 'max:3'],
               'tags.*'       => ['required', 'alpha_dash', 'max:20'],
      
               'stocks'                => ['array','min:1', 'max:3'],
               'stocks.*.currency'     => ['required', 'exists:inv_currs,name'],
               'stocks.*.unit_price'   => ['required', 'numeric', 'min:0', 'max:1000000000'],
               'stocks.*.uom'          => ['required', 'alpha_dash', 'max:5'],
            ]
         );

         if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $errorMessage = implode(', ', $errors);
            throw new Exception($errorMessage);
         }

         $loc_id = null;
         if ($item['loc_parent'] && $item['loc_bin']) {
            $loc_id = InvLoc::firstOrCreate([
               'parent' => $item['loc_parent'],
               'bin'    => $item['loc_bin'],
            ])->id;
         }

         $inv_item->name = $item['name'];
         $inv_item->desc = $item['desc'];
         $inv_item->code = $item['code'];
         $inv_item->inv_loc_id = $loc_id;
         $inv_item->save();

         // detach tags
         $item_tag_ids = InvItemTag::where('inv_item_id', $inv_item->id);
         $item_tag_ids->delete();

         // create tags
         $tag_ids = [];
         foreach ($item['tags'] as $tag) {
            $tag_ids[] = InvTag::firstOrCreate(['name' => $tag])->id;
         }

         // attach tags
         foreach($tag_ids as $tag_id) {
            InvItemTag::firstOrCreate([
               'inv_item_id' => $inv_item->id,
               'inv_tag_id'  => $tag_id,
            ]);
         }

         // detach stocks
         $stocks = InvStock::where('inv_item_id', $inv_item->id)->get();
         foreach ($stocks as $stock) {
            $stock->update([
               'is_active' => false
            ]);
         }
         
         // create and attach stocks
         foreach ($item['stocks'] as $stock) {
            $curr_id = InvCurr::where('name', $stock['currency'])->first()->id;
            $stocks = InvStock::updateOrCreate([
               'inv_item_id'  => $inv_item->id,
               'inv_curr_id'  => $curr_id,
               'uom'          => $stock['uom'],
            ],[
               'unit_price'   => $stock['unit_price'],
               'is_active'    => true
            ]);
         }
         
         $status  = __('Berhasil');
         $message = $is_updating ? __('Barang diperbarui') : __('Barang dibuat');

         $is_updating ? $this->result['update']['success']++ : $this->result['create']['success']++;

      } catch (\Throwable $th) {
         $status  = __('Gagal');
         $message = $th->getMessage();

         $is_updating ? $this->result['update']['failure']++ : $this->result['create']['failure']++;
      }

      $action = $is_updating ? __('Perbarui') : __('Buat baru');
      $item['action']   = $action;
      $item['status']   = $status;
      $item['message']  = $message;

      return $item;
   }

   public function download($area_id)
   {
       // Create a unique token for this download request
       $token = md5(uniqid());

       // Store the token in the session
       session()->put('inv_items_token', $token);
       
       $this->js('toast("' . __('Unduhan dimulai...') . '", { type: "success" })');
       // Redirect to a temporary route that will handle the streaming
       return redirect()->route('download.inv-items', ['token' => $token, 'area_id' => $area_id]);
   }

};

?>

<x-slot name="title">{{ __('Pembaruan massal') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Pembaruan massal') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   @if (count($areas))
      <div wire:key="modals">
         <x-modal name="warning">
            <div class="p-6 space-y-4 text-sm">
               <div class="flex justify-between items-start">
                  <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                     <i class="fa fa-exclamation-triangle mr-2 text-yellow-600"></i>{{ __('Teralu banyak') }}
                  </h2>
                  <x-text-button type="button" x-on:click="$dispatch('close')">
                     <i class="fa fa-times"></i>
                  </x-text-button>
               </div>
               <div>
                  {{ __('Entri yang dimasukkan melebihi 100, harap kurangi entri sebelum melanjutkan.') }}
               </div>
               <div class="flex items-center justify-end">
                  <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Paham') }}</x-secondary-button>
               </div>
            </div>
         </x-modal>
         <x-modal name="apply-confirm">
            <div class="p-6 space-y-4 text-sm">
               <div class="flex justify-between items-start">
                  <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Ringkasan') }}
                  </h2>
                  <x-text-button type="button" x-on:click="$dispatch('close')">
                     <i class="fa fa-times"></i>
                  </x-text-button>
               </div>
               @if(count($result['items']))
                  <div class="p-6 border border-neutral-200 rounded-lg">
                     <div class="flex items-center space-x-2 mb-2">
                        <h2 class="font-bold text-neutral-800">{{ __('Pembaruan') }}</h2>
                     </div>
                     <div>
                        <x-pill color="green">{{ $result['update']['success'] }}</x-pill>{{ ' ' . __('barang diperbarui.') }}                     
                     </div>
                     <div>
                        <x-pill color="red">{{ $result['update']['failure'] }}</x-pill>{{ ' ' . __('barang gagal diperbarui.') }}                     
                     </div>
                  </div>
                  <div class="p-6 border border-neutral-200 rounded-lg">
                     <div class="flex items-center space-x-2 mb-2">
                        <h2 class="font-bold text-neutral-800">{{ __('Pembuatan') }}</h2>
                     </div>
                     <div>
                        <x-pill color="green">{{ $result['create']['success'] }}</x-pill>{{ ' ' . __('barang dibuat.') }}                     
                     </div>
                     <div>
                        <x-pill color="red">{{ $result['create']['failure'] }}</x-pill>{{ ' ' . __('barang gagal dibuat.') }}                     
                     </div>
                  </div>
                  <div>
                     {{ __('Rincian mengenai berhasil atau gagalnya setiap barang dapat dilihat pada file CSV yang seharusnya sudah terunduh.') }}
                  </div>

               @else
                  <div>
                     <x-pill>{{ $update_count }}</x-pill>{{ ' ' . __('barang akan diperbarui.') }}
                  </div>
                  @if($create_count)
                  <div>
                     <x-pill>{{ $create_count }}</x-pill>{{ ' ' . __('barang akan dibuat.') }}
                  </div>
                  <div>
                     {{ __('Ke area mana barang baru tersebut akan diregistrasikan?') }}
                  </div>
                  <div>
                     <label for="area_id"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Area') }}</label>
                     <x-select wire:model="area_id" class="w-full">
                        <option value=""></option>
                        @foreach($areas as $area)
                           <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
                        @endforeach
                     </x-select>
                     <x-input-error :messages="$errors->get('area_id')" class="mt-2" />
                  </div>
                  @endif
                  <div class="flex items-center justify-end">
                     <x-primary-button type="button" wire:click="apply(true)">{{ __('Terapkan') }}</x-secondary-button>
                  </div>
               @endif

            </div>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target="apply"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target="apply" class="hidden"></x-spinner>
         </x-modal>
         <x-modal name="guide" maxWidth="lg">
            <div x-data="{ backup: false }" class="p-6 space-y-4 text-sm">
               <div class="flex justify-between items-start">
                  <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                     {{ __('Panduan') }}
                  </h2>
                  <x-text-button type="button" x-on:click="$dispatch('close')">
                     <i class="fa fa-times"></i>
                  </x-text-button>
               </div>
               <!-- Section 1: ID barang itu penting -->
               <div x-show="!backup" class="p-6 border border-neutral-200 rounded-lg">
                  <div class="flex items-center space-x-2 mb-2">
                     <i class="fas fa-info-circle text-neutral-500"></i>
                     <h2 class="font-bold text-neutral-800">{{ __('ID barang itu penting') }}</h2>
                  </div>
                  <p class="text-neutral-600 leading-relaxed">
                     {{ __('ID barang diberikan oleh Caldera. Gunakanlah ID tersebut untuk memperbarui identitas barang secara massal seperti nama, deskripsi, lokasi, tag, dan satuan unit.') }}
                  </p>
               </div>

               <!-- Section 2: Kosongkan ID untuk membuat barang baru -->
               <div x-show="!backup" class="p-6 border border-neutral-200 rounded-lg">
                  <div class="flex items-center space-x-2 mb-2">
                     <i class="fas fa-plus-circle text-neutral-500"></i>
                     <h2 class="font-bold text-neutral-800">{{ __('Kosongkan ID untuk membuat barang baru') }}</h2>
                  </div>
                  <p class="text-neutral-600 leading-relaxed">
                     {{ __('Jika ID dikosongkan, Caldera akan menganggap entri tersebut sebagai barang baru. Informasi yang wajib diisi untuk barang baru adalah: nama, deskripsi, area, dan unit stok.') }}
                  </p>
               </div>

               <!-- Section 3: Maksimum 100 entri -->
               <div x-show="!backup" class="p-6 border border-neutral-200 rounded-lg">
                  <div class="flex items-center space-x-2 mb-2">
                     <i class="fa fa-arrows-down-to-line text-neutral-500"></i>
                     <h2 class="font-bold text-neutral-800">{{ __('Maksimum 100 entri') }}</h2>
                  </div>
                  <p class="text-neutral-600 leading-relaxed">
                     {{ __('Kamu dapat memperbarui atau membuat barang dengan jumlah maksimal 100 entri dalam sekali operasi.') }}
                  </p>
               </div>

               <!-- Section 4: Unduh backup -->
               <div x-show="backup" class="p-6 border border-neutral-200 rounded-lg">
                  <div class="flex items-center space-x-2 mb-2">
                     <i class="fas fa-download text-neutral-500"></i>
                     <h2 class="font-bold text-neutral-800">{{ __('Unduh backup') }}</h2>
                  </div>
                  <p class="text-neutral-600 leading-relaxed">
                     {{ __('Kamu bisa mengunduh daftar lengkap barang dari suatu area sebagai tindakan pencegahan bila terjadi kesalahan.') }}
                  </p>
               </div>
               
               <!-- Section 4: Unduh backup -->
               <div x-show="backup" class="grid grid-cols-1 gap-y-2 p-6">
                  @foreach ($areas as $area)
                     <div>
                        <x-text-button type="button" wire:click="download({{ $area['id'] }})"><i class="fa fa-download mr-3"></i>{{ $area['name'] }}</x-text-button>
                     </div>
                  @endforeach
               </div>

               <div class="flex items-center justify-between">
                  <x-secondary-button x-show="backup" x-on:click="backup = false" type="button">{{ __('Kembali') }}</x-secondary-button>
                  <x-text-button x-show="!backup" x-on:click="backup = true" type="button" class="uppercase tracking-wide font-bold text-xs">{{ __('Unduh backup') }}</x-text-button>
                  <x-primary-button x-show="!backup" type="button" x-on:click="$dispatch('close')">{{ __('Paham') }}</x-primary-button>
               </div>
            </div>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target="download"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target="download" class="hidden"></x-spinner>
         </x-modal>
      </div>
      <div 
      x-data="editorData()"
      x-init="editorInit()">
         <div class="mb-6 flex justify-between">
            <div class="px-3">
               <span x-text="rowCount"></span><span class="">{{ ' ' . __('baris') }}</span>
            </div>
            <div class="flex gap-x-2">
               <div class="btn-group">
                  <x-secondary-button type="button" x-on:click="editorDownload"><i class="fa fa-fw fa-download"></i></x-secondary-button>
                  <x-secondary-button type="button" x-on:click="editorReset"><i class="fa fa-fw fa-undo"></i></x-secondary-button>
               </div>
               <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'guide')">{{ __('Panduan') }}</x-secondary-button>
               <x-secondary-button type="button" x-on:click="editorApply">
                  <div class="relative">
                     <span wire:loading.class="opacity-0" wire:target="apply"><i class="fa fa-check mr-2"></i>{{ __('Terapkan') }}</span>
                     <x-spinner wire:loading.class.remove="hidden" wire:target="apply" class="hidden sm mono"></x-spinner>                
                  </div>                
               </x-secondary-button>
            </div>
         </div>
         <div class="bg-white dark:bg-neutral-800 shadow rounded-lg text-sm" id="editor-table" wire:ignore></div>
      </div>

   @else

      <div class="text-center w-72 py-20 mx-auto">
         <i class="fa fa-person-circle-question text-5xl mb-8 text-neutral-400 dark:text-neutral-600"></i>
         <div class="text-neutral-500">{{ __('Kamu tidak memiliki wewenang untuk mengelola barang di area manapun.') }}</div>
      </div>
   @endif

</div>

@script
<script type="module">   
   Alpine.data('editorData', () => ({
         table: null,
         items: @entangle('items'),
         itemsDefault: null,
         rowCount: 0,            
         
         editorInit() {
            const columns = [
               { title: 'id', field: 'id', width: 50 }, 
               { title: 'name', field: 'name', width: 100 }, 
               { title: 'desc', field: 'desc', width: 100 }, 
               { title: 'code', field: 'code', width: 100 }, 
               { title: 'location', field: 'location', width: 80 },
               { title: 'tag_1', field: 'tag_1', width: 80, cssClass: "border-l-2" },
               { title: 'tag_2', field: 'tag_2', width: 80, },
               { title: 'tag_3', field: 'tag_3', width: 80, },
               { title: 'curr_1', field: 'curr_1', cssClass: "border-l-2"},
               { title: 'up_1', field: 'up_1', width: 80 },
               { title: 'uom_1', field: 'uom_1' },

               { title: 'curr_2', field: 'curr_2', cssClass: "border-l-2"},
               { title: 'up_2', field: 'up_2', width: 80 },
               { title: 'uom_2', field: 'uom_2' },

               { title: 'curr_3', field: 'curr_3', cssClass: "border-l-2"},
               { title: 'up_3', field: 'up_3', width: 80 },
               { title: 'uom_3', field: 'uom_3' },
            ];
            
            this.itemsDefault = this.itemsDefault ? this.itemsDefault : this.items,

            // Initialize Tabulator
            this.table = new Tabulator("#editor-table", {
               
               data: this.itemsDefault,
               layout: "fitColumns",
               columns: columns,
               height: "480px",

               //enable range selection
               selectableRange: 1,
               selectableRangeColumns: true,
               selectableRangeRows: true,
               selectableRangeClearCells: true,

               //change edit trigger mode to make cell navigation smoother
               editTriggerEvent:"dblclick",

               //configure clipboard to allow copy and paste of range format data
               clipboard: true,
               clipboardCopyStyled:false,
               clipboardCopyConfig:{
                  rowHeaders:false,
                  columnHeaders:false,
               },
               clipboardCopyRowRange:"range",
               clipboardPasteParser:"range",
               clipboardPasteAction:"replace",

               rowHeader:{resizable: false, frozen: true, width:40, hozAlign:"center", formatter: "rownum", cssClass:"range-header-col", editor:false},
               columnDefaults:{
                  headerSort:false,
                  headerHozAlign:"center",
                  resizable:"header",
                  editor: "input"
               }
            });      
            
            this.table.on("dataLoaded", (data) => {

               if (data.length > 100) {
                  $dispatch('open-modal', 'warning');
               }

               // Check if the last row exists and is empty (all properties are empty strings)
               if (data.length > 0) {
                  const lastRow = data[data.length - 1];
                  const isLastRowEmpty = Object.values(lastRow).every(value => value === "");

                  // If the last row is empty, remove it
                  if (isLastRowEmpty) {
                     data.pop(); // Remove the last row from the data array
                     this.table.setData(data);
                  }
               }

               this.rowCount = data.length; // Update the row count
            });

            this.table.on("dataChanged", (data) => {             
               this.rowCount = data.length; // Update the row count
            });
            
            document.addEventListener('editor-reset', event => {
               this.table.destroy();
               this.editorInit();
            });
         },
         
         editorApply() {
            this.items = this.table.getData();
            $wire.apply();
         },

         editorReset() {
            Livewire.navigate("{{ route('inventory.items.mass-update') }}");
         },

         editorDownload() {
            this.table.download("csv", "mass_update_editor.csv"); 
         },
   }));
</script>
@endscript