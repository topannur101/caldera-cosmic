<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\ShMod;
use App\Models\InsRdcTest;
use App\Models\InsRdcMachine;
use App\Models\InsRdcTag;
use App\Models\InsRubberBatch;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

new class extends Component {

    use WithFileUploads;

    public int $id;
    public string $updated_at;
    public string $code;
    
    public string $model = '';
    public string $color = '';
    public string $mcs = '';
    public string $code_alt = '';
    
    public string $e_model = '';
    public string $e_color = '';
    public string $e_mcs = '';
    public string $e_code_alt = '';
    
    public string $o_model = '';
    public string $o_color = '';
    public string $o_mcs = '';
    public string $o_code_alt = '';
    
    public string $tag = '';
    public float $s_max = 0;
    public float $s_min = 0;
    public string $eval = '';
    public float $tc10 = 0;
    public float $tc50 = 0;
    public float $tc90 = 0;

    public int $machine_id = 0;
    public bool $update_batch = false;
    public $sh_mods;
    public $ins_rdc_tags;

    public $file;
    public string $view = 'upload';

    public function mount()
    {
        $this->sh_mods = ShMod::where('is_active', true)->orderBy('updated_at', 'desc')->get();
        $this->ins_rdc_tags = InsRdcTag::orderBy('name')->get();
    }

    #[On('batch-test-create')]
    public function loadBatch(int $id)
    {
        $batch = InsRubberBatch::find($id);

        if ($batch) {
            $this->id = $batch->id;
            $this->updated_at = $batch->updated_at ?? '';
            $this->code = $batch->code;

            $this->model        = $batch->model ?? '';
            $this->color        = $batch->color ?? '';
            $this->mcs          = $batch->mcs ?? '';
            $this->code_alt     = $batch->code_alt ?? '';

            $this->o_model      = $batch->model ?? '';
            $this->o_color      = $batch->color ?? '';
            $this->o_mcs        = $batch->mcs ?? '';
            $this->o_code_alt   = $batch->code_alt ?? '';

            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }

        $this->customReset();
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
                $this->js('toast("' . __('Mime tidak didukung') . '", { type: "danger" })');
                break;
        }

    }

    public function updatedUpdateBatch()
    {
        $this->updateBatchInfo();
    }

    private function find3Digit($string) {
        preg_match_all('/\/?(\d{3})/', $string, $matches);
        return !empty($matches[1]) ? end($matches[1]) : null;
    }

    private function extractDataText()
    {

        try {
            // Fetch the machine data based on the selected machine_id
            $machine = InsRdcMachine::find($this->machine_id);

            if (!$machine) {
                throw new \Exception("Mesin tidak ditemukan");
            }

            // Read the text file content
            $content = file_get_contents($this->file->getRealPath());
            
            // Split content into lines
            $lines = explode("\n", $content);
            
            // Initialize variables to store extracted values
            $values = [
                'model' => '',
                'color' => '',
                'mcs' => '',
                'code_alt' => '',
                's_max' => 0,
                's_min' => 0,
                'tc10' => 0,
                'tc50' => 0,
                'tc90' => 0,
                'eval' => ''
            ];

            // Process each line to extract data
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Debug line yang sedang diproses
                $this->js('console.log("Processing line: '. addslashes($line) .'")');
                
                // Extract MCS dari baris yang mengandung "Compound"
                if (strpos($line, 'Compound') !== false) {
                    // Extract MCS
                    if (preg_match('/OG\/RS\s+(\d{3})/i', $line, $matches)) {
                        $values['mcs'] = $matches[1];
                        $this->js('console.log("Found MCS in Compound line: '. $matches[1] .'")');
                    }
                    
                    // Extract Description (Warna) dari baris yang sama
                    if (preg_match('/Description:\s*([^$]+)/i', $line, $matches)) {
                        $values['color'] = trim($matches[1]);
                        $this->js('console.log("Found Color in Description: '. $matches[1] .'")');
                    }
                }
                // Extract Orderno as code_alt
                elseif (preg_match('/Orderno\.:?\s*(\d+)/i', $line, $matches)) {
                    $values['code_alt'] = $this->safeString($matches[1]);
                }
                // Extract Description as color
                elseif (preg_match('/Description:\s*(.+)$/i', $line, $matches)) {
                    $values['color'] = $this->safeString($matches[1]);
                }
                // Extract ML as s_min
                elseif (preg_match('/^ML\s+(\d+\.\d+)/i', $line, $matches)) {
                    $values['s_min'] = $this->safeFloat($matches[1]);
                }
                // Extract MH as s_max
                elseif (preg_match('/^MH\s+(\d+\.\d+)/i', $line, $matches)) {
                    $values['s_max'] = $this->safeFloat($matches[1]);
                }
                // Extract t10 as tc10
                elseif (preg_match('/^t10\s+(\d+\.\d+)/i', $line, $matches)) {
                    $values['tc10'] = $this->safeFloat($matches[1]);
                }
                // Extract t50 as tc50
                elseif (preg_match('/^t50\s+(\d+\.\d+)/i', $line, $matches)) {
                    $values['tc50'] = $this->safeFloat($matches[1]);
                }
                // Extract t90 as tc90
                elseif (preg_match('/^t90\s+(\d+\.\d+)/i', $line, $matches)) {
                    $values['tc90'] = $this->safeFloat($matches[1]);
                }
                // Extract status for eval
                elseif (preg_match('/Status:\s*Pass/i', $line)) {
                    $values['eval'] = 'pass';
                }
                elseif (preg_match('/Status:\s*Fail/i', $line)) {
                    $values['eval'] = 'fail';
                }
            }

            // Debug final values
            $this->js('console.log("Before assignment - Color value:", "' . ($values['color'] ?? 'not set') . '")');

            // Assign extracted values to component properties
            $this->e_model = $values['model'];
            $this->e_color = $values['color'] ?? '';  // Menggunakan nilai dari Description
            $this->e_mcs = $values['mcs'] ?? '';
            $this->e_code_alt = $values['code_alt'];
            $this->s_max = $values['s_max'];
            $this->s_min = $values['s_min'];
            $this->tc10 = $values['tc10'];
            $this->tc50 = $values['tc50'];
            $this->tc90 = $values['tc90'];
            $this->eval = $values['eval'];

            // Debug after assignment
            $this->js('console.log("After assignment - Color value:", "' . $this->e_color . '")');

            // Check if batch info should be updated
            if((!$this->model && !$this->color && !$this->mcs && !$this->code_alt) && 
               ($this->e_model || $this->e_color || $this->e_mcs || $this->e_code_alt)) 
            {
                $this->update_batch = true;
                $this->updateBatchInfo();
            }

            $this->view = 'review';

        } catch (\Exception $e) {
            $this->js('toast("' . __('Tidak dapat membaca file') . '", { description: "'. $e->getMessage() .'", type: "danger" })'); 
        }

    }

    private function extractDataExcel()
    {
        try {
            // Fetch the machine data based on the selected machine_id
            $machine = InsRdcMachine::find($this->machine_id);

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
                        $this->{"e_$field"} = $this->safeString($value);
                        break;
                    case 'mcs':
                        $this->e_mcs = $this->find3Digit($this->safeString($value));
                        break;
                    case 's_max':
                    case 's_min':
                    case 'tc10':
                    case 'tc50':
                    case 'tc90':
                        $this->$field = $this->safeFloat($value);
                        break;
                    case 'eval':
                        $eval = $this->safeString($value);
                        $this->eval = ($eval == 'OK' ? 'pass' : ($eval == 'SL' ? 'fail' : ''));
                        break;
                }
            }

            if((!$this->model && !$this->color && !$this->mcs && !$this->code_alt) && ($this->e_model || $this->e_color || $this->e_mcs || $this->e_code_alt)) 
            {
                $this->update_batch = true;
                $this->updateBatchInfo();
            }

        } catch (\Exception $e) {
            $this->js('toast("' . __('Tidak dapat membaca file') . '", { description: "'. $e->getMessage() .'", type: "danger" })'); 
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

    public function removeFromQueue()
    {
        $batch = InsRubberBatch::find($this->id);

        if ($batch) {
            $batch->update([
                'rdc_queue' => 0
            ]);
            $this->js('toast("' . __('Dihapus dari antrian') . '", { type: "success" })'); 
            $this->js('$dispatch("close")');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
        }
        $this->customReset();
    }

    private function updateBatchInfo()
    {
        if ($this->update_batch) {
            // Use extracted values if available, otherwise keep current value
            $this->model    = $this->e_model ?: $this->model;
            $this->color    = $this->e_color ?: $this->color;
            $this->mcs      = $this->e_mcs ?: $this->mcs;
            $this->code_alt = $this->e_code_alt ?: $this->code_alt;
        } else {
            // Revert to original values
            $this->model    = $this->o_model;
            $this->color    = $this->o_color;
            $this->mcs      = $this->o_mcs;
            $this->code_alt = $this->o_code_alt; 
        }
    }

    public function insertTest()
    {
        $batch = InsRubberBatch::find($this->id);

        if ($batch) {
            $test = new InsRdcTest;
            Gate::authorize('manage', $test);
        
            $batchValidator = Validator::make(
                [
                    'model'      => $this->model,
                    'color'      => $this->color,
                    'mcs'        => $this->mcs,
                    'code_alt'   => $this->code_alt,
                ],
                [
                    'model'      => 'required|exists:sh_mods,name',
                    'color'      => 'nullable|string|max:20',
                    'mcs'        => 'nullable|string|max:10',
                    'code_alt'   => 'nullable|string|max:50',
                ]
            );

            $testValidator = Validator::make(
                [
                    'eval'       => $this->eval,
                    's_max'      => $this->s_max,
                    's_min'      => $this->s_min,
                    'tc10'       => $this->tc10,
                    'tc50'       => $this->tc50,
                    'tc90'       => $this->tc90,
                    'machine_id' => $this->machine_id,
                    'tag'        => $this->tag,
                ],
                [
                    'eval'       => ['required', 'in:pass,fail'],
                    's_max'      => ['required', 'numeric', 'gt:0', 'lt:99'],
                    's_min'      => ['required', 'numeric', 'gt:0', 'lt:99'],
                    'tc10'       => ['required', 'numeric', 'gt:0', 'lt:999'],
                    'tc50'       => ['required', 'numeric', 'gt:0', 'lt:999'],
                    'tc90'       => ['required', 'numeric', 'gt:0', 'lt:999'],
                    'machine_id' => ['required', 'exists:ins_rdc_machines,id'],
                    'tag'        => ['nullable', 'exists:ins_rdc_tags,name'],
                ]
            );

            if ($batchValidator->fails() && $this->update_batch) {
                $batchError = $batchValidator->errors()->first();
                $this->js('toast("'.$batchError.'", { type: "danger" })'); 

            } elseif ($testValidator->fails()) {
                $testError = $testValidator->errors()->first();
                $this->js('toast("'.$testError.'", { type: "danger" })'); 
            } else {

                $queued_at = $batch->updated_at;

                if($this->update_batch) {
                    $batch->update([
                        'model'     => $this->model,
                        'color'     => $this->color,
                        'mcs'       => $this->mcs,
                        'code_alt'  => $this->code_alt
                    ]);
                }

                $test->fill([
                    's_max'                 => $this->s_max,
                    's_min'                 => $this->s_min,
                    'eval'                  => $this->eval,
                    'tc10'                  => $this->tc10,
                    'tc50'                  => $this->tc50,
                    'tc90'                  => $this->tc90,
                    'user_id'               => Auth::user()->id,
                    'ins_rubber_batch_id'   => $batch->id,
                    'ins_rdc_machine_id'    => $this->machine_id,
                    'queued_at'             => $queued_at,
                    'tag'                   => $this->tag,
                ]);

                $test->save();

                $batch->update([
                    'rdc_queue' => 0
                ]);

                $this->js('$dispatch("close")');
                $this->js('toast("' . __('Hasil uji disisipkan') . '", { type: "success" })');
                $this->dispatch('updated');

                $this->customReset();
            }

        } else {
            $this->handleNotFound();
        }
      
    }

    public function with(): array
    {
        $machines = InsRdcMachine::orderBy('number')->get();
        return [
            'machines' => InsRdcMachine::orderBy('number')->get()
        ];
    }

    public function customReset()
    {
        $this->resetValidation();
        $this->reset(['file','s_max', 's_min', 'eval', 'tc10', 'tc50', 'tc90', 'model', 'color', 'mcs', 'code_alt', 'e_model', 'e_color', 'e_mcs' , 'e_code_alt', 'o_model', 'o_color', 'o_mcs', 'o_code_alt', 'update_batch', 'machine_id', 'tag', 'view']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }
};

?>
<div>
    <div x-data="{ dropping: false, machine_id: @entangle('machine_id') }" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Sisipkan hasil uji') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        @switch($view)
            @case('upload')
                <div class="relative" x-on:dragover.prevent="machine_id ? dropping = true : dropping = false">
                    <div wire:loading.class="hidden"
                        class="absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white/80 dark:bg-neutral-800/80 py-3"
                        x-cloak x-show="dropping">
                        <div
                            class="flex justify-around items-center w-full h-full border-dashed border-2 border-neutral-500  text-neutral-500 dark:text-neutral-400 rounded-lg">
                            <div class="text-center">
                                <div class="text-4xl mb-3">
                                    <i class="icon-upload"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input wire:model="file" type="file"
                            class="absolute inset-0 m-0 p-0 w-full h-full outline-none opacity-0" x-cloak x-ref="file"
                            x-show="dropping" x-on:dragleave.prevent="dropping = false" x-on:drop="dropping = false" />
                    <div class="flex flex-col items-center justify-center gap-y-3 h-48">
                        <x-select class="w-full" id="test-machine_id" x-model="machine_id" :disabled="$file">
                            <option value=""></option>
                            @foreach($machines as $machine)
                                <option value="{{ $machine->id }}">{{ $machine->number . ' - ' . $machine->name }}</option>
                            @endforeach
                        </x-select>
                        @error('machine_id')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                        @error('file')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                </div>
                @break

            @case('review')
                <dl class="text-neutral-900 divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
                    <div class="flex flex-col py-6">
                        <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi batch') }}</dt>
                        <dd>
                            <table class="table table-xs table-col-heading-fit">
                                <tr>
                                    <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                        {{ __('Kode') . ': ' }}
                                    </td>
                                    <td>
                                        {{ $code }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                        {{ __('Kode alt') . ': ' }}
                                    </td>
                                    <td>
                                        {{ $code_alt ?: '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                        {{ __('Warna') . ': ' }}
                                    </td>
                                    <td>
                                        {{ $color ?: '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                        {{ __('MCS') . ': ' }}
                                    </td>
                                    <td>
                                        {{ $mcs ?: '-' }}
                                    </td>
                                </tr>
                            </table>
                            <div class="mt-6">
                                <label for="test-model"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Model') }}</label>
                                <x-text-input id="test-model" list="test-models" wire:model="model" type="text" />
                                @error('model')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                                <datalist id="test-models">
                                    @foreach ($sh_mods as $sh_mod)
                                        <option value="{{ $sh_mod->name }}">
                                    @endforeach
                                </datalist>
                            </div>
                            @if($e_model || $e_color || $e_mcs || $e_code_alt)
                                <div class="mt-6">
                                    <x-toggle name="update_batch" wire:model.live="update_batch" :checked="$update_batch ? true : false" >{{ __('Perbarui informasi batch') }}</x-toggle>
                                </div>
                            @endif
                        </dd>
                    </div>
                    <div class="flex flex-col py-6">
                        <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Hasil uji rheometer') }}</dt>
                        <dd>
                            <table class="table table-xs table-col-heading-fit">
                                <tr>
                                    <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                        {{ __('Mesin') . ': ' }}
                                    </td>
                                    <td>
                                        {{ $machines->firstWhere('id', $this->machine_id) ? ($machines->firstWhere('id', $this->machine_id)->number . '. ' . $machines->firstWhere('id', $this->machine_id)->name ) : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                        {{ __('Penguji') . ': ' }}
                                    </td>
                                    <td>
                                        {{ Auth::user()->name . ' (' . Auth::user()->emp_id . ')' }}
                                    </td>
                                </tr>
                            </table>
                            <div class="grid grid-cols-2">
                                <table class="table table-xs table-col-heading-fit">
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Hasil') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $eval ?: '-' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('S Min') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $s_min ?: '-' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('S Maks') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $s_max ?: '-' }}
                                        </td>
                                    </tr>
                                </table>
                                <table class="table table-xs table-col-heading-fit">
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('TC10') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $tc10 ?: '-' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('TC50') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $tc50 ?: '-' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('TC90') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $tc90 ?: '-' }}
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="mt-6">
                                <label for="test-tag"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tag') }}</label>
                                <x-text-input id="test-tag" list="test-tags" wire:model="tag" type="text" />
                                @error('tag')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                                <datalist id="test-tags">
                                    @foreach ($ins_rdc_tags as $ins_rdc_tag)
                                        <option value="{{ $ins_rdc_tag->name }}">
                                    @endforeach
                                </datalist>
                            </div>
                        </dd>
                    </div>
                </dl>            
                @break

            @case('form')
                <div>
                    <div class="flex flex-col pt-6">
                        <dt class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi batch') }}</dt>
                        <dd>
                            <div class="mt-6">
                                <label for="test-code_alt"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode alternatif') }}</label>
                                <x-text-input id="test-code_alt" wire:model="code_alt" type="text" />
                                @error('code_alt')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                            <div class="mt-6">
                                <label for="test-model"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Model') }}</label>
                                <x-text-input id="test-model" list="test-models" wire:model="model" type="text" />
                                @error('model')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                                <datalist id="test-models">
                                    @foreach ($sh_mods as $sh_mod)
                                        <option value="{{ $sh_mod->name }}">
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="mt-6">
                                <label for="test-color"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Warna') }}</label>
                                <x-text-input id="test-color" wire:model="color" type="text" />
                                @error('color')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                            <div class="mt-6">
                                <label for="test-mcs"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('MCS') }}</label>
                                <x-text-input id="test-mcs" wire:model="mcs" type="text" />
                                @error('mcs')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                        </dd>
                    </div>
                    <div class="flex-flex-col pt-6">
                        <dt class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Hasil uji') }}</dt>
                        <dd>
                            <div class="relative pt-3">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3">
                                    <div class="mt-6">
                                        <label for="test-eval"
                                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Hasil') }}</label>
                                        <x-select class="w-full" id="test-eval" wire:model="eval">
                                            <option value=""></option>
                                            <option value="pass">{{ __('PASS') }}</option>
                                            <option value="fail">{{ __('FAIL') }}</option>
                                        </x-select>
                                        @error('eval')
                                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                        @enderror
                                    </div>
                                    <div class="mt-6">
                                        <label for="test-s_max"
                                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S maks') }}</label>
                                        <x-text-input id="test-s_max" wire:model="s_max" type="number" step=".01" />
                                        @error('s_max')
                                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                        @enderror
                                    </div>
                                    <div class="mt-6">
                                        <label for="test-s_min"
                                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S Min') }}</label>
                                        <x-text-input id="test-s_min" wire:model="s_min" type="number" step=".01" />
                                        @error('s_min')
                                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                        @enderror
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3">
                                    <div class="mt-6">
                                        <label for="test-tc10"
                                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC10') }}</label>
                                        <x-text-input id="test-tc10" wire:model="tc10" type="number" step=".01" />
                                        @error('tc10')
                                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                        @enderror
                                    </div>
                                    <div class="mt-6">
                                        <label for="test-tc50"
                                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC50') }}</label>
                                        <x-text-input id="test-tc50" wire:model="tc50" type="number" step=".01" />
                                        @error('tc50')
                                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                        @enderror
                                    </div>
                                    <div class="mt-6">
                                        <label for="test-tc90"
                                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC90') }}</label>
                                        <x-text-input id="test-tc90" wire:model="tc90" type="number" step=".01" />
                                        @error('tc90')
                                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </dd> 
                        <div class="mt-6">
                            <label for="test-tag"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tag') }}</label>
                            <x-text-input id="test-tag" list="test-tags" wire:model="tag" type="text" />
                            @error('tag')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                            <datalist id="test-tags">
                                @foreach ($ins_rdc_tags as $ins_rdc_tag)
                                    <option value="{{ $ins_rdc_tag->name }}">
                                @endforeach
                            </datalist>
                        </div> 
                    </div>
                </div>
                @break
                
        @endswitch
        <div class="mt-6 flex justify-between items-center">
            <x-dropdown align="left" width="48">
                <x-slot name="trigger">
                    <x-text-button><i class="icon-ellipsis-vertical"></i></x-text-button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-link href="#" wire:click.prevent="customReset">
                        {{ __('Reset') }}
                    </x-dropdown-link>
                    <hr class="border-neutral-300 dark:border-neutral-600 {{ true ? '' : 'hidden' }}" />
                    <x-dropdown-link href="#" wire:click.prevent="removeFromQueue"
                        class="{{ true ? '' : 'hidden' }}">
                        {{ __('Hapus dari antrian') }}
                    </x-dropdown-link>
                </x-slot>
            </x-dropdown>
            <div class="flex flex-row gap-x-3">
                @if($view != 'form')
                <x-secondary-button type="button" wire:click="$set('view', 'form'); $set('file', '')" x-show="machine_id">{{ __('Isi manual') }}</x-secondary-button>
                @endif
                @if($view == 'review' || $view == 'form')
                <x-primary-button type="button" wire:click="insertTest">
                    {{ __('Sisipkan') }}
                </x-primary-button>
                @endif
                @if($view == 'upload')
                <x-primary-button type="button" x-on:click="$refs.file.click()" x-show="machine_id" ><i
                    class="icon-upload mr-2"></i>{{ __('Unggah') }}</x-primary-button>
                @endif
            </div>
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
