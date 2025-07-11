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
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        @foreach ($machines as $machine)
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">                
                <div class="flex gap-x-3 items-center px-8 py-4 w-full bg-caldy-200 dark:bg-caldy-700 bg-opacity-20 dark:bg-opacity-20">
                    <div class="w-2 h-2 rounded-full {{ $machine->is_online($offline_minutes) ? 'bg-green-500' : 'bg-red-500' }}"></div>
                    <div class="font-mono text-xl">{{ sprintf('%02d', $machine->line) }}</div>
                    <div class="text-sm text-neutral-500">{{ $machine->latest_metric_obj ? Carbon\Carbon::parse($machine->latest_metric_obj->created_at)->diffForHumans() : __('Belum ada batch terkini') }}</div>
                </div>
                <div>
                    @if ($machine->latest_metric_obj)
                        @php $metric = $machine->latest_metric_obj; @endphp
                        <livewire:insights.ctc.data.metric-detail :id="$metric->id" />
                    @else
                        <div class="text-sm text-neutral-500">{{ __('Belum ada batch terkini') }}</div>
                    @endif
                </div>                
            </div>
        @endforeach
    </div>

    <x-modal name="metric-detail" maxWidth="3xl">
        <livewire:insights.ctc.data.metric-detail />
    </x-modal></div>
