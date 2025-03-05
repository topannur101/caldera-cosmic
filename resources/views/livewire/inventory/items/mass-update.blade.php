<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\InvItem;


new #[Layout('layouts.app')]
class extends Component
{

   public $items = [];
   public $products = [];
   public $columns = [];
   public $tableData = [];
   public $changedData = [];
    
   public function mount()
   {
      $this->products = InvItem::all()->toArray();
      $this->items = [
         [
            'id'        => 1,
            'name'      => __('Barang uji coba'),
            'desc'      => __('Deskripsi barang uji coba'),
            'code'      => __('XXX10-19001'),
            'location'  => 'Z9-99-99',
            'tag 1'     => __('Contoh tag 1'),
            'tag 2'     => __('Contoh tag 2'),
            'tag 3'     => __('Contoh tag 3'),
            'curr 1'    => 'IDR',
            'up 1'      => 1.0,
            'uom 1'     => 'EA',
            'curr 2'    => 'USD',
            'up 2'      => 2.0,
            'uom 2'     => 'EA',
            'curr 3'    => 'EA-R',
            'up 3'      => 3.0,
            'uom 3'     => 'PACK',
         ]
      ];
   }

   public function validateItems()
   {
      dd($this);
   }

   public function saveChanges($changes)
   {
       $changedData = json_decode($changes, true);
       
       $toCreate = [];
       $toUpdate = [];
       
       foreach ($changedData as $row) {
           if (isset($row['id']) && !empty($row['id'])) {
               $toUpdate[] = $row;
           } else {
               $toCreate[] = $row;
           }
       }
       
       // Batch updates
       if (!empty($toUpdate)) {
           foreach ($toUpdate as $item) {
               InvItem::find($item['id'])->update($item);
           }
       }
       
       // Batch creates
       if (!empty($toCreate)) {
           InvItem::insert($toCreate);
       }
       
       // Refresh products
       $this->products = InvItem::all()->toArray();
       
       return $this->dispatch('changes-saved', message: 'Successfully saved ' . (count($toCreate) + count($toUpdate)) . ' products');
   }

};

?>

<x-slot name="title">{{ __('Perbarui massal') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Perbarui massal') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <div 
   x-data="editorData()"
   x-init="editorInit()">
      <div class="mb-6">
         <x-secondary-button type="button">{{ __('Unduh templat') }}</x-secondary-button>
         <x-secondary-button type="button">{{ __('Panduan') }}</x-secondary-button>
         <x-secondary-button type="button" x-on:click="validate">{{ __('Validasi') }}</x-secondary-button>
      </div>
      <div id="editor-table" wire:ignore></div>
   </div>
</div>

@script
<script type="module">   
   Alpine.data('editorData', () => ({
         table: null,
         items: @entangle('items'),
         
         editorInit() {
            const columns = [
               { title: 'id', field: 'id', width: 50 }, 
               { title: 'name', field: 'name', width: 150 }, 
               { title: 'desc', field: 'desc', width: 250 }, 
               { title: 'code', field: 'code', width: 100 }, 
               { title: 'location', field: 'location', width: 150 },
               { title: 'tag 1', field: 'tag 1', width: 100 },
               { title: 'tag 2', field: 'tag 2', width: 100 },
               { title: 'tag 3', field: 'tag 3', width: 100 },
               { title: 'curr 1', field: 'curr 1', width: 80 },
               { title: 'up 1', field: 'up 1', width: 120 }, 
               { title: 'uom 1', field: 'uom 1', width: 80 },
               { title: 'curr 2', field: 'curr 2', width: 80 },
               { title: 'up 2', field: 'up 2', width: 120 },
               { title: 'uom 2', field: 'uom 2', width: 80 },
               { title: 'curr 3', field: 'curr 3', width: 80 },
               { title: 'up 3', field: 'up 3', width: 120 },
               { title: 'uom 3', field: 'uom 3', width: 80 },
            ];

            // Initialize Tabulator
            this.table = new Tabulator("#editor-table", {
               data: this.items,
               layout: "fitColumns",
               columns: columns,
               // editable: true,

               //enable range selection
               selectableRange: 1,
               selectableRangeColumns: true,
               selectableRangeRows: true,
               selectableRangeClearCells: true,

               //change edit trigger mode to make cell navigation smoother
               // editTriggerEvent:"dblclick",

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
                  editor:"input",
                  resizable:"header",
                  width:100,
               },
            });
         },
         
         validate() {
            this.items = this.table.getData();
            console.log(this.items);
            $wire.validateItems();
         }
   }));

   // Listen for changes-saved event
   document.addEventListener('changes-saved', (event) => {
         alert(event.detail.message);
   });
</script>
@endscript