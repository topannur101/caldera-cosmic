<?php

use App\Models\InsRubberBatch;
use App\Models\InsOmvMetric;
use App\Models\InsRdcTest;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public int $batch_id = 0;

    public int $omv_metric_id = 0;

    public int $rdc_test_id = 0;

    public array $batch = [
        'code'              => '',
        'code_alt'          => '',
        'model'             => '',
        'color'             => '',
        'mcs'               => '',
        'omv_eval'          => '',
        'omv_eval_human'    => '',
        'rdc_eval'          => '',
        'rdc_eval_human'    => '',
        'rdc_tests_count'   => '',
        'created_at'        => '',
        'updated_at'        => '',
    ];

    public string $view = '';

    private function loadBatch(int $id) {

        $batch = InsRubberBatch::find($id);
        
        if ($batch) {
            $this->batch_id = $id;
            $this->batch['code']             = $batch->code;
            $this->batch['code_alt']         = $batch->code_alt ?: '';
            $this->batch['model']            = $batch->model ?: '?';
            $this->batch['color']            = $batch->color ?: '?';
            $this->batch['mcs']              = $batch->mcs ?: '?';
            $this->batch['created_at']       = $batch->created_at ?: '';
            $this->batch['updated_at']       = $batch->updated_at ?: '';

            $omv_metric = $batch->ins_omv_metric;
            $rdc_test   = $batch->ins_rdc_test;

            if ($omv_metric) {
                $this->omv_metric_id            = $omv_metric->id;
                $this->batch['omv_eval']        = $omv_metric->eval;
                $this->batch['omv_eval_human']  = $omv_metric->evalHuman();
            }

            if ($rdc_test) {
                $this->rdc_test_id              = $rdc_test->id;
                $this->batch['rdc_eval']        = $rdc_test->eval;
                $this->batch['rdc_eval_human']  = $rdc_test->evalHuman();
                $this->batch['rdc_tests_count'] = $batch->ins_rdc_tests->count();
            }

        } else {
            $this->handleNotFound();
         }
    }

    #[On('batch-show')]
    public function showBatch(int $batch_id = 0, string $view = '', int $omv_metric_id = 0, int $rdc_test_id = 0) {

        if ($batch_id) {
            $this->loadBatch($batch_id);
            
        } elseif ($omv_metric_id || $rdc_test_id) {

            if ($omv_metric_id) {
                $omv_metric = InsOmvMetric::find($omv_metric_id);
    
                if ($omv_metric) {
                    $this->omv_metric_id    = $omv_metric->id;
                    $this->batch_id         = $omv_metric->ins_rubber_batch_id ?: 0;
                }            
    
            } elseif ($rdc_test_id) {
                $rdc_test = InsRdcTest::find($rdc_test_id);
    
                if ($rdc_test) {
                    $this->rdc_test_id      = $rdc_test->id;
                    $this->batch_id         = $rdc_test->ins_rubber_batch_id ?: 0;
                }
            }

            if ($this->batch_id) {
                $this->loadBatch($this->batch_id);
            } else {
                $this->batchReset();
            }
        }

        $this->view = $view;

    }

    public function customReset()
    {
        $this->batchReset();
        $this->reset(['batch_id', 'omv_metric_id', 'rdc_test_id', 'view']);
    }

    public function batchReset()
    {
        $this->reset(['batch']);
    }

    public function handleNotFound()
    {
        $this->customReset();
        $this->js('$dispatch("close")');
        $this->js('toast("'.__('Tidak ditemukan').'", { type: "danger" })');
        $this->dispatch('updated');
    }
};

?>
<div>
    <div class="p-6 text-neutral-900 dark:text-white">
        <div class="flex justify-between items-start mb-4">
            @if($view && $batch_id)
            <x-text-button type="button" wire:click="showBatch({{ $batch_id }})">
            <div class="flex gap-x-3 items-center">
                    <i class="fa fa-arrow-left"></i>
                <h2 class="text-lg uppercase font-medium">
                    {{ $batch['code'] ?: __('Tanpa kode') }}
                </h2>
            </div>
            </x-text-button>
            @else
            <h2 class="text-lg uppercase font-medium">{{ $batch['code'] ?: __('Tanpa kode') }}</h2>
            @endif

            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div>
            @switch($view)
                @case('omv')
                    <livewire:insights.rubber-batch.omv :$omv_metric_id />
                    @break
                @case('rdc')
                    <livewire:insights.rubber-batch.rdc :$rdc_test_id />
                    @break
                @default
                    <div class="grid grid-cols-3 gap-x-6">
                    <div>
                        <ol class="relative border-s border-neutral-200 dark:border-neutral-700">                  
                            <li @if( $batch['omv_eval'] ) wire:click="showBatch({{ $batch_id }}, 'omv')" @endif tabindex="0" class="ms-3 px-3 py-4 cursor-pointer rounded hover:bg-caldy-500 hover:bg-opacity-10 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 transition ease-in-out duration-150">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Open mill')}}</div>
                                <x-pill class="uppercase"
                                color="{{ $batch['omv_eval'] === 'on_time' ? 'green' : ($batch['omv_eval'] === 'on_time_manual' ? 'yellow' : ($batch['omv_eval'] === 'too_late' || $batch['omv_eval'] === 'too_soon' ? 'red' : 'neutral')) }}">{{ $batch['omv_eval_human'] ?: 'N/A' }}</x-pill>    
                            </li>
                            <li class="ms-3 px-3 py-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Calendar')}}</div>
                                <x-pill class="uppercase"
                                color="neutral">N/A</x-pill>
                            </li>
                            <li @if( $batch['rdc_eval'] ) wire:click="showBatch({{ $batch_id }}, 'rdc')" @endif tabindex="0" class="ms-3 px-3 py-4 cursor-pointer rounded hover:bg-caldy-500 hover:bg-opacity-10 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 transition ease-in-out duration-150">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Rheometer')}}</div>
                                <x-pill class="uppercase"
                                color="{{ $batch['rdc_eval'] === 'queue' ? 'yellow' : ($batch['rdc_eval'] === 'pass' ? 'green' : ($batch['rdc_eval'] === 'fail' ? 'red' : 'neutral')) }}">{{ $batch['rdc_eval_human'] ?: 'N/A' }}</x-pill> 

                            </li>
                            <li class="ms-3 px-3 py-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Aging') }}</div>
                                <x-pill class="uppercase"
                                color="neutral">N/A</x-pill>
                            </li>
                        </ol>
                    </div>
                    <div class="col-span-2">
                        <div class="px-1 py-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi batch') }}</div>
                        <table class="table table-xs table-col-heading-fit">
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
                                    {{ __('Dibuat') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch['created_at'] }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Diperbarui') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch['updated_at'] }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            @endswitch
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
