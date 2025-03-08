<?php

use App\Livewire\Forms\LoginForm;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\InvArea;
use App\Models\InvItem;
use App\Models\User;


new #[Layout('layouts.app')]
class extends Component
{
   public LoginForm $form;

   public array $items = [];
   public array $areas = [];
   public int $area_id = 0;

   public int $update_count = 0;
   public int $create_count = 0;

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

         $this->form->authenticate();

         $this->js('toast("' . count($this->items) . ' ' . __('entri terkonfirmasi.') . '", { type: "success" })');

      } else {

         if(count($this->items) > 100) {
            $this->js('toast("' . __('Hanya maksimal 100 entri yang diperbolehkan') . '", { type: "danger" })');
            return;
   
         } else {
            $this->js('toast("' . count($this->items) . ' ' . __('entri terdeteksi.') . '", { type: "success" })');
         }
   
         $this->reset(['update_count', 'create_count', 'area_id']);
   
         foreach ($this->items as $item) {
            $item['id'] ? $this->update_count++ : $this->create_count++;
         }
   
         $this->js('$dispatch("open-modal", "apply-confirm")');
      }

   }

   public function commit()
   {
      $this->js('toast("' . count($this->items) . ' ' . __('entri terdeteksi.') . '", { type: "success" })');
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
                  <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Konfirmasi') }}
                  </h2>
                  <x-text-button type="button" x-on:click="$dispatch('close')">
                     <i class="fa fa-times"></i>
                  </x-text-button>
               </div>
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
               </div>
               @endif
               <div>
                  <label for="password"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kata sandi') }}</label>
                  <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                     type="password"
                     name="password"
                     required autocomplete="current-password" />
                  <x-input-error :messages="$errors->get('form.emp_id')" class="mt-2" />
                  <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
               </div>

               <div class="flex items-center justify-end">
                  <x-primary-button type="button" wire:click="apply(true)">{{ __('Terapkan') }}</x-secondary-button>
               </div>
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
                  <x-text-button x-show="backup" x-on:click="backup = false" type="button" class="uppercase tracking-wide font-bold text-xs">{{ __('Kembali') }}</x-text-button>
                  <x-text-button x-show="!backup" x-on:click="backup = true" type="button" class="uppercase tracking-wide font-bold text-xs">{{ __('Unduh backup') }}</x-text-button>
                  <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Paham') }}</x-secondary-button>
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
               <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'guide')"><i class="fa fa-book fa-fw mr-2"></i>{{ __('Panduan') }}</x-secondary-button>
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
         rowCount: 0,
         defaultData: 
            [
               {
                  id: 1,
                  name: "Barang uji coba",
                  desc: "Deskripsi barang uji coba",
                  code: "XXX10-19001",
                  location: "Z9-99-99",
                  "tag 1": "Contoh tag 1",
                  "tag 2": "Contoh tag 2",
                  "tag 3": "Contoh tag 3",
                  "curr 1": "IDR",
                  "up 1": 1.0,
                  "uom 1": "EA",
                  "curr 2": "USD",
                  "up 2": 2.0,
                  "uom 2": "EA",
                  "curr 3": "EA-R",
                  "up 3": 3.0,
                  "uom 3": "PACK"
               }
            ],
         
         editorInit() {
            const columns = [
               { title: 'id', field: 'id', width: 50 }, 
               { title: 'name', field: 'name', width: 100 }, 
               { title: 'desc', field: 'desc', width: 100 }, 
               { title: 'code', field: 'code', width: 100 }, 
               { title: 'location', field: 'location', width: 80 },
               { title: 'tag 1', field: 'tag 1', width: 80, cssClass: "border-l-2" },
               { title: 'tag 2', field: 'tag 2', width: 80, },
               { title: 'tag 3', field: 'tag 3', width: 80, },
               { title: 'curr 1', field: 'curr 1', cssClass: "border-l-2"},
               { title: 'up 1', field: 'up 1', width: 80 },
               { title: 'uom 1', field: 'uom 1' },

               { title: 'curr 2', field: 'curr 2', cssClass: "border-l-2"},
               { title: 'up 2', field: 'up 2', width: 80 },
               { title: 'uom 2', field: 'uom 2' },

               { title: 'curr 3', field: 'curr 3', cssClass: "border-l-2"},
               { title: 'up 3', field: 'up 3', width: 80 },
               { title: 'uom 3', field: 'uom 3' },
            ];

            // Initialize Tabulator
            this.table = new Tabulator("#editor-table", {
               data: this.defaultData,
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
         },
         
         editorApply() {
            this.items = this.table.getData();
            $wire.apply();
         },

         editorReset() {
            this.table.destroy();
            this.editorInit();
         },

         editorDownload() {
            this.table.download("csv", "mass_update_editor.csv"); 
         },
   }));
</script>
@endscript