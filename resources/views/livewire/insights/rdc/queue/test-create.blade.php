<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

use App\Models\InsRubberBatch;
use App\Models\InsRdcMachine;
use App\Models\InsRdcTest;

use PhpOffice\PhpSpreadsheet\IOFactory;

new class extends Component
{
   use WithFileUploads;

   public $file;

   public array $machines = [];

   public array $batch = [
      'id' => '',
      'code' => '',
      'code_alt' => '',
      'model' => '',
      'color' => '',
      'mcs' => '',
   ];
   
   public bool $update_batch = true;  

   public array $test = [
      'ins_rdc_machine_id' => 0,
      's_max_low' => '',
      's_max_high' => '',
      's_min_low' => '',
      's_min_high' => '',
      'tc10_low' => '',
      'tc10_high' => '',
      'tc50_low' => '',
      'tc50_high' => '',
      'tc90_low' => '',
      'tc90_high' => '',
      'type' => '',
      's_max' => '',
      's_min' => '',
      'tc10' => '',
      'tc50' => '',
      'tc90' => '',
      'eval' => '',
   ];

   public array $shoe_models = [];

   public function mount()
   {
      $this->batch['code'] = __('Kode batch');
      $this->machines = InsRdcMachine::all()->toArray();
   }

   #[On('test-create')]
   public function loadBatch($id)
   {
      $this->customReset();

      $batch = InsRubberBatch::find($id);

      if ($batch) {
         $this->batch['id'] = $batch->id;
         $this->batch['code'] = $batch->code;
         $this->batch['code_alt'] = $batch->code_alt;
         $this->batch['model'] = $batch->model;
         $this->batch['color'] = $batch->color;
         $this->batch['mcs'] = $batch->mcs;
      } else {
         $this->handleNotFound();
      }
   }

   private function customReset()
   {
      $this->resetErrorBag();
      $this->reset(['file', 'batch', 'test']);
      $this->batch['code'] = __('Kode batch');
   }

   public function customResetBatch()
   {
      $id = $this->batch['id'];
      $this->customReset();
      $this->loadBatch($id);
   }

   public function handleNotFound()
   {
       $this->js('$dispatch("close")');
       $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
       $this->dispatch('updated');
   }

   public function rules()
   {
      return [
         'batch.code_alt'     => 'nullable|string|max:50',
         'batch.model'        => 'nullable|string|max:30',
         'batch.color'        => 'nullable|string|max:20',
         'batch.mcs'          => 'nullable|string|max:10',

         'test.ins_rdc_machine_id' => 'required|exists:ins_rdc_machines,id',

         'test.s_min_low'    => 'required|numeric|gte:0|lte:99',
         'test.s_min_high'   => 'required|numeric|gte:0|lte:99',
         'test.s_max_low'    => 'required|numeric|gte:0|lte:99',
         'test.s_max_high'   => 'required|numeric|gte:0|lte:99',

         'test.tc10_low'     => 'required|numeric|gte:0|lte:999',
         'test.tc10_high'    => 'required|numeric|gte:0|lte:999',
         'test.tc50_low'     => 'required|numeric|gte:0|lte:999',
         'test.tc50_high'    => 'required|numeric|gte:0|lte:999',
         'test.tc90_low'     => 'required|numeric|gte:0|lte:999',
         'test.tc90_high'    => 'required|numeric|gte:0|lte:999',
         
         'test.type'         => 'required|in:-,slow,fast',
         'test.s_max'        => 'required|numeric|gte:0|lte:99',
         'test.s_min'        => 'required|numeric|gte:0|lte:99',
         'test.tc10'         => 'required|numeric|gte:0|lte:999',
         'test.tc50'         => 'required|numeric|gte:0|lte:999',
         'test.tc90'         => 'required|numeric|gte:0|lte:999',
         'test.eval'         => 'required|in:pass,fail',
      ];
   }

   public function updatedFile()
   {
      // Validate file first
      $this->validate([
         'file' => 'file|max:1024'
      ]);

      // Get the selected machine
      $machine = InsRdcMachine::find($this->test['ins_rdc_machine_id']);
      if (!$machine instanceof InsRdcMachine) {
         $this->js('toast("' . __('Pilih mesin terlebih dahulu') . '", { type: "danger" })');
         $this->reset(['file']);
         return;
      }

      // Validate file type against machine type
      $mimeType = $this->file->getMimeType();
      $isValidFile = $this->validateFileForMachine($mimeType, $machine->type);

      if (!$isValidFile) {
         $expectedTypes = $machine->type === 'excel' ? 'Excel (.xls, .xlsx)' : 'Text (.txt)';
         $this->js('toast("' . __('File tidak sesuai dengan tipe mesin. Diharapkan: ') . $expectedTypes . '", { type: "danger" })');
         $this->reset(['file']);
         return;
      }

      // Process the file based on machine type
      try {
         if ($machine->type === 'excel') {
            $this->extractDataExcel($machine);
         } else {
            $this->extractDataText($machine);
         }
      } catch (\Exception $e) {
         $this->js('toast("' . __('Gagal memproses file: ') . $e->getMessage() . '", { type: "danger" })');
      }

      $this->reset(['file']);
   }

   private function validateFileForMachine(string $mimeType, string $machineType): bool
   {
      return match ($machineType) {
         'excel' => in_array($mimeType, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
         ]),
         'txt' => $mimeType === 'text/plain',
         default => false
      };
   }

   private function extractDataExcel(InsRdcMachine $machine)
   {
      $config = json_decode($machine->cells, true) ?? [];
      if (empty($config)) {
         throw new \Exception("Mesin tidak memiliki konfigurasi");
      }

      $path = $this->file->getRealPath();
      $spreadsheet = IOFactory::load($path);
      $worksheet = $spreadsheet->getActiveSheet();

      $extractedData = [];

      foreach ($config as $fieldConfig) {
         if (!isset($fieldConfig['field']) || !isset($fieldConfig['address'])) {
            continue;
         }

         $field = $fieldConfig['field'];
         $address = $fieldConfig['address'];
         
         try {
            $value = $worksheet->getCell($address)->getValue();
            $extractedData[$field] = $this->processFieldValue($field, $value);
         } catch (\Exception $e) {
            // Skip if cell doesn't exist or can't be read
            continue;
         }
      }

      $this->applyExtractedData($extractedData);
   }

   private function extractDataText(InsRdcMachine $machine)
   {
      $config = json_decode($machine->cells, true) ?? [];
      if (empty($config)) {
         throw new \Exception("Mesin tidak memiliki konfigurasi");
      }

      $content = file_get_contents($this->file->getRealPath());
      $lines = explode("\n", $content);

      $extractedData = [];

      foreach ($config as $fieldConfig) {
         if (!isset($fieldConfig['field']) || !isset($fieldConfig['pattern'])) {
            continue;
         }

         $field = $fieldConfig['field'];
         $pattern = $fieldConfig['pattern'];

         $value = $this->extractValueFromText($lines, $pattern);
         if ($value !== null) {
            $extractedData[$field] = $this->processFieldValue($field, $value);
         }
      }

      $this->applyExtractedData($extractedData);
   }

   private function extractValueFromText(array $lines, string $pattern): ?string
   {
      foreach ($lines as $line) {
         $line = trim($line);
         if (preg_match('/' . $pattern . '/i', $line, $matches)) {
            // Return the first capture group if it exists, otherwise the full match
            return isset($matches[1]) ? $matches[1] : $matches[0];
         }
      }
      return null;
   }

   private function processFieldValue(string $field, $value): mixed
   {
      $value = trim((string)$value);

      return match($field) {
         'mcs' => $this->find3Digit($value),
         'color', 'code_alt', 'model' => $this->safeString($value),
         's_max', 's_min', 'tc10', 'tc50', 'tc90' => $this->safeFloat($value),
         's_max_low', 's_max_high', 's_min_low', 's_min_high',
         'tc10_low', 'tc10_high', 'tc50_low', 'tc50_high',
         'tc90_low', 'tc90_high' => $this->safeFloat($value),
         'eval' => $this->processEvalValue($value),
         default => $value
      };
   }

   private function processEvalValue(string $value): string
   {
      $value = strtolower(trim($value));
      return match($value) {
         'ok', 'pass' => 'pass',
         'sl', 'fail' => 'fail',
         default => ''
      };
   }

   private function applyExtractedData(array $extractedData)
   {
      // Apply batch data
      $batchFields = ['code_alt', 'model', 'color', 'mcs'];
      foreach ($batchFields as $field) {
         if (isset($extractedData[$field]) && !empty($extractedData[$field])) {
            $this->batch[$field] = $extractedData[$field];
         }
      }

      // Apply test data
      $testFields = [
         's_max', 's_min', 'tc10', 'tc50', 'tc90', 'eval',
         's_max_low', 's_max_high', 's_min_low', 's_min_high',
         'tc10_low', 'tc10_high', 'tc50_low', 'tc50_high',
         'tc90_low', 'tc90_high'
      ];
      
      foreach ($testFields as $field) {
         if (isset($extractedData[$field])) {
            $this->test[$field] = $extractedData[$field];
         }
      }
   }

   private function find3Digit($string): ?string
   {
      preg_match_all('/\/?(\d{3})/', (string)$string, $matches);
      return !empty($matches[1]) ? end($matches[1]) : null;
   }

   private function safeString($value): string
   {
      return trim(preg_replace('/[^a-zA-Z0-9\s]/', '', (string)$value));
   }

   private function safeFloat($value): ?float
   {
      return is_numeric($value) ? (float)$value : 0;
   }

   public function getBoundFromString(string $range, string $type = 'low'): ?float
   {
       if (empty($range) || !str_contains($range, '-')) {
           return 0; 
       }

       [$part1, $part2] = explode('-', $range, 2);

       $value1 = $this->safeFloat($part1);
       $value2 = $this->safeFloat($part2);

       $lower = min($value1, $value2);
       $higher = max($value1, $value2);

       return $type === 'high' ? $higher : $lower;
   }

   public function removeFromQueue()
   {
      $batch = InsRubberBatch::find($this->batch['id']);

      if ($batch) {
         $batch->update([ 'rdc_queue' => 0 ]);
         $this->js('toast("' . __('Dihapus dari antrian') . '", { type: "success" })'); 
         $this->js('$dispatch("close")');
         $this->dispatch('updated');
      } else {
         $this->handleNotFound();
      }
      $this->customReset();
   }

   public function save()
   {
      $test = new InsRdcTest;
      Gate::authorize('manage', $test);

      $this->validate();

      $batch = InsRubberBatch::find($this->batch['id']);

      if (!$batch) {
         $this->customReset();
         $this->handleNotFound();
         return;
      }

      foreach ($this->batch as $key => $value) {
         if (in_array($key, ['id','code'])) {
            continue;
         }

         if ($value) {
            $value = trim($value);
            $batch->$key = $value;
         }
      }
      
      $batch->rdc_queue = 0;
      $test->queued_at = $batch->updated_at;
      $batch->save();
      
      foreach ($this->test as $key => $value) {
         $test->$key = $value;
      }

      $test->user_id = Auth::user()->id;
      $test->ins_rubber_batch_id = $batch->id;
      $test->save();

      $this->js('$dispatch("close")');
      $this->js('toast("' . __('Hasil uji disimpan') . '", { type: "success" })');
      $this->dispatch('updated');
      $this->customReset();
   }

   public function getSelectedMachine()
   {
      return collect($this->machines)->firstWhere('id', $this->test['ins_rdc_machine_id']);
   }
}

?>

<div>
   <div class="flex justify-between items-start p-6">
      <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
         {{ $batch['code'] }}
      </h2>
      <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
   </div>
   <div class="grid grid-cols-6">
      <div class="col-span-2 px-6 bg-caldy-500 bg-opacity-10 rounded-r-xl">
         <div class="mt-6">
            <x-pill class="uppercase">{{ __('Batch') }}</x-pill>     
         </div>   
         <div class="mt-6">
            <label for="test-code_alt"
               class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode alt.') }}</label>
            <x-text-input id="test-code_alt" wire:model="batch.code_alt" type="text" />
         </div>
         <div class="mt-6">
            <label for="test-model"
               class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Model') }}</label>
            <x-text-input id="test-model" list="test-models" wire:model="batch.model" type="text" />
            <datalist id="test-models">
               @foreach ($shoe_models as $shoe_model)
                     <option value="{{ $shoe_model }}">
               @endforeach
            </datalist>
         </div>
         <div class="mt-6">
            <label for="test-color"
               class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Warna') }}</label>
            <x-text-input id="test-color" wire:model="batch.color" type="text" />
         </div>
         <div class="mt-6">
            <label for="test-mcs"
               class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('MCS') }}</label>
            <x-text-input id="test-mcs" wire:model="batch.mcs" type="text" />
         </div>
      </div>
      <div class="col-span-4 px-6">
         <div class="flex gap-3" x-data="{ 
            machine_id: @entangle('test.ins_rdc_machine_id'),
            selectedMachine: null,
            machines: @entangle('machines'),
            updateSelectedMachine() {
               this.selectedMachine = this.machines.find(m => m.id == this.machine_id);
            }
         }" x-init="updateSelectedMachine()" @change="updateSelectedMachine()">
            <div class="grow">
               <x-select class="w-full" id="test-machine_id" x-model="machine_id">
                  <option value="">{{ __('Pilih mesin') }}</option>
                  @foreach($machines as $machine)
                     <option value="{{ $machine['id'] }}">
                        {{ $machine['number'] . ' - ' . $machine['name'] }}
                     </option>
                  @endforeach
               </x-select>
            </div>
            <div x-cloak x-show="machine_id">
               <input wire:model="file" type="file" class="hidden" x-cloak x-ref="file" 
                      x-bind:accept="selectedMachine?.type === 'excel' ? '.xls,.xlsx' : selectedMachine?.type === 'txt' ? '.txt' : ''" />
               <x-secondary-button  type="button" class="w-full h-full justify-center" 
                                   x-on:click="$refs.file.click()">
                  <i class="icon-upload mr-2"></i>
                  <span x-show="!selectedMachine?.type">?</span>
                  <span x-show="selectedMachine?.type === 'excel'">XLS</span>
                  <span x-show="selectedMachine?.type === 'txt'">TXT</span>
               </x-secondary-button>
            </div>
         </div>
         
         <div class="my-6">
            <x-pill class="uppercase">{{ __('Standar') }}</x-pill>     
         </div>    
         <div class="grid grid-cols-1 sm:grid-cols-3 mt-6">     
            <div>
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S maks') }}</label>
               <div class="flex w-full items-center">
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.s_max_low" />
                  </div>
                  <div>-</div>
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.s_max_high" />
                  </div>
               </div>
            </div>
            <div>
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S min') }}</label>
               <div class="flex w-full items-center">
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.s_min_low" />
                  </div>
                  <div>-</div>
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.s_min_high" />
                  </div>
               </div>
            </div>
            <div>
               <label for="test-type"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tipe') }}</label>
               <x-select class="w-full uppercase" id="test-type" wire:model="test.type">
                  <option value=""></option>
                  <option value="-">-</option>
                  <option value="slow">SLOW</option>
                  <option value="fast">FAST</option>
               </x-select>
            </div>
         </div>
         <div class="grid grid-cols-1 sm:grid-cols-3 mt-6">
            <div>
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC10') }}</label>
               <div class="flex w-full items-center">
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.tc10_low" />
                  </div>
                  <div>-</div>
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.tc10_high" />
                  </div>
               </div>
            </div>
            <div>
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC50') }}</label>
               <div class="flex w-full items-center">
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.tc50_low" />
                  </div>
                  <div>-</div>
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.tc50_high" />
                  </div>
               </div>
            </div>
            <div>
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC90') }}</label>
               <div class="flex w-full items-center">
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.tc90_low" />
                  </div>
                  <div>-</div>
                  <div class="grow">
                     <x-text-input-t class="text-center" wire:model="test.tc90_high" />
                  </div>
               </div>
            </div>
         </div>
         <div class="my-6">
            <x-pill class="uppercase">{{ __('Hasil') }}</x-pill>     
         </div>       
         <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3 mt-6">
            <div>
               <label for="test-s_max"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S maks') }}</label>
               <x-text-input id="test-s_max" wire:model="test.s_max" type="number" step=".01" />
            </div>
            <div>
               <label for="test-s_min"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S Min') }}</label>
               <x-text-input id="test-s_min" wire:model="test.s_min" type="number" step=".01" />
            </div>
            <div>
               <label for="test-eval"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Evaluasi') }}</label>
               <x-select class="w-full" id="test-eval" wire:model="test.eval">
                  <option value=""></option>
                  <option value="pass">{{ __('PASS') }}</option>
                  <option value="fail">{{ __('FAIL') }}</option>
               </x-select>
            </div>
         </div>
         <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3 mt-6">
            <div>
               <label for="test-tc10"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC10') }}</label>
               <x-text-input id="test-tc10" wire:model="test.tc10" type="number" step=".01" />
            </div>
            <div>
               <label for="test-tc50"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC50') }}</label>
               <x-text-input id="test-tc50" wire:model="test.tc50" type="number" step=".01" />
            </div>
            <div>
               <label for="test-tc90"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC90') }}</label>
               <x-text-input id="test-tc90" wire:model="test.tc90" type="number" step=".01" />
            </div>
         </div>
      </div>
   </div>
   @if ($errors->any())
      <div class="px-6 mt-6">
          <x-input-error :messages="$errors->first()" />
      </div>
   @endif
   <div class="p-6 flex justify-between items-center gap-3">
      <x-dropdown align="left" width="48">
         <x-slot name="trigger">
            <x-text-button><i class="icon-ellipsis-vertical"></i></x-text-button>
         </x-slot>
         <x-slot name="content">
            <x-dropdown-link href="#" wire:click.prevent="customResetBatch">
               {{ __('Reset') }}
            </x-dropdown-link>
            <hr class="border-neutral-300 dark:border-neutral-600" />
            <x-dropdown-link href="#" wire:click.prevent="removeFromQueue">
               {{ __('Hapus dari antrian') }}
            </x-dropdown-link>
         </x-slot>
      </x-dropdown>
      <x-primary-button type="button" wire:click="save">
         {{ __('Simpan') }}
      </x-primary-button>
   </div>
   <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>