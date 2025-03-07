<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\InvItem;


new #[Layout('layouts.app')]
class extends Component
{

   public $items = [];

    
   public function apply()
   {

      if(count($this->items) > 100) {
         $this->js('toast("' . __('Hanya maksimal 100 entri yang diperbolehkan') . '", { type: "danger" })');
      } else {
         $this->js('toast("' . count($this->items) . ' ' . __('entri terdeteksi.') . '", { type: "success" })');
      }
      // checks if it's under 100
      // categorize to Update items and Create items (ask area)
      // return status of update for any reason (operation: update/create, status: success, fail )
      // dd($this);
   }

};

?>

<x-slot name="title">{{ __('Pembaruan massal') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Pembaruan massal') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
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
      <x-modal name="guide" maxWidth="lg">
         <div class="p-6 space-y-4 text-sm">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Panduan') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="fa fa-times"></i>
                </x-text-button>
            </div>
            <!-- Section 1: ID barang itu penting -->
            <div class="p-6 border border-neutral-200 rounded-lg">
               <div class="flex items-center space-x-2 mb-2">
                  <i class="fas fa-info-circle text-neutral-500"></i>
                  <h2 class="font-bold text-neutral-800">{{ __('ID barang itu penting') }}</h2>
               </div>
               <p class="text-neutral-600 leading-relaxed">
                  {{ __('ID barang diberikan oleh Caldera. Gunakanlah ID tersebut untuk memperbarui identitas barang secara massal seperti nama, deskripsi, lokasi, tag, dan satuan unit.') }}
               </p>
            </div>

            <!-- Section 2: Kosongkan ID untuk membuat barang baru -->
            <div class="p-6 border border-neutral-200 rounded-lg">
               <div class="flex items-center space-x-2 mb-2">
                  <i class="fas fa-plus-circle text-neutral-500"></i>
                  <h2 class="font-bold text-neutral-800">{{ __('Kosongkan ID untuk membuat barang baru') }}</h2>
               </div>
               <p class="text-neutral-600 leading-relaxed">
                  {{ __('Jika ID dikosongkan, Caldera akan menganggap entri tersebut sebagai barang baru. Informasi yang wajib diisi untuk barang baru adalah: nama, deskripsi, area, dan unit stok.') }}
               </p>
            </div>

            <!-- Section 3: Maksimum 100 entri -->
            <div class="p-6 border border-neutral-200 rounded-lg">
               <div class="flex items-center space-x-2 mb-2">
                  <i class="fas fa-exclamation-triangle text-neutral-500"></i>
                  <h2 class="font-bold text-neutral-800">{{ __('Maksimum 100 entri') }}</h2>
               </div>
               <p class="text-neutral-600 leading-relaxed">
                  {{ __('Kamu dapat memperbarui atau membuat barang dengan jumlah maksimal 100 entri dalam sekali operasi.') }}
               </p>
            </div>

            <!-- Section 4: Unduh backup -->
            <div class="p-6 border border-neutral-200 rounded-lg">
               <div class="flex items-center space-x-2 mb-2">
                  <i class="fas fa-download text-neutral-500"></i>
                  <h2 class="font-bold text-neutral-800">{{ __('Unduh backup') }}</h2>
               </div>
               <p class="text-neutral-600 leading-relaxed">
                  {{ __('Kamu bisa mengunduh informasi seluruh barang dari suatu area sebagai tindakan pencegahan bila terjadi kesalahan.') }}
               </p>
            </div>
            <div class="flex items-center justify-between">
               <x-text-button type="button" class="uppercase tracking-wide font-bold text-xs">{{ __('Unduh backup') }}</x-text-button>
               <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Paham') }}</x-secondary-button>
            </div>
         </div>
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
            <x-secondary-button type="button" x-on:click="editorDownload">{{ __('Unduh') }}</x-secondary-button>
            <x-secondary-button type="button" x-on:click="editorReset">{{ __('Reset') }}</x-secondary-button>
            <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'guide')">{{ __('Panduan') }}</x-secondary-button>
            <x-secondary-button type="button" x-on:click="editorApply">{{ __('Terapkan') }}</x-secondary-button>
         </div>
      </div>
      <div class="bg-white dark:bg-neutral-800 shadow rounded-lg text-sm" id="editor-table" wire:ignore></div>
   </div>
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