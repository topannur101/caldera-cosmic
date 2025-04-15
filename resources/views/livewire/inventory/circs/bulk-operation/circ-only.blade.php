<?php

use App\Livewire\Forms\LoginForm;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\InvArea;
use App\Models\InvItem;
use App\Models\User;
use App\Models\InvCurr;
use App\Models\InvCirc;
use App\Models\InvStock;
use Carbon\Carbon;


new #[Layout('layouts.app')]
class extends Component
{
   public string $type = ''; // deposit, capture, withdrawal
   public array $circs =
   [
      [
         'item_id'      => 0,  // user defined
         'item_code'    => '', // user defined
         'type'         => '', // global
         'curr'         => '', // user defined
         'uom'          => '', // user defined
         'inv_stock_id' => 0,  // based on item_id, curr, and uom
         'qty_relative' => 0,  // user defined
         'amount'       => 0,  // automatic calculation
         'unit_price'   => 0,  // copy from inv_stock unit_price
         'remarks'      => ''  // user defined
      ]
   ];

   public array $areas = [];
   public int $area_id = 0;

   public int $count = 0;

   public array $result = [
      'circs'     => [],
      'success'   => 0,
      'failure'   => 0,
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
            $response = Gate::inspect('circCreate', $item);

            if ($response->allowed()) {
               $area_ids[] = $area->id;
            }
         }
      }

      $this->areas = InvArea::whereIn('id', $area_ids)->get()->toArray();
   }
    
   public function apply(bool $is_confirmed = false)
   {
      if ($is_confirmed) {

         $this->validate([
            'area_id'   => ['required', 'exists:inv_areas,id'],
            'type'      => ['required', 'in:deposit,capture,withdrawal']
         ]);

         foreach ($this->circs as $circ) {
            $this->result['circs'][] = $this->createCirc($circ);
         } 

         $this->reset(['circs']);
         $this->js('$dispatch("editor-reset")');

         if($this->result['failure']) {
            $this->js('toast("' . __('Unduhan dimulai...') . '", { type: "success" })');        
               
            // CSV download
            $filename = __('Hasil operasi massal (sirkulasi)') . ' ' . Carbon::now()->format('Y-m-d His') . '.csv';
            $handle = fopen('php://temp', 'r+');
            $headers = [
               'item_id', 'item_code', 'curr', 'qty_relative', 'uom', 'remarks',
               __('Area'), __('Tindakan'), __('Status'), __('Pesan')
            ];
            
            fputcsv($handle, $headers);
            
            foreach ($this->result['circs'] as $circ) {
               $row = [

                     $circ['item_id']     ?? '',
                     $circ['item_code']   ?? '',
                     $circ['curr']        ?? '',
                     $circ['qty_relative'] ?? '',
                     $circ['uom']         ?? '',
                     $circ['remarks']     ?? '',
   
                     $circ['area']        ?? '',
                     $circ['action']      ?? '',
                     $circ['status']      ?? '',
                     $circ['message']     ?? ''
               ];            
               fputcsv($handle, $row);
            }
            
            rewind($handle);
            
            $csv = stream_get_contents($handle);
            fclose($handle);
            
            return response()->streamDownload(function () use ($csv) {
               echo $csv;
            }, $filename, [
               'Content-Type' => 'text/csv',
            ]);
         }

      } else {

         if(count($this->circs) > 100) {
            $this->js('toast("' . __('Hanya maksimal 100 entri yang diperbolehkan') . '", { type: "danger" })');
            return;
   
         }
   
         // $this->reset(['update_count', 'create_count', 'area_id', 'result']);
         $this->reset(['count', 'area_id', 'type', 'result']);

         foreach ($this->circs as $key => $circ) {

            // Iterate over the children of $circ
            foreach ($circ as $childKey => $childValue) {
                // Trim all child items
                $this->circs[$key][$childKey] = trim($childValue);
        
                // Convert specific keys to uppercase
                if (in_array($childKey, ['item_code', 'curr', 'uom'])) {
                    $this->circs[$key][$childKey] = strtoupper($this->circs[$key][$childKey]);
                }
        
                // Cast value with key 'id' to integer
                if ($childKey === 'item_id') {
                    $this->circs[$key][$childKey] = (int)$this->circs[$key][$childKey];
                }
        
                // Cast values with key 'qty_relative' to integer
                if (in_array($childKey, ['qty_relative'])) {
                    $this->circs[$key][$childKey] = (float)$this->circs[$key][$childKey];
                }
            }

            $this->count++;
        }
   
         $this->js('$dispatch("open-modal", "apply-confirm")');
      }

   }

   private function createCirc(array $circ)
   {
      $inv_circ = new InvCirc();
      $inv_circ->type = $this->type;

      $area = InvArea::find($this->area_id);

      try {

         $inv_item = null;

         if ($circ['item_id'] ?? false) {
            $inv_item = InvItem::find($circ['item_id']);

         } elseif ($circ['item_code']) {
            $inv_item = InvItem::where('code', $circ['item_code'])
               ->where('inv_area_id', $this->area_id)
               ->first();

            if(!$inv_item) {
               throw new Exception(__('Barang dengan kode item ini tidak ditemukan di area yang dipilih'));

            }
         }

         if($inv_item) {
            if($inv_item->inv_area_id !== $this->area_id) {
               throw new Exception(__('Barang dengan ID ini bukan untuk area yang dipilih'));
            }

         } else {
            throw new Exception(__('Barang tidak ditemukan'));

         }

         $response = Gate::inspect('circCreate', $inv_item);
         if($response->denied()) {
            throw new Exception ($response->message());
         }

         $validator = Validator::make(
            $circ,
            [
               'curr'         => ['exists:inv_currs,name'],
               'uom'          => ['required', 'alpha_dash', 'max:5'],
               'qty_relative' => ['required', 'min:0', 'max:100000'],
               'remarks'      => ['required', 'string', 'max:256'],
            ]
         );

         if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $errorMessage = implode(', ', $errors);
            throw new Exception($errorMessage);
         }

         $curr = InvCurr::where('name', $circ['curr'])->first();

         if (!$curr) {
            throw new Exception(__('Mata uang tidak dikenal'));
            
         }

         $stock = InvStock::where('inv_item_id', $inv_item->id)
            ->where('uom', $circ['uom'])
            ->where('inv_curr_id', $curr->id)
            ->first();

         if (!$stock) {
            throw new Exception(__('Barang tersebut tidak memiliki unit stok dengan uom dan mata uang tersebut'));
         }

         $amount = 0;
         $amount = $stock->unit_price * $circ['qty_relative'];
         $unit_price = $stock->unit_price;
   
         // amount should always be main currency (USD)
         if($amount > 0 && $curr->id !== 1) {
            $amount /= $curr->rate;
            $unit_price /= $curr->rate;
         }

         $inv_circ->amount       = $amount;
         $inv_circ->unit_price   = $unit_price; 
   
         $inv_circ->type         = $this->type;
         $inv_circ->inv_stock_id = $stock->id;
         $inv_circ->qty_relative = $circ['qty_relative'];
         $inv_circ->remarks      = $circ['remarks'];
   
         $inv_circ->user_id      = Auth::user()->id;
         $inv_circ->is_delegated = false;

         $inv_circ->save();   

         $status  = __('Berhasil');
         $message = __('Sirkulasi dibuat');
         $this->result['success']++;
      

      } catch (\Throwable $th) {
         $status  = __('Gagal');
         $message = $th->getMessage();
         $this->result['failure']++;
      }

      $circ['area']     = $area?->name;
      $circ['action']   = $inv_circ->type_friendly();
      $circ['status']   = $status;
      $circ['message']  = $message;

      return $circ;
   }

};

?>

<x-slot name="title">{{ __('Operasi massal sirkulasi') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Operasi massal sirkulasi') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-700 dark:text-neutral-200">
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
               @if(count($result['circs']))
                  @if($result['success'] || $result['failure'])
                  <div class="p-6 border border-neutral-200 dark:border-neutral-700 rounded-lg">
                     <div class="flex items-center space-x-2 mb-2">
                        <h2 class="font-bold text-xl">{{ __('Hasil') }}</h2>
                     </div>
                     @if($result['success'])
                     <div>
                        <x-pill color="green">{{ $result['success'] }}</x-pill>{{ ' ' . __('sirkulasi dibuat.') }}                     
                     </div>
                     @endif
                     @if($result['failure'])
                     <div>
                        <x-pill color="red">{{ $result['failure'] }}</x-pill>{{ ' ' . __('sirkulasi gagal dibuat.') }}                     
                     </div>
                     @endif
                  </div>
                  @endif
                  @if($result['failure'])
                  <div class="p-4 text-xs text-neutral-800 dark:text-neutral-400 rounded-lg bg-neutral-200 dark:bg-neutral-900">
                     <i class="fa fa-info-circle me-2"></i>{{ __('Alasan mengapa gagal dapat dilihat pada 3 kolom terakhir (Tindakan, Status, dan Pesan) pada CSV yang terunduh.') }}
                  </div>
                  @endif
                  <div class="flex items-center justify-end">
                     <x-primary-button type="button" x-on:click="$dispatch('close')">{{ __('Selesai') }}</x-secondary-button>
                  </div>
               @else
                  <div>
                     <x-pill>{{ $count }}</x-pill>{{ ' ' . __('sirkulasi akan dibuat.') }}
                  </div>
                  <div>
                     <div class="mb-3">{{ __('Tindakan sirkulasi apa yang akan kamu lakukan?') }}</div>
                     <div class="btn-group w-full">
                        <x-radio-button wire:model="type" grow value="deposit" name="type" id="type-deposit">
                              <div class="text-center my-auto">
                                 <i class="fa fa-fw fa-plus text-green-500 text-lg"></i>
                              </div>
                        </x-radio-button>
                        <x-radio-button wire:model="type" grow value="capture" name="type" id="type-capture">
                              <div class="text-center my-auto">
                                 <i class="fa fa-fw fa-code-commit text-yellow-600 text-lg"></i>
                              </div>
                        </x-radio-button>
                        <x-radio-button wire:model="type" grow value="withdrawal" name="type" id="type-withdrawal">
                              <div class="text-center my-auto">
                                 <i class="fa fa-fw fa-minus text-red-500 text-lg"></i>
                              </div>
                        </x-radio-button>
                     </div>
                     <x-input-error :messages="$errors->get('type')" class="mt-2" />
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
                  <div class="flex items-center justify-end">
                     <x-secondary-button type="button" wire:click="apply(true)">{{ __('Lanjut') }}<i class="fa fa-chevron-right ml-2"></i></x-secondary-button>
                  </div>
               @endif

            </div>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target="apply"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target="apply" class="hidden"></x-spinner>
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

               <div class="p-6 border border-neutral-200 dark:border-neutral-700 rounded-lg">
                  <div class="flex items-center space-x-2 mb-2">
                     <i class="fa fa-clipboard text-neutral-500"></i>
                     <h2 class="font-bold text-xl">{{ __('Salin dan tempel') }}</h2>
                  </div>
                  <p class="leading-relaxed">
                     {{ __('Tempel daftar sirkulasi yang hendak kamu lakukan di kotak editor. Maksimal 100 entri dalam sekali operasi.') }}
                  </p>
               </div>

               <div class="p-6 border border-neutral-200 dark:border-neutral-700 rounded-lg">
                  <div class="flex items-center space-x-2 mb-2">
                     <i class="fas fa-info-circle text-neutral-500"></i>
                     <h2 class="font-bold text-xl">{{ __('Gunakan ID item atau kode item') }}</h2>
                  </div>
                  <p class="leading-relaxed">
                     {{ __('Sirkulasi akan dibuat pada barang dengan ID atau kode item yang kamu tentukan. Caldera akan mencari barang dengan ID terlebih dahulu dan bila dikosongkan, akan mencari barang dengan kode item di area yang dipilih.') }}
                  </p>
               </div>

               <div class="p-6 border border-neutral-200 dark:border-neutral-700 rounded-lg">
                  <div class="flex items-center space-x-2 mb-2">
                     <i class="fas fa-arrow-right-arrow-left text-neutral-500"></i>
                     <h2 class="font-bold text-xl">{{ __('Pilih tipe sirkulasi dan area') }}</h2>
                  </div>
                  <p class="leading-relaxed">
                     {{ __('Sirkulasi massal hanya bisa dilakukan pada satu tindakan (Tambah, Ambil, atau Catat) dan pada satu area saja dalam sekali operasi.') }}
                  </p>
               </div>

               <div class="flex items-center justify-end">
                  <x-primary-button type="button" x-on:click="$dispatch('close')">{{ __('Paham') }}</x-primary-button>
               </div>
            </div>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target="download"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target="download" class="hidden"></x-spinner>
         </x-modal>
      </div>
      <div 
      x-data="editorData()"
      x-init="editorInit()">
         <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6 mb-8">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100"><i class="fa fa-fw fa-arrow-right-arrow-left mr-3"></i>{{ __('Sirkulasi saja') }}</h1>
            <div class="flex gap-x-2">
               <div class="px-2 my-auto">
                  <span x-text="rowCount"></span><span class="">{{ ' ' . __('baris') }}</span>
               </div>
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
         <i class="fa fa-hand text-5xl mb-8 text-neutral-400 dark:text-neutral-600"></i>
         <div class="text-neutral-500">{{ __('Kamu tidak memiliki wewenang untuk membuat sirkulasi di area manapun.') }}</div>
      </div>
   @endif

</div>

@script
<script type="module">   
   Alpine.data('editorData', () => ({
         table: null,
         circs: @entangle('circs'),
         circsDefault: null,
         rowCount: 0,            
         
         editorInit() {
            const columns = [
               { title: 'item_id', field: 'item_id', width: 80 }, 
               { title: 'item_code', field: 'item_code', width: 110 }, 
               { title: 'curr', field: 'curr', width: 80},
               { title: 'qty_relative', field: 'qty_relative', width: 100 },
               { title: 'uom', field: 'uom', width: 80 },
               { title: 'remarks', field: 'remarks', width: 200 }, 
            ];
            
            this.circsDefault = this.circsDefault ? this.circsDefault : this.circs,

            // Initialize Tabulator
            this.table = new Tabulator("#editor-table", {
               
               data: this.circsDefault,
               layout: "fitColumns",
               columns: columns,
               height: "calc(100vh - 19rem)",

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
            this.circs = this.table.getData();
            $wire.apply();
         },

         editorReset() {
            Livewire.navigate("{{ route('inventory.circs.bulk-operation.index') }}");
         },

         editorDownload() {
            this.table.download("csv", "bulk_operation_circulations.csv"); 
         },
   }));
</script>
@endscript