<?php

use Livewire\Volt\Component;

new class extends Component
{
    public int $id;

    public array $rubberBatch = [];

    public string $view = '';

    public function mount() {}

    private function loadBatch() {
        
    }

    public function customReset()
    {
        $this->reset(['id']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('notyfError("'.__('Tidak ditemukan').'")');
        $this->dispatch('updated');
    }
};

?>
<div>
    <div class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Info batch') }}
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
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Open mill')}}</div>
                                <x-pill class="uppercase"
                                color="{{ $batch_omv_eval === 'on_time' ? 'green' : ($batch_omv_eval === 'on_time_manual' ? 'yellow' : ($batch_omv_eval === 'too_late' || $batch_omv_eval === 'too_soon' ? 'red' : 'neutral')) }}">{{ $batch_omv_eval_human }}</x-pill>    
                            </li>
                            <li class="mb-6 ms-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Calendar')}}</div>
                                <x-pill class="uppercase"
                                color="neutral">N/A</x-pill>
                            </li>
                            <li class="mb-6 ms-4">
                                <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Rheometer')}}</div>
                                <x-pill class="uppercase"
                                color="{{ $batch_rdc_eval === 'queue' ? 'yellow' : ($batch_rdc_eval === 'pass' ? 'green' : ($batch_rdc_eval === 'fail' ? 'red' : 'neutral')) }}">{{ $batch_rdc_eval_human }}</x-pill> 

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
            <div class="flex flex-col pt-6">
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
