<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Reactive]
    public $fline;
    public $perPage = 20;

    public function with(): array
    {
        $rows = DB::table('ins_rtc_metrics')
            ->join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')
            ->join('ins_rtc_devices', 'ins_rtc_devices.id', '=', 'ins_rtc_clumps.ins_rtc_device_id')
            ->join('ins_rtc_recipes', 'ins_rtc_recipes.id', '=', 'ins_rtc_clumps.ins_rtc_recipe_id')  // Join with ins_rtc_recipes
            ->select(
                'ins_rtc_devices.line',
                'ins_rtc_clumps.id as clump_id',  // renamed to avoid ambiguity
                'ins_rtc_recipes.name as recipe_name',
                'ins_rtc_recipes.id as recipe_id'
            )
            ->selectRaw('MIN(ins_rtc_metrics.dt_client) as start_time')
            ->selectRaw('MAX(ins_rtc_metrics.dt_client) as end_time')
            ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as duration_seconds')
            // Uncomment and adjust the following lines if needed:
            // ->selectRaw('SUBSTRING_INDEX(GROUP_CONCAT(ins_rtc_metrics.sensor_left ORDER BY ins_rtc_metrics.dt_client DESC), ",", 1) as sensor_left')
            // ->selectRaw('SUBSTRING_INDEX(GROUP_CONCAT(ins_rtc_metrics.sensor_right ORDER BY ins_rtc_metrics.dt_client DESC), ",", 1) as sensor_right')
            ->where('ins_rtc_metrics.dt_client', '>=', Carbon::now()->subDays(90));

        if ($this->fline) {
            $rows->where('ins_rtc_devices.line', $this->fline);
        }

        $rows->groupBy('ins_rtc_devices.line', 'ins_rtc_clumps.id', 'ins_rtc_recipes.id')
            ->orderBy('end_time', 'desc');  // Ordering by the latest dt_client
        $rows = $rows->paginate($this->perPage);

        return [
            'rows' => $rows,
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};

?>

<div wire:poll class="w-full">
    <h1 class="text-2xl mb-6 text-neutral-900 dark:text-neutral-100 px-5">
        {{ __('Ringkasan Gilingan') }}</h1>

    @if (!$rows->count())

        <div wire:key="no-match" class="py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="fa fa-ghost"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
            </div>
        </div>
    @else
        <div wire:key="line-all-rows" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto">
            <table class="table table-sm table-truncate text-neutral-600 dark:text-neutral-400">
                <tr class="uppercase text-xs">
                    <th>{{ __('IDG') }}</th>
                    <th>{{ __('Line') }}</th>
                    <th colspan="2">{{ __('Resep') }}</th>
                    <th>{{ __('Detik') }}</th>
                    <th>{{ __('Waktu mulai') }}</th>
                </tr>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->clump_id }}</td>
                        <td>{{ $row->line }}</td>
                        <td>{{ $row->recipe_id }}</td>
                        <td>{{ $row->recipe_name }}</td>
                        <td>{{ $row->duration_seconds }}</td>
                        <td>{{ $row->start_time }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="flex items-center relative h-16">
            @if (!$rows->isEmpty())
                @if ($rows->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((rows) => {
                                rows.forEach(row => {
                                    if (row.isIntersecting) {
                                        @this.loadMore()
                                    }
                                })
                            })
                            observer.observe(this.$el)
                        }
                    }" x-init="observe"></div>
                    <x-spinner class="sm" />
                @else
                    <div class="mx-auto">{{ __('Tidak ada lagi') }}</div>
                @endif
            @endif
        </div>
    @endif
</div>
