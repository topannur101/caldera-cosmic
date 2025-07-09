<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\InsCtcMachine;

new #[Layout('layouts.app')] class extends Component
{
    public int $offline_minutes = 180;

    public function with(): array
    {
        $machines = InsCtcMachine::orderBy('line')->get();

        $machines->each(function ($machine) {
            $machine->latest_metric_obj = $machine->ins_ctc_metrics()
                ->latest('created_at')
                ->with(['ins_ctc_recipe', 'ins_rubber_batch'])
                ->first();
        });

        return [
            'machines' => $machines,
        ];
    }
};
?>

<div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($machines as $machine)
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 flex gap-4 items-start">
                    <div class="flex items-center">
                        <div class="font-mono text-xl">{{ sprintf('%02d', $machine->line) }}</div>
                        <div class="w-2 h-2 rounded-full ml-2 {{ $machine->is_online($offline_minutes) ? 'bg-green-500' : 'bg-red-500' }}"></div>
                    </div>
                    <div class="grow">
                        @if ($machine->latest_metric_obj)
                            @php $metric = $machine->latest_metric_obj; @endphp
                            <div class="text-sm font-medium">
                                {{ $metric->ins_rubber_batch->code ?? __('Tanpa kode') }}
                            </div>
                            <div class="text-xs text-neutral-500">
                                {{ $metric->ins_ctc_recipe->name ?? '' }}
                            </div>
                            <div class="text-xs text-neutral-500">
                                {{ $metric->created_at->format('d M Y H:i') }}
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                @foreach (['avg','mae','ssd','correction'] as $key)
                                    @php $eval = $metric->all_evaluations[$key] ?? null; @endphp
                                    <i class="{{ ($eval['is_good'] ?? false) ? 'icon-circle-check' : 'icon-circle-x' }} {{ $eval['icon_color'] ?? 'text-neutral-400' }}"></i>
                                @endforeach
                                <x-text-button type="button" class="ml-auto px-2" x-on:click="$dispatch('open-modal', 'metric-detail'); $dispatch('metric-detail-load', { id: '{{ $metric->id }}'})">
                                    <i class="icon-eye"></i>
                                </x-text-button>
                            </div>
                        @else
                            <div class="text-sm text-neutral-500">{{ __('Belum ada metrik') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-modal name="metric-detail" maxWidth="3xl">
        <livewire:insights.ctc.data.metric-detail />
    </x-modal>
</div>