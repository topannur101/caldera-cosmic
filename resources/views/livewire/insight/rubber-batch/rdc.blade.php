<?php

use App\Models\InsRdcTest;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public int $rdc_test_id;

    public array $batch = [
        'code' => '',
        'code_alt' => '',
        'rdc_eval' => '',
        'rdc_eval_human' => '',
        'model' => '',
        'color' => '',
        'mcs' => '',
        'rdc_test_count' => '',
    ];

    public int $machine_number;

    public string $machine_name;

    public string $user_name;

    public string $user_emp_id;

    public float $s_min;

    public float $s_max;

    public float $tc10;

    public float $tc50;

    public float $tc90;

    public string $queued_at;

    public string $updated_at;

    public function mount()
    {
        if ($this->rdc_test_id) {
            $this->show(view: 'rdc', rdc_test_id: $this->rdc_test_id);
        }
    }

    #[On('batch-show')]
    public function show(string $view = '', int $rdc_test_id = 0)
    {
        if ($view !== 'rdc') {
            return;
        }

        $this->customReset();

        $test = InsRdcTest::with('ins_rubber_batch')->find($rdc_test_id);

        if ($test) {

            $this->id = $test->id;
            $this->machine_number = $test->ins_rdc_machine->number;
            $this->machine_name = $test->ins_rdc_machine->name;
            $this->user_name = $test->user->name;
            $this->user_emp_id = $test->user->emp_id;
            $this->s_min = $test->s_min;
            $this->s_max = $test->s_max;
            $this->tc10 = $test->tc10;
            $this->tc50 = $test->tc50;
            $this->tc90 = $test->tc90;
            $this->queued_at = $test->queued_at;
            $this->updated_at = $test->updated_at;

            if ($test->ins_rubber_batch_id) {
                $this->batch['code'] = $test->ins_rubber_batch->code ?? '-';
                $this->batch['model'] = $test->ins_rubber_batch->model ?? __('Model');
                $this->batch['color'] = $test->ins_rubber_batch->color ?? __('Warna');
                $this->batch['mcs'] = $test->ins_rubber_batch->mcs ?? __('MCS');
                $this->batch['code_alt'] = $test->ins_rubber_batch->code_alt ?? '-';
            }

            $this->batch['rdc_eval'] = $test->eval ?? '-';
            $this->batch['rdc_eval_human'] = $test->evalHuman();

        } else {
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset([
            'rdc_test_id',
            'batch',
            'machine_number',
            'machine_name',
            'user_name',
            'user_emp_id',
            's_min',
            's_max',
            'tc10',
            'tc50',
            'tc90',
            'queued_at',
            'updated_at',
        ]);
    }
};

?>
<div>
   <div class="flex w-full justify-between p-3 border border-neutral-300 dark:border-neutral-700 rounded-full">
        <div class="flex items-center text-xs uppercase text-neutral-500 dark:text-neutral-400 divide-x divide-neutral-300 dark:divide-neutral-700">
            <div class="px-2 text-neutral-900 dark:text-white">{{ __('Rheometer') }}</div>
            <div class="px-2">{{ $batch['model'] }}</div>
            <div class="px-2">{{ $batch['color'] }}</div>
            <div class="px-2">{{ $batch['mcs'] }}</div>
        </div>
        <div>
            <x-pill class="block uppercase" color="{{ $batch['rdc_eval'] === 'queue' ? 'yellow' : ($batch['rdc_eval'] === 'pass' ? 'green' : ($batch['rdc_eval'] === 'fail' ? 'red' : '')) }}">{{ $batch['rdc_eval_human'] }}</x-pill> 
        </div>
    </div>
    <div class="grid grid-cols-2 mt-8">
      <div>
      <div>
      <span class="text-neutral-500 dark:text-neutral-400 text-sm">
            {{ __('Kode alt') . ': ' }}
      </span>
      <span>
            {{ $batch['code_alt'] }}
      </span>
   </div>
   <div>
      <span class="text-neutral-500 dark:text-neutral-400 text-sm">
            {{ __('Mesin') . ': ' }}
      </span>
      <span>
            {{ $machine_number . '. ' . $machine_name }}
      </span>
   </div>
   <div>
      <span class="text-neutral-500 dark:text-neutral-400 text-sm">
            {{ __('Penguji') . ': ' }}
      </span>
      <span>
            {{ $user_name . ' (' . $user_emp_id . ')' }}
      </span>
   </div>
      </div>
      <div>
      <table class="table-auto">
                        <tr>
                            <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                                {{ __('Antri') . ': ' }}
                            </td>
                            <td class="font-mono">
                            {{ $queued_at }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                                {{ __('Selesai') . ': ' }}
                            </td>
                            <td class="font-mono">
                            {{ $updated_at }}
                            </td>
                        </tr>
                    </table>   

      </div>
    </div>

   <div class="grid grid-cols-5 mt-8 text-center">
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
