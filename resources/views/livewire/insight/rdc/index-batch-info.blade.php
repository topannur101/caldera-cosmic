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
    public string $updated_at_human;
    public string $code;
    public string $model;
    public string $color;
    public string $mcs;
    public string $code_alt;
    public string $omv_eval;
    public string $omv_eval_human;
    public string $rdc_eval;
    public string $rdc_eval_human;
    public int $rdc_tests_count;

    #[On('batch-load')]
    public function loadBatch(int $id, string $updated_at_human, string $code, string $model, string $color, string $mcs, string $code_alt, string $omv_eval, string $omv_eval_human, string $rdc_eval, string $rdc_eval_human, int $rdc_tests_count)
    {
        $this->id               = $id;
        $this->code             = $code ?: '-';
        $this->model            = $model ?: '-';
        $this->color            = $color ?: '-';
        $this->mcs              = $mcs ?: '-';
        $this->code_alt         = $code_alt ?: '-';
        $this->omv_eval         = $omv_eval ?: '-';
        $this->omv_eval_human   = $omv_eval_human ?: '-';
        $this->rdc_eval         = $rdc_eval ?: '-';
        $this->rdc_eval_human   = $rdc_eval_human ?: '-';
        $this->rdc_tests_count  = $rdc_tests_count ?: 0;
        $this->updated_at_human = $updated_at_human ?: '-';
    }

    public function customReset()
    {
        $this->reset(['id', 'updated_at_human', 'model', 'color', 'mcs', 'code_alt', 'omv_eval', 'omv_eval_human', 'rdc_eval', 'rdc_eval_human', 'rdc_tests_count']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('notyfError("' . __('Tidak ditemukan') . '")');
        $this->dispatch('updated');
    }

    #[Renderless]
    public function addToQueue()
    {
        $batch = InsRubberBatch::find($this->id);
        if ($batch) {
            $batch->update([
                'rdc_eval' => 'queue'
            ]);
            $this->js('$dispatch("close")');
            $this->js('notyfSuccess("' . __('Ditambahkan ke antrian') . '")');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
        }      
    }
};

?>
<div>
    <div class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Informasi batch') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="flex gap-x-6 mt-6">
            <div>                        
                <ol class="relative border-s border-neutral-200 dark:border-neutral-700 mt-2">                  
                    <li class="mb-6 ms-4">
                        <div class="absolute w-3 h-3 bg-neutral-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-neutral-900 dark:bg-neutral-700"></div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('OMV')}}</div>
                        <x-pill class="uppercase"
                        color="{{ $omv_eval === 'on_time' ? 'green' : ($omv_eval === 'too_late' || $omv_eval === 'too_soon' ? 'red' : '') }}">{{ $omv_eval_human }}</x-pill>    
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
                        color="{{ $rdc_eval === 'queue' ? 'yellow' : ($rdc_eval === 'pass' ? 'green' : ($rdc_eval === 'fail' ? 'red' : '')) }}">{{ $rdc_eval_human }}</x-pill> 

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
                            {{ $code }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                            {{ __('Kode alt') . ': ' }}
                        </td>
                        <td>
                            {{ $code_alt }}
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
                    <tr>
                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                            {{ __('Uji rheo') . ': ' }}
                        </td>
                        <td>
                            {{ $rdc_tests_count . ' kali' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                            {{ __('Diperbarui') . ': ' }}
                        </td>
                        <td>
                            {{ $updated_at_human }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        @can('manage', InsRdcTest::class)
            <div class="mt-6 flex justify-end">
                {{-- <div>
                    <x-text-button type="button" class="uppercase text-xs text-red-500" wire:click="delete"
                        wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}">
                        {{ __('Cabut') }}
                    </x-text-button>
                </div> --}}
                @if($rdc_eval == 'queue')
                <x-secondary-button type="button" disabled>
                    {{ __('Sudah diantrikan') }}
                </x-secondary-button>
                @elseif($rdc_eval == 'pass')
                <x-secondary-button type="button" disabled>
                    {{ __('Sudah PASS') }}
                </x-secondary-button>
                @else
                <x-secondary-button type="button" wire:click="addToQueue">
                    {{ __('Tambah ke antrian') }}
                </x-secondary-button>
                @endif
            </div>
        @endcan
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
