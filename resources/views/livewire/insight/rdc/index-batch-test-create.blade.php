<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\InsRdcTest;
use App\Models\InsRdcMachine;
use App\Models\InsRubberBatch;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
// use Illuminate\Validation\Rule;

// use App\Models\User;
// use Livewire\Attributes\Renderless;
// use Illuminate\Support\Facades\Gate;

new #[Layout('layouts.app')] 
class extends Component {

    use WithFileUploads;

    public int $id;
    public string $updated_at;
    public string $code;
    
    public string $model = '';
    public string $color = '';
    public string $mcs = '';

    public string $e_model = '';
    public string $e_color = '';
    public string $e_mcs = '';

    public string $o_model = '';
    public string $o_color = '';
    public string $o_mcs = '';

    public float $s_max;
    public float $s_min;
    public string $eval;
    public float $tc10;
    public float $tc50;
    public float $tc90;

    public int $machine_id;
    public bool $update_batch = false;

    public $file;

    #[On('batch-test-create')]
    public function loadBatch(int $id)
    {
        $batch = InsRubberBatch::find($id);

        if ($batch) {
            $this->id = $batch->id;
            $this->updated_at = $batch->updated_at ?? '';
            $this->code = $batch->code;

            $this->model    = $batch->model ?? '';
            $this->color    = $batch->color ?? '';
            $this->mcs      = $batch->mcs ?? '';

            $this->o_model  = $batch->model ?? '';
            $this->o_color  = $batch->color ?? '';
            $this->o_mcs    = $batch->mcs ?? '';

            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }

        $this->customReset();
    }

    public function updatedFile()
    {
        $this->validate([
            'file' => 'file|mimes:xls,xlsx|max:1024'
        ]);

        $this->extractData();
    }

    private function find3Digit($string) {
        preg_match_all('/\/?(\d{3})/', $string, $matches);
        return !empty($matches[1]) ? end($matches[1]) : null;
    }

    private function extractData()
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

            if((!$this->model && !$this->color && !$this->mcs) && ($this->e_model || $this->e_color || $this->e_mcs)) 
            {
                $this->update_batch = true;
                $this->updateBatchInfo();
            }

        } catch (\Exception $e) {
            $this->js('notyfError("' . __('Terjadi galat ketika memproses berkas. Periksa console') . '")'); 
            $this->js('console.log("'. $e->getMessage() .'")');
        }
    }

    private function safeString($value): string
    {
        return is_string($value) ? strtoupper(trim($value)) : '';
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
                'rdc_eval' => null
            ]);
            $this->js('notyfSuccess("' . __('Dihapus dari antrian') . '")'); 
            $this->js('$dispatch("close")');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
        }
    }

    private function updateBatchInfo()
    {
        if ($this->update_batch) {
            // Use extracted values if available, otherwise keep current value
            $this->model = $this->e_model ?: $this->model;
            $this->color = $this->e_color ?: $this->color;
            $this->mcs = $this->e_mcs ?: $this->mcs;
        } else {
            // Revert to original values
            $this->model = $this->o_model;
            $this->color = $this->o_color;
            $this->mcs = $this->o_mcs;
        }
    }

    public function rules()
    {
        return [
            's_max'         => ['required', 'numeric', 'gt:0', 'lt:99'],
            's_min'         => ['required', 'numeric', 'gt:0', 'lt:99'],
            'eval'          => ['required', 'in:pass,fail'],
            'tc10'          => ['required', 'numeric', 'gt:0', 'lt:999'],
            'tc50'          => ['required', 'numeric', 'gt:0', 'lt:999'],
            'tc90'          => ['required', 'numeric', 'gt:0', 'lt:999'],
            'machine_id'    => ['required', 'exists:ins_rdc_machines,id'],
        ];
    }

    public function insertTest()
    {
        $batch = InsRubberBatch::find($this->id);

        if ($batch) {
            $test = new InsRdcTest;
            Gate::authorize('manage', $test);

            $validatedTest  = $this->validate();
            $validatedBatch = Validator::make(
                [
                    'model'      => $this->e_model,
                    'color'      => $this->e_color,
                    'mcs'        => $this->e_mcs,
                ],
                [
                    'model'      => 'nullable|string|max:50',
                    'color'      => 'nullable|string|max:20',
                    'mcs'        => 'nullable|string|max:10',
                ]
            );

            if ($validatedBatch->fails() && $this->update_batch) {
                $error = $validatedBatch->errors()->first();
                $this->js('notyfError("'.$error.'")'); 

            } else {

                $test->fill([
                    's_max'                 => $validatedTest['s_max'],
                    's_min'                 => $validatedTest['s_min'],
                    'eval'                  => $validatedTest['eval'],
                    'tc10'                  => $validatedTest['tc10'],
                    'tc50'                  => $validatedTest['tc50'],
                    'tc90'                  => $validatedTest['tc90'],
                    'user_id'               => Auth::user()->id,
                    'ins_rubber_batch_id'   => $batch->id,
                    'ins_rdc_machine_id'    => $validatedTest['machine_id'],
                    'queued_at'             => $batch->updated_at,
                ]);

                $test->save();

                if ($this->update_batch) {
                    $batch->update([
                        'model' => $this->e_model ?: $this->model,
                        'color' => $this->e_color ?: $this->color,
                        'mcs'   => $this->e_mcs ?: $this->mcs,
                    ]);
                }

                $batch->update([
                    'rdc_eval' => $validatedTest['eval']
                ]);

                $this->js('$dispatch("close")');
                $this->js('notyfSuccess("' . __('Hasil uji disisipkan') . '")');
                $this->dispatch('updated');

                $this->customReset();
            }

        } else {
            $this->handleNotFound();
        }
      
    }

    public function with(): array
    {
        $this->updateBatchInfo();
        return [
            'machines' => InsRdcMachine::orderBy('number')->get()
        ];
    }

    // public function save()
    // {
    //     Gate::batchorize('superuser');
    //     $this->validate();

    //     $batch = InsRubberBatch::find($this->id);
    //     if ($batch) {
    //         $batch->actions = json_encode($this->actions, true);
    //         $batch->update();

    //         $this->js('$dispatch("close")');
    //         $this->js('notyfSuccess("' . __('Wewenang diperbarui') . '")');
    //         $this->dispatch('updated');
    //     } else {
    //         $this->handleNotFound();
    //         $this->customReset();
    //     }
    // }

    // public function delete()
    // {
    //     Gate::batchorize('superuser');

    //     $batch = InsRubberBatch::find($this->id);
    //     if ($batch) {
    //         $batch->delete();

    //         $this->js('$dispatch("close")');
    //         $this->js('notyfSuccess("' . __('Wewenang dicabut') . '")');
    //         $this->dispatch('updated');
    //     } else {
    //         $this->handleNotFound();
    //     }
    //     $this->customReset();
    // }

    public function machineReset()
    {
        $this->customReset();
        $this->reset(['machine_id']);
    }

    public function customReset()
    {
        $this->resetValidation();
        $this->reset(['file','s_max', 's_min', 'eval', 'tc10', 'tc50', 'tc90', 'model', 'color', 'mcs', 'e_model', 'e_color', 'e_mcs', 'o_model', 'o_color', 'o_mcs', 'update_batch']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('notyfError("' . __('Tidak ditemukan') . '")');
        $this->dispatch('updated');
    }
};

?>
<div>
    <div class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Sisipkan hasil uji') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="relative" x-data="{ dropping: false, machine_id: @entangle('machine_id') }" x-on:dragover.prevent="machine_id ? dropping = true : dropping = false">
            <div wire:loading.class="hidden"
                class="absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white/80 dark:bg-neutral-800/80 py-3"
                x-cloak x-show="dropping">
                <div
                    class="flex justify-around items-center w-full h-full border-dashed border-2 border-neutral-500  text-neutral-500 dark:text-neutral-400 rounded-lg">
                    <div class="text-center">
                        <div class="text-4xl mb-3">
                            <i class="fa fa-upload"></i>
                        </div>
                    </div>
                </div>
            </div>
            <input wire:model="file" type="file"
                    class="absolute inset-0 m-0 p-0 w-full h-full outline-none opacity-0" x-cloak x-ref="file"
                    x-show="dropping" x-on:dragleave.prevent="dropping = false" x-on:drop="dropping = false" />
            <div class="flex flex-col items-center justify-center gap-6 h-48">
                <x-select class="w-full" id="test-machine_id" x-model="machine_id" :disabled="$file">
                    <option value=""></option>
                    @foreach($machines as $machine)
                        <option value="{{ $machine->id }}">{{ $machine->number . ' - ' . $machine->name }}</option>
                    @endforeach
                </x-select>
                @error('machine_id')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
                <x-primary-button type="button" x-on:click="$refs.file.click()" x-bind:disabled="!machine_id || {{ $file ? 'true' : 'false' }}" ><i
                    class="fa fa-upload mr-2"></i>{{ __('Unggah') }}</x-primary-button>
            </div>
        </div>

        <dl class="text-neutral-900 divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
            <div class="flex flex-col py-6">
                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi batch') }}</dt>
                <dd>
                    <table class="table table-xs table-col-heading-fit">
                        <tr>
                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Kode 1') . ': ' }}
                            </td>
                            <td>
                                {{ $code }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Kode 2') . ': ' }}
                            </td>
                            <td>
                                {{ 'TBA' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Model') . ': ' }}
                            </td>
                            <td>
                                {{ $model }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Warna') . ': ' }}
                            </td>
                            <td>
                                {{ $color }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('MCS') . ': ' }}
                            </td>
                            <td>
                                {{ $mcs }}
                            </td>
                        </tr>
                    </table>
                </dd>
            </div>
            <div class="flex flex-col py-6">
                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Hasil uji rheometer') }}</dt>
                <dd>
                    <div>
                        <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                            {{ __('Mesin') . ': ' }}
                        </span>
                        <span>
                            Free memory
                        </span>
                    </div>
                    <div>
                        <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                            {{ __('Penguji') . ': ' }}
                        </span>
                        <span>
                            {{ Auth::user()->name . ' (' . Auth::user()->emp_id . ')' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 mt-3">
                        <div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Hasil') . ': ' }}
                                </span>
                                <span>
                                    {{ $eval }}
                                </span>
                            </div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('S Min') . ': ' }}
                                </span>
                                <span>
                                    {{ $s_min }}
                                </span>
                            </div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('S Maks') . ': ' }}
                                </span>
                                <span>
                                    {{ $s_max }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('TC10') . ': ' }}
                                </span>
                                <span>
                                    {{ $tc10 }}
                                </span>
                            </div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('TC50') . ': ' }}
                                </span>
                                <span>
                                    {{ $tc50 }}
                                </span>
                            </div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('TC90') . ': ' }}
                                </span>
                                <span>
                                    {{ $tc90 }}
                                </span>
                            </div>
                        </div>
                    </div>
                </dd>
            </div>
        </dl>

        <div>
            <div class="flex flex-col pt-6">
                <dt class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi batch') }}</dt>
                <dd>
                    <div class="mt-6">
                        <label for="test-model"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Model') }}</label>
                        <x-text-input id="test-model" wire:model="model" type="text"
                            :disabled="Gate::denies('manage', InsRdcTest::class) || $update_batch" />
                        @error('model')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div class="mt-6">
                        <label for="test-color"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Warna') . $update_batch }}</label>
                        <x-text-input id="test-color" wire:model="color" type="text"
                            :disabled="Gate::denies('manage', InsRdcTest::class) || $update_batch" />
                        @error('color')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div class="mt-6">
                        <label for="test-mcs"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('MCS') }}</label>
                        <x-text-input id="test-mcs" wire:model="mcs" type="text"
                            :disabled="Gate::denies('manage', InsRdcTest::class) || $update_batch" />
                        @error('mcs')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    @if($e_model || $e_color || $e_mcs)
                    <div>
                        <x-toggle name="mblur" wire:model.live="update_batch" :checked="$update_batch ? true : false" >{{ __('Perbarui informasi batch') }}</x-toggle>
                    </div>
                    @endif
                </dd>
            </div>
            <div class="flex-flex-col pt-6">
                <dt class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Hasil uji') }}</dt>
                <dd>
                    <div class="relative py-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3">
                            <div class="mt-6">
                                <label for="test-s_max"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S maks') }}</label>
                                <x-text-input id="test-s_max" wire:model="s_max" type="number" step=".01"
                                    :disabled="Gate::denies('manage', InsRdcTest::class) || $file" />
                                @error('s_max')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                            <div class="mt-6">
                                <label for="test-s_min"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S Min') }}</label>
                                <x-text-input id="test-s_min" wire:model="s_min" type="number" step=".01"
                                    :disabled="Gate::denies('manage', InsRdcTest::class) || $file" />
                                @error('s_min')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                            <div class="mt-6">
                                <label for="test-eval"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Hasil') }}</label>
                                <x-select class="w-full" id="test-eval" wire:model="eval" :disabled="Gate::denies('manage', InsRdcTest::class) || $file">
                                    <option value=""></option>
                                    <option value="pass">{{ __('PASS') }}</option>
                                    <option value="fail">{{ __('FAIL') }}</option>
                                </x-select>
                                @error('eval')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3">
                            <div class="mt-6">
                                <label for="test-tc10"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC10') }}</label>
                                <x-text-input id="test-tc10" wire:model="tc10" type="number" step=".01"
                                    :disabled="Gate::denies('manage', InsRdcTest::class) || $file" />
                                @error('tc10')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                            <div class="mt-6">
                                <label for="test-tc50"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC50') }}</label>
                                <x-text-input id="test-tc50" wire:model="tc50" type="number" step=".01"
                                    :disabled="Gate::denies('manage', InsRdcTest::class) || $file" />
                                @error('tc50')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                            <div class="mt-6">
                                <label for="test-tc90"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC90') }}</label>
                                <x-text-input id="test-tc90" wire:model="tc90" type="number" step=".01"
                                    :disabled="Gate::denies('manage', InsRdcTest::class) || $file" />
                                @error('tc90')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                        </div>
                    </div>
                </dd>  
            </div>
        </div>
        <div class="mt-6 flex justify-between items-center">
            <x-dropdown align="left" width="48">
                <x-slot name="trigger">
                    <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
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
            <x-secondary-button type="button">{{ __('Isi manual') }}</x-secondary-button>
            <x-primary-button type="button" wire:click="insertTest">
                {{ __('Sisipkan') }}
            </x-primary-button>
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
