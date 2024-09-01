<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use App\Models\InsRdcTest;
use App\Models\InsRubberBatch;

new #[Layout('layouts.app')] 
class extends Component {
   public int $id;

   public string $batch_code;
   public string $batch_model;
   public string $batch_color;
   public string $batch_mcs;
   public string $batch_code_alt;
   public string $batch_omv_eval;
   public string $batch_omv_eval_human;
   public string $batch_rdc_eval;
   public string $batch_rdc_eval_human;
   public int $batch_rdc_tests_count;
   public string $batch_updated_at_human;
   public int $test_machine_number;
   public string $test_machine_name;
   public string $test_user_name;
   public string $test_user_emp_id;
   public string $test_eval;
   public string $test_eval_human;
   public float $test_s_min;
   public float $test_s_max;
   public float $test_tc10;
   public float $test_tc50;
   public float $test_tc90;
   public string $test_queued_at;
   public string $test_updated_at;

   #[On('test-show')]
   public function showTest(int $id)
   {
      $this->id = $id;
      $test = InsRdcTest::find($id);
      if ($test) {
         $this->id                     = $test->id;
         $this->batch_code             = $test->ins_rubber_batch->code ?? '-';
         $this->batch_model            = $test->ins_rubber_batch->model ?? '-';
         $this->batch_color            = $test->ins_rubber_batch->color ?? '-';
         $this->batch_mcs              = $test->ins_rubber_batch->mcs ?? '-';
         $this->batch_code_alt         = $test->ins_rubber_batch->code_alt ?? '-';
         $this->batch_omv_eval         = $test->ins_rubber_batch->omv_eval ?? '-';
         $this->batch_omv_eval_human   = $test->ins_rubber_batch->omvEvalHuman();
         $this->batch_rdc_eval         = $test->ins_rubber_batch->rdc_eval ?? '-';
         $this->batch_rdc_eval_human   = $test->ins_rubber_batch->rdcEvalHuman();
         $this->batch_rdc_tests_count  = $test->ins_rubber_batch->ins_rdc_tests->count();
         $this->batch_updated_at_human = $test->ins_rubber_batch->updated_at->diffForHumans();

         $this->test_machine_number    = $test->ins_rdc_machine->number;
         $this->test_machine_name      = $test->ins_rdc_machine->name;
         $this->test_user_name         = $test->user->name;
         $this->test_user_emp_id       = $test->user->emp_id;
         $this->test_eval              = $test->eval;
         $this->test_eval_human        = $test->evalHuman();
         $this->test_s_min             = $test->s_min;
         $this->test_s_max             = $test->s_max;
         $this->test_tc10              = $test->tc10;
         $this->test_tc50              = $test->tc50;
         $this->test_tc90              = $test->tc90;

         $this->test_queued_at         = $test->queued_at;
         $this->test_updated_at        = $test->updated_at;
      } else {
         $this->handleNotFound();
      }
   }

   public function customReset()
   {
      $this->reset(['id']);
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
                {{ __('Rincian pengujian rheometer') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <dl class="text-neutral-900 divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
            <div class="flex flex-col py-6">
                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi batch') }}</dt>
                <dd class="flex gap-x-6">
                    <div>                        
                        <ol class="relative border-s border-neutral-200 dark:border-neutral-700 mt-2">                  
                            <li class="mb-6 ms-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('OMV')}}</div>
                                <x-pill class="uppercase"
                                color="{{ $batch_omv_eval === 'on_time' ? 'green' : ($batch_omv_eval === 'too_late' || $batch_omv_eval === 'too_soon' ? 'red' : '') }}">{{ $batch_omv_eval_human }}</x-pill>    
                            </li>
                            <li class="mb-6 ms-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('RTC')}}</div>
                                <x-pill class="uppercase"
                                color="neutral">{{ __('Segera') }}</x-pill>
                            </li>
                            <li class="mb-6 ms-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Rheo')}}</div>
                                <x-pill class="uppercase"
                                color="{{ $batch_rdc_eval === 'queue' ? 'yellow' : ($batch_rdc_eval === 'pass' ? 'green' : ($batch_rdc_eval === 'fail' ? 'red' : '')) }}">{{ $batch_rdc_eval_human }}</x-pill> 

                            </li>
                            <li class="ms-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Aging') }}</div>
                                <x-pill class="uppercase"
                                color="neutral">{{ __('Segera') }}</x-pill>                                </li>
                        </ol>
                    </div>
                    <div class="grow">
                        <table class="table table-xs table-col-heading-fit">
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Kode') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch_code }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Kode alt') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch_code_alt }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Model') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch_model }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Warna') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch_color }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('MCS') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch_mcs }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Uji rheo') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch_rdc_tests_count . ' kali' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Diperbarui') . ': ' }}
                                </td>
                                <td>
                                    {{ $batch_updated_at_human }}
                                </td>
                            </tr>
                        </table>
                    </div>
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
                            {{ $test_machine_number . '. ' . $test_machine_name }}
                        </span>
                    </div>
                    <div>
                        <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                            {{ __('Penguji') . ': ' }}
                        </span>
                        <span>
                            {{ $test_user_name . ' (' . $test_user_emp_id . ')' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 mt-3">
                        <div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Hasil') . ': ' }}
                                </span>
                                <x-pill class="uppercase"
                                color="{{ $test_eval === 'queue' ? 'yellow' : ($test_eval === 'pass' ? 'green' : ($test_eval === 'fail' ? 'red' : '')) }}">{{ $test_eval_human }}</x-pill> 
                            </div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('S Min') . ': ' }}
                                </span>
                                <span>
                                    {{ $test_s_min }}
                                </span>
                            </div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('S Maks') . ': ' }}
                                </span>
                                <span>
                                    {{ $test_s_max }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('TC10') . ': ' }}
                                </span>
                                <span>
                                    {{ $test_tc10 }}
                                </span>
                            </div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('TC50') . ': ' }}
                                </span>
                                <span>
                                    {{ $test_tc50 }}
                                </span>
                            </div>
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('TC90') . ': ' }}
                                </span>
                                <span>
                                    {{ $test_tc90 }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Diantrikan pada') . ': ' }}
                            </span>
                            <span>
                                {{ $test_queued_at }}
                            </span>
                        </div>
                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Diselesaikan pada') . ': ' }}
                            </span>
                            <span>
                                {{ $test_updated_at }}
                            </span>
                        </div>
                    </div>
                </dd>
            </div>
        </dl>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
