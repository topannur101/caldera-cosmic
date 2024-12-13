<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\InsRubberBatch;
use App\Models\InsRdcTest;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Validator;

new #[Layout('layouts.app')] 
class extends Component {

    public string $code = '';

    #[On('updated')]
    public function with(): array
    {
        return [
            'batches' => InsRubberBatch::where('rdc_eval', 'queue')->orderBy('updated_at')->get()
        ];
    }

    public function batchQuery()
    {
        $this->code = strtoupper(trim(str_replace('#', '', $this->code)));

        $validator = Validator::make(
            ['code' => $this->code ],
            ['code' => 'required|string|min:1|max:20'],
            [
                'required'  => __('Kode wajib diisi'),
                'string'    => __('Kode harus berupa teks/string.'),
                'min'       => __('Kode minimal 1 karakter'),
                'max'       => __('Kode maksimal 50 karakter')
            ]
        );

        if ($validator->fails()) {

            $errors = $validator->errors();
            $error = $errors->first('code');
            $this->js('notyfError("'.$error.'")'); 

        } else {

            $batch = InsRubberBatch::firstOrCreate(
                ['code' => $this->code]
            );
            $this->js('$dispatch("open-modal", "batch-info"); $dispatch("batch-load", { 
                id: ' . $batch->id . ', 
                updated_at_human: "' . $batch->updated_at->diffForHumans() . '", 
                code: "' . $batch->code . '", 
                model: "' . $batch->model . '", 
                color: "' . $batch->color . '", 
                mcs: "' . $batch->mcs . '", 
                code_alt: "' . $batch->code_alt . '", 
                omv_eval: "' . $batch->omv_eval . '", 
                omv_eval_human: "' . $batch->omvEvalHuman() . '",
                rdc_eval: "' . $batch->rdc_eval . '", 
                rdc_eval_human: "' . $batch->rdcEvalHuman() . '",
                rdc_tests_count: "' . $batch->ins_rdc_tests->count() . '"
            })');
            $this->reset(['code']);
        }
    }
};

?>

<x-slot name="title">{{ __('Pendataan Rheometer') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-rdc></x-nav-insights-rdc>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div class="flex flex-col gap-y-8 sm:flex-row">
        <h1 class="grow text-2xl text-neutral-900 dark:text-neutral-100 px-8">
            {{ __('Antrian pengujian') }}</h1>
        <div class="px-8">
            <form wire:submit="batchQuery" class="btn-group">
                <x-text-input class="w-20" wire:model="code" id="rdc-code" placeholder="{{ __('Kode') }}"></x-text-input->
                <x-secondary-button type="submit"><i class="fa fa-fw fa-chevron-right" wire:loading.class="hidden"></i><i class="fa fa-fw fa-spinner fa-spin-pulse hidden" wire:loading.class.remove="hidden"></i></x-secondary-button>
            </form>
        </div>
    </div>
    <div wire:key="batch-info">
        <x-modal name="batch-info">
            <livewire:insight.rdc.index-batch-info />
        </x-modal>
    </div>
    @can('manage', InsRdcTest::class)
    <div wire:key="batch-test-create">
        <x-modal name="batch-test-create">
            <livewire:insight.rdc.index-batch-test-create />
        </x-modal>
    </div>
    @endcan
    <div wire:poll.30s class="overflow-auto w-full my-8">
        <div class="p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                <table wire:key="rdc-index-table" class="table">
                    <tr>
                        <th>{{ __('Nomor batch') }}</th>
                        <th>{{ __('Model/Warna/MCS') }}</th>
                        <th>{{ __('Diperbarui') }}</th>
                        <th></th>
                    </tr>
                    @foreach ($batches as $batch)
                        <tr wire:key="batch-tr-{{ $batch->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'batch-test-create'); $dispatch('batch-test-create', { id: '{{ $batch->id }}'})">
                            <td>
                                {{ $batch->code }}
                            </td>
                            <td>
                                {{ ($batch->model ? $batch->model : '-') . ' / ' . ($batch->color ? $batch->color : '-') . ' / ' . ($batch->mcs ? $batch->mcs : '-') }}
                            </td>
                            <td>
                                {{ $batch->updated_at }}
                            </td>
                            <td>
                                {{ $batch->updated_at->diffForHumans() }}
                            </td>
                        </tr>
                    @endforeach
                </table>
                <div wire:key="batches-none">
                    @if (!$batches->count())
                        <div class="text-center py-12">
                            {{ __('Antrian kosong') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    document.addEventListener('keypress', (event) => {
        if (event.key === '#') {
            const rdcCodeInput = document.getElementById('rdc-code');
            if (rdcCodeInput) {
                event.preventDefault();
                rdcCodeInput.focus();
            }
        }
    });
</script>
@endscript
