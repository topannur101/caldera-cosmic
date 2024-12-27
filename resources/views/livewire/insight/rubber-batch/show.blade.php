<?php

use App\Models\InsRubberBatch;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public int $id;

    public array $batch = [
        'omv_eval'          => '',
        'omv_eval_human'    => '',
        'rdc_eval'          => '',
        'rdc_eval_human'    => '',
        'code'              => '',
        'code_alt'          => '',
        'model'             => '',
        'color'             => '',
        'mcs'               => '',
        'rdc_tests_count'   => '',
        'updated_at_human'  => '',
    ];

    public string $view = '';

    public function mount() {
        $this->batch['code'] = __('Kode batch');
    }

    private function loadBatch(int $id) {

        $batch = InsRubberBatch::find($id);
        
        if ($batch) {
            $this->id = $id;
            $this->batch['omv_eval']         = $batch->omv_eval;
            $this->batch['omv_eval_human']   = $batch->omvEvalHuman();
            $this->batch['rdc_eval']         = $batch->rdc_eval;
            $this->batch['rdc_eval_human']   = $batch->rdcEvalHuman();
            $this->batch['code']             = $batch->code;
            $this->batch['code_alt']         = $batch->code_alt ?: '-';
            $this->batch['model']            = $batch->model ?: '-';
            $this->batch['color']            = $batch->color ?: '-';
            $this->batch['mcs']              = $batch->mcs ?: '-';
            $this->batch['rdc_tests_count']  = $batch->ins_rdc_tests->count();
            $this->batch['updated_at_human'] = $batch->updated_at->diffForHumans();
        } else {
            $this->handleNotFound();
         }
    }

    #[On('batch-show')]
    public function showBatch(int $id) {
        $this->loadBatch($id);
    }

    #[On('batch-show-omv')]
    public function showBatchOmv(int $id) {

        $this->loadBatch($id);
        if ($this->id) {
            $this->view = 'omv';
        }
    }

        
    #[On('batch-show-rdc')]
    public function showBatchRdc(int $id) {

        $this->loadBatch($id);
        if ($this->id) {
            $this->view = 'rdc';
        }

    }

    #[On('batch-show-rtc')]
    public function showBatchRtc(int $id) {

        $this->loadBatch($id);
        if ($this->id) {
            $this->view = 'rtc';
        }
        
    }

    public function customReset()
    {
        $this->reset(['id', 'batch', 'view']);
    }

    public function handleNotFound()
    {
        $this->customReset();
        $this->js('$dispatch("close")');
        $this->js('notyfError("'.__('Tidak ditemukan').'")');
        $this->dispatch('updated');
    }
};

?>
<div>
    <div class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg uppercase font-medium text-neutral-900 dark:text-neutral-100">
                {{ $batch['code'] }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <dl class="text-neutral-900 divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
            <div class="flex flex-col py-6">
                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi batch') }}</dt>
                <dd class="flex gap-x-6">
                    <div class="w-40">                        
                        <ol class="relative border-s border-neutral-200 dark:border-neutral-700 mt-2">                  
                            <li class="mb-6 ms-4 cursor-pointer rounded focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 transition ease-in-out duration-150" wire:click="switchView" tabindex="0">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Open mill')}}</div>
                                <x-pill class="uppercase"
                                color="{{ $batch['omv_eval'] === 'on_time' ? 'green' : ($batch['omv_eval'] === 'on_time_manual' ? 'yellow' : ($batch['omv_eval'] === 'too_late' || $batch['omv_eval'] === 'too_soon' ? 'red' : 'neutral')) }}">{{ $batch['omv_eval_human'] }}</x-pill>    
                            </li>
                            <li class="mb-6 ms-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Calendar')}}</div>
                                <x-pill class="uppercase"
                                color="neutral">N/A</x-pill>
                            </li>
                            <li class="mb-6 ms-4 cursor-pointer rounded focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 transition ease-in-out duration-150" wire:click="switchView" tabindex="0">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Rheometer')}}</div>
                                <x-pill class="uppercase"
                                color="{{ $batch['rdc_eval'] === 'queue' ? 'yellow' : ($batch['rdc_eval'] === 'pass' ? 'green' : ($batch['rdc_eval'] === 'fail' ? 'red' : 'neutral')) }}">{{ $batch['rdc_eval_human'] }}</x-pill> 

                            </li>
                            <li class="ms-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Aging') }}</div>
                                <x-pill class="uppercase"
                                color="neutral">N/A</x-pill>                                </li>
                        </ol>
                    </div>
                    <div class="grow">
                        <table class="table table-xs table-col-heading-fit">
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Kode alt') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch['code_alt'] }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Model') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch['model'] }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Warna') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch['color'] }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('MCS') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch['mcs'] }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Uji rheo') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch['rdc_tests_count'] . ' kali' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Diperbarui') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch['updated_at_human'] }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </dd>
            </div>
        </dl>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
