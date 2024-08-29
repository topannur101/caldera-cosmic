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
    public string $updated_at;
    public string $code;
    public string $model;
    public string $color;
    public string $mcs;
    public string $omv_eval;
    public string $omv_eval_human;
    public string $rdc_eval;
    public string $rdc_eval_human;

    #[On('batch-load')]
    public function loadBatch(int $id, string $updated_at, string $code, string $model, string $color, string $mcs, string $omv_eval, string $omv_eval_human, string $rdc_eval, string $rdc_eval_human)
    {
        $this->id               = $id;
        $this->updated_at       = $updated_at ? $updated_at : '-';
        $this->code             = $code ? $code : '-';
        $this->model            = $model ? $model : '-';
        $this->color            = $color ? $color : '-';
        $this->mcs              = $mcs ? $mcs : '-';
        $this->omv_eval         = $omv_eval ? $omv_eval : '-';
        $this->omv_eval_human   = $omv_eval_human ? $omv_eval_human : __('Tak diketahui');
        $this->rdc_eval         = $rdc_eval ? $rdc_eval : '-';
        $this->rdc_eval_human   = $rdc_eval_human ? $rdc_eval_human : __('Tak diketahui');
    }

    public function customReset()
    {
        $this->reset(['id', 'updated_at', 'model', 'color', 'mcs', 'omv_eval', 'omv_eval_human', 'rdc_eval', 'rdc_eval_human']);
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
                {{ __('Info batch') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <dl class="text-neutral-900 divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700 mt-6 text-sm">
            <div class="flex flex-col pb-3">
                <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Kode') }}</dt>
                <dd>{{ $code ?: '-' }}</dd>
            </div>
            <div class="flex flex-col py-3">
                <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Model/Warna/MCS')}}</dt>
                <dd>{{ $model . ' / ' . $color . ' / ' . $mcs }}</dd>
            </div>
            <div class="grid grid-cols-2">
                <div class="flex flex-col py-3">
                    <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Hasil open mill')}}</dt>
                    <dd><x-pill class="uppercase" color="{{ $omv_eval === 'on_time' ? 'green' : ($omv_eval === 'too_late' || $omv_eval === 'too_soon' ? 'red' : '') }}">{{ $omv_eval_human }}</x-pill></dd>
                </div>
                <div class="flex flex-col py-3">
                    <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Hasil rheometer')}}</dt>
                    <dd><x-pill class="uppercase" color="{{ 
                        $rdc_eval === 'queue' ? 'yellow' : 
                        ($rdc_eval === 'pass' ? 'green' : 
                        ($rdc_eval === 'fail' ? 'red' : ''))
                    }}">{{ $rdc_eval_human }}</x-pill></dd>
                </div>
            </div>

            <div class="flex flex-col pt-3">
                <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Diperbarui') }}</dt>
                <dd>{{ $updated_at }}</dd>
            </div>
        </dl>
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
