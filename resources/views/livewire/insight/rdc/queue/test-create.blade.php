<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;

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
       $this->js('notyfError("' . __('Tidak ditemukan') . '")');
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
      $this->validate([
      'file' => 'file|mimes:txt,xls,xlsx|max:1024'
      ]);

      $mimeType = $this->file->getMimeType();

      $type = match ($mimeType) {
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
      'text/plain' => 'text'
      };

      switch ($type) {
      case 'excel':
         $this->extractDataExcel();
         $this->view = 'review';
         break;

      case 'text':
         $this->extractDataText();
         $this->view = 'review';
         break;

      default:
         $this->js('notyfError("' . __('Mime tidak didukung') . '")');
         break;
      }
      $this->reset(['file']);
   }

   private function find3Digit($string) {
      preg_match_all('/\/?(\d{3})/', $string, $matches);
      return !empty($matches[1]) ? end($matches[1]) : null;
   }

   private function extractDataText()
   {
      try {
         // Fetch the machine data based on the selected machine_id
         $machine = InsRdcMachine::find($this->test['ins_rdc_machine_id']);

         if (!$machine) {
            throw new \Exception("Mesin tidak ditemukan");
         }

         // Read the text file content
         $content = file_get_contents($this->file->getRealPath());
              
         // Split content into lines
         $lines = explode("\n", $content);
         
         // Initialize variables
         $values = [
            'code_alt' => '',
            'color' => '',
            'mcs' => '',
            's_max' => 0,
            's_min' => 0,
            'tc10' => 0,
            'tc50' => 0,
            'tc90' => 0,
            's_max_low' => 0,
            's_max_high' => 0,
            's_min_low' => 0,
            's_min_high' => 0,
            'tc10_low' => 0,
            'tc10_high' => 0,
            'tc50_low' => 0,
            'tc50_high' => 0,
            'tc90_low' => 0,
            'tc90_high' => 0,
         ];

         foreach ($lines as $line) {
            $line = trim($line);
            
            // Extract Orderno (code_alt)
            if (preg_match('/Orderno\.\s*:\s*(\d+)/i', $line, $matches)) {
               $values['code_alt'] = $matches[1];
            }
            
            // Extract Description (color) and MCS from Compound line
            elseif (strpos($line, 'Compound') !== false) {
               // Extract MCS (001)
               if (preg_match('/OG\/RS\s+(\d{3})/i', $line, $matches)) {
                  $values['mcs'] = $matches[1];
               }
               // Extract Description
               if (preg_match('/Description:\s*([^$]+)/i', $line, $matches)) {
                  $values['color'] = trim($matches[1]);
               }
            }
            
            // Extract ML (s_min) and its bounds
            elseif (strpos($line, 'ML') === 0) {
               if (preg_match('/ML\s+(\d+\.\d+).*?(\d+\.\d+)\s+(\d+\.\d+)/i', $line, $matches)) {
                  $values['s_min'] = floatval($matches[1]);
                  $values['s_min_low'] = floatval($matches[2]);
                  $values['s_min_high'] = floatval($matches[3]);
               }
            }
            
            // Extract MH (s_max) and its bounds
            elseif (strpos($line, 'MH') === 0) {
               if (preg_match('/MH\s+(\d+\.\d+).*?(\d+\.\d+)\s+(\d+\.\d+)/i', $line, $matches)) {
                  $values['s_max'] = floatval($matches[1]);
                  $values['s_max_low'] = floatval($matches[2]);
                  $values['s_max_high'] = floatval($matches[3]);
               }
            }
            
            // Extract t10 (tc10) and its bounds
            elseif (strpos($line, 't10') === 0) {
               if (preg_match('/t10\s+(\d+\.\d+).*?(\d+\.\d+)\s+(\d+\.\d+)/i', $line, $matches)) {
                  $values['tc10'] = floatval($matches[1]);
                  $values['tc10_low'] = floatval($matches[2]);
                  $values['tc10_high'] = floatval($matches[3]);
               }
            }
            
            // Extract t50 (tc50) and its bounds
            elseif (strpos($line, 't50') === 0) {
               if (preg_match('/t50\s+(\d+\.\d+).*?(\d+\.\d+)\s+(\d+\.\d+)/i', $line, $matches)) {
                  // If value and bounds exist in file, use them
                  $values['tc50'] = floatval($matches[1]);
                  $values['tc50_low'] = floatval($matches[2]);
                  $values['tc50_high'] = floatval($matches[3]);
               } elseif (preg_match('/t50\s+(\d+\.\d+)/i', $line, $matches)) {
                  // If only value exists, use it and set bounds to 0
                  $values['tc50'] = floatval($matches[1]);
                  $values['tc50_low'] = 0;
                  $values['tc50_high'] = 0;
               }
            }
            
            // Extract t90 (tc90) and its bounds
            elseif (strpos($line, 't90') === 0) {
               if (preg_match('/t90\s+(\d+\.\d+).*?(\d+\.\d+)\s+(\d+\.\d+)/i', $line, $matches)) {
                  $values['tc90'] = floatval($matches[1]);
                  $values['tc90_low'] = floatval($matches[2]);
                  $values['tc90_high'] = floatval($matches[3]);
               }
            }

            // Extract Status for evaluation
            elseif (preg_match('/Status:\s*(\w+)/i', $line, $matches)) {
               $status = strtolower($matches[1]); // Convert to lowercase
               $values['eval'] = ($status === 'pass') ? 'pass' : 'fail'; // Anything not 'pass' will be 'fail'
            }
         }

         // Assign extracted values
         $this->batch['code_alt'] = $values['code_alt'];
         $this->batch['color'] = $values['color'];
         $this->batch['mcs'] = $values['mcs'];

         // Assign test values
         $this->test['s_max'] = $values['s_max'];
         $this->test['s_min'] = $values['s_min'];
         $this->test['tc10'] = $values['tc10'];
         $this->test['tc50'] = $values['tc50'];
         $this->test['tc90'] = $values['tc90'];

         // Assign bounds
         $this->test['s_max_low'] = $values['s_max_low'];
         $this->test['s_max_high'] = $values['s_max_high'];
         $this->test['s_min_low'] = $values['s_min_low'];
         $this->test['s_min_high'] = $values['s_min_high'];
         $this->test['tc10_low'] = $values['tc10_low'];
         $this->test['tc10_high'] = $values['tc10_high'];
         $this->test['tc50_low'] = $values['tc50_low'];
         $this->test['tc50_high'] = $values['tc50_high'];
         $this->test['tc90_low'] = $values['tc90_low'];
         $this->test['tc90_high'] = $values['tc90_high'];

         // Set evaluation from status
         $this->test['eval'] = $values['eval'] ?? 'fail'; // Default to fail if status not found

         // Debug output
         $this->js('console.log("TC50 Value:", ' . $values['tc50'] . ')');
         $this->js('console.log("TC50 Bounds:", ' . $values['tc50_low'] . ' - ' . $values['tc50_high'] . ')');
         $this->js('console.log("Status found:", "' . $this->test['eval'] . '")');

      } catch (\Exception $e) {
         $this->js('notyfError("' . __('Terjadi galat ketika memproses berkas. Periksa console') . '")'); 
         $this->js('console.log("Error: '. $e->getMessage() .'")');
      }
   }

   private function extractDataExcel()
   {
      try {
         // Fetch the machine data based on the selected machine_id
         $machine = InsRdcMachine::find($this->test['ins_rdc_machine_id']);

         if (!$machine) {
            throw new \Exception("Mesin tidak ditemukan");
         }

         $cellsConfig = json_decode($machine->cells, true);

         $path = $this->file->getRealPath();
         $spreadsheet = IOFactory::load($path);
         $worksheet = $spreadsheet->getActiveSheet();

         foreach ($cellsConfig as $config) {
            $field = $config['field'];
            $address = $config['address'];
            $value = $worksheet->getCell($address)->getValue();

            switch ($field) {
               case 'model':
               case 'color':
               case 'code_alt':
                  $this->batch[$field] = $this->safeString($value);
                  break;
               case 'mcs':
                  $this->batch['mcs'] = $this->find3Digit($this->safeString($value));
                  break;
               case 's_max':
               case 's_min':
               case 'tc10':
               case 'tc50':
               case 'tc90':
                  $this->test[$field] = $this->safeFloat($value);
                  break;
               case 's_max_low':
               case 's_min_low':
               case 'tc10_low':
               case 'tc50_low':
               case 'tc90_low':
                  $this->test[$field] = $this->getBoundFromString($value, 'low');
                  break;
               case 's_max_high':
               case 's_min_high':
               case 'tc10_high':
               case 'tc50_high':
               case 'tc90_high':
                  $this->test[$field] = $this->getBoundFromString($value, 'high');
                  break;
               case 'eval':
                  $eval = $this->safeString($value);
                  $this->test['eval'] = ($eval == 'OK' ? 'pass' : ($eval == 'SL' ? 'fail' : ''));
                  break;
            }
         }

      } catch (\Exception $e) {
            $this->js('notyfError("' . __('Terjadi galat ketika memproses berkas. Periksa console') . '")'); 
            $this->js('console.log("'. $e->getMessage() .'")');
      }
   }

   private function safeString($value): string
   {
      return trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $value));
   }

   private function safeFloat($value): ?float
   {
      return is_numeric($value) ? (float)$value : 0;
   }

   public function getBoundFromString(string $range, string $type = 'low'): ?float
   {
       // Validate the input
       if (empty($range) || !str_contains($range, '-')) {
           return 0; 
       }

       // Split the string into parts
       [$part1, $part2] = explode('-', $range, 2);

       // Convert to floats using safeFloat
       $value1 = $this->safeFloat($part1);
       $value2 = $this->safeFloat($part2);

       // Determine which is lower and which is higher
       $lower = min($value1, $value2);
       $higher = max($value1, $value2);

       // Return based on requested type
       return $type === 'high' ? $higher : $lower;
   }

   public function removeFromQueue()
   {
      $batch = InsRubberBatch::find($this->batch['id']);

      if ($batch) {
         $batch->update([ 'rdc_queue' => 0 ]);
         $this->js('notyfSuccess("' . __('Dihapus dari antrian') . '")'); 
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
      $this->js('notyfSuccess("' . __('Hasil uji disimpan') . '")');
      $this->dispatch('updated');
      $this->customReset();
   }
}

?>

<div>
   <div class="flex justify-between items-start p-6">
      <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
         {{ $batch['code'] }}
      </h2>
      <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
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
         <!-- <div class="px-3 my-6">
            <x-toggle name="update_batch" wire:model.live="update_batch" :checked="$update_batch ? true : false" >{{ __('Perbarui') }}</x-toggle>
         </div> -->
      </div>
      <div class="col-span-4 px-6">
         <div class="flex gap-3" x-data="{ machine_id: @entangle('test.ins_rdc_machine_id') }">
            <div class="grow">
               <x-select class="w-full" id="test-machine_id" x-model="machine_id">
                  <option value=""></option>
                  @foreach($machines as $machine)
                     <option value="{{ $machine['id'] }}">{{ $machine['number'] . ' - ' . $machine['name'] }}</option>
                  @endforeach
               </x-select>
            </div>
            <div>
               <input wire:model="file" type="file" class="hidden" x-cloak x-ref="file" />
               <x-secondary-button x-show="!machine_id" disabled type="button" id="test-file" class="w-full h-full justify-center"><i
               class="fa fa-upload mr-2"></i>{{ __('Unggah') }}</x-secondary-button>
               <x-secondary-button x-show="machine_id" type="button" id="test-file" class="w-full h-full justify-center" x-on:click="$refs.file.click()" ><i
               class="fa fa-upload mr-2"></i>{{ __('Unggah') }}</x-secondary-button>
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
            <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
         </x-slot>
         <x-slot name="content">
            <x-dropdown-link href="#" wire:click.prevent="customResetBatch">
               {{ __('Reset') }}
            </x-dropdown-link>
            <hr class="border-neutral-300 dark:border-neutral-600 {{ true ? '' : 'hidden' }}" />
            <x-dropdown-link href="#" wire:click.prevent="removeFromQueue"
               class="{{ true ? '' : 'hidden' }}">
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