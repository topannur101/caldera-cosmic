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
   public string $batch_omv_eval;
   public string $batch_omv_eval_human;
   public string $batch_rdc_eval;
   public string $batch_rdc_eval_human;
   public int $test_machine_number;
   public string $test_machine_name;
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
         $this->batch_omv_eval         = $test->ins_rubber_batch->omv_eval ?? '-';
         $this->batch_omv_eval_human   = $test->ins_rubber_batch->omvEvalHuman();
         $this->batch_rdc_eval         = $test->ins_rubber_batch->rdc_eval ?? '-';
         $this->batch_rdc_eval_human   = $test->ins_rubber_batch->rdcEvalHuman();

         $this->test_machine_number    = $test->ins_rdc_machine->number;
         $this->test_machine_name      = $test->ins_rdc_machine->name;
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
        <dl class="text-neutral-900 divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700 mt-6 text-sm">
            <div class="flex flex-col pb-3">
                <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Informasi batch') }}</dt>
                <dd>
                    <div>{{ __('Kode 1') . ': ' . $batch_code }}</div>
                    <div>{{ __('Kode 2') . ': ' . 'TBA' }}</div>
                    <div>{{ __('Model') . ': ' . $batch_model }}</div>
                    <div>{{ __('Warna') . ': ' . $batch_color }}</div>
                    <div>{{ __('MCS') . ': ' . $batch_mcs }}</div>
                </dd>
            </div>
            <div class="grid grid-cols-2">
                <div class="flex flex-col py-3">
                    <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Hasil open mill') }}</dt>
                    <dd><x-pill class="uppercase"
                            color="{{ $batch_omv_eval === 'on_time' ? 'green' : ($batch_omv_eval === 'too_late' || $batch_omv_eval === 'too_soon' ? 'red' : '') }}">{{ $batch_omv_eval_human }}</x-pill>
                    </dd>
                </div>
                <div class="flex flex-col py-3">
                    <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Hasil rheometer') }}</dt>
                    <dd><x-pill class="uppercase"
                            color="{{ $batch_rdc_eval === 'queue' ? 'yellow' : ($batch_rdc_eval === 'pass' ? 'green' : ($batch_rdc_eval === 'fail' ? 'red' : '')) }}">{{ $batch_rdc_eval_human }}</x-pill>
                    </dd>
                </div>
            </div>
            <div class="flex flex-col py-3">
                <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Data rheometer') }}</dt>
                <dd>
                    <div>{{ __('Mesin') . ': ' . $test_machine_number . '. ' . $test_machine_name }}</div>
                    <div>{{ __('S Min') . ': ' . $test_s_min }}</div>
                    <div>{{ __('S Maks') . ': ' . $test_s_max }}</div>
                    <div>{{ __('TC10') . ': ' . $test_tc10 }}</div>
                    <div>{{ __('TC50') . ': ' . $test_tc50 }}</div>
                    <div>{{ __('TC90') . ': ' . $test_tc90 }}</div>
                </dd>
            </div>
            <div class="flex flex-col pt-3">
                <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Waktu') }}</dt>
                <dd>
                    <div>{{ __('Diantrikan pada') . ': ' . $test_queued_at }}</div>
                    <div>{{ __('Diselesaikan pada') . ': ' . $test_updated_at }}</div>
                </dd>
            </div>
        </dl>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
