<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\InsRdcTest;
// use Illuminate\Validation\Rule;

// use App\Models\User;
use App\Models\InsRubberBatch;
// use Livewire\Attributes\Renderless;
// use Illuminate\Support\Facades\Gate;

new #[Layout('layouts.app')] 
class extends Component {

    public int $id;
    public string $updated_at;
    public string $code;
    public string $model;
    public string $color;
    public string $mcs;
    public string $rdc_eval;

    // public function rules()
    // {
    //     return [
    //         'actions' => ['array'],
    //         'actions.*' => ['string'],
    //     ];
    // }

    #[On('batch-load')]
    public function loadBatch(int $id)
    {
        $batch = InsRubberBatch::find($id);
        
        if ($batch) {
            $this->id           = $batch->id;
            $this->updated_at   = $batch->updated_at ?? '';
            $this->code         = $batch->code;
            $this->model        = $batch->model ?? '-';
            $this->color        = $batch->color ?? '-';
            $this->mcs          = $batch->mcs ?? '-';
            $this->rdc_eval     = $batch->rdc_eval ?? '-';
            // $this->actions = json_decode($batch->actions ?? '[]', true);
        } else {
            $this->handleNotFound();
        }

    }

    // public function with(): array
    // {
    //     return [
    //         'is_superuser' => Gate::allows('superuser'),
    //     ];
    // }

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

    public function customReset()
    {
        $this->reset(['id', 'model', 'color', 'mcs', 'rdc_eval']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('notyfError("' . __('Tidak ditemukan') . '")');
        $this->dispatch('updated');
    }

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
                <dd>{{ $code }}</dd>
            </div>
            <div class="flex flex-col py-3">
                <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Model/Warna')}}</dt>
                <dd>{{ $model . ' / ' . $color}}</dd>
            </div>
            <div class="flex flex-col py-3">
                <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('MCS')}}</dt>
                <dd>{{ $mcs }}</dd>
            </div>
            <div class="flex flex-col py-3">
                <dt class="mb-1 text-neutral-500 dark:text-neutral-400">{{ __('Evaluasi uji rheo')}}</dt>
                <dd>{{ $rdc_eval }}</dd>
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
