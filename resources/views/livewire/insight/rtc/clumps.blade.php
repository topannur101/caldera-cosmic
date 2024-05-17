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
    public $start_at;

    #[Reactive]
    public $fline;
    public $perPage = 20;

    public function with(): array
    {
        $start = Carbon::parse($this->start_at)->addHours(6);
        $end = $start->copy()->addHours(24);
        $rows = DB::table('ins_rtc_metrics')
            ->join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')
            ->join('ins_rtc_devices', 'ins_rtc_devices.id', '=', 'ins_rtc_clumps.ins_rtc_device_id')
            ->join('ins_rtc_recipes', 'ins_rtc_recipes.id', '=', 'ins_rtc_clumps.ins_rtc_recipe_id')
            ->select(
                'ins_rtc_devices.line',
                'ins_rtc_clumps.id as clump_id',
                'ins_rtc_recipes.name as recipe_name',
                'ins_rtc_recipes.id as recipe_id',
                'ins_rtc_recipes.std_mid as std_mid'
            )
            ->selectRaw('MIN(ins_rtc_metrics.dt_client) as start_time')
            ->selectRaw('MAX(ins_rtc_metrics.dt_client) as end_time')
            ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as duration_seconds')
            ->selectRaw('CASE WHEN HOUR(MIN(ins_rtc_metrics.dt_client)) BETWEEN 6 AND 13 THEN "1" WHEN HOUR(MIN(ins_rtc_metrics.dt_client)) BETWEEN 14 AND 21 THEN "2" ELSE "3" END AS shift')
            ->selectRaw('ROUND((AVG(CASE WHEN ins_rtc_metrics.sensor_left <> 0 THEN ins_rtc_metrics.sensor_left END)), 2) as avg_left')
            ->selectRaw('ROUND((AVG(CASE WHEN ins_rtc_metrics.sensor_right <> 0 THEN ins_rtc_metrics.sensor_right END)), 2) as avg_right')
            ->selectRaw('ROUND((AVG(CASE WHEN ins_rtc_metrics.sensor_left <> 0 THEN ins_rtc_metrics.sensor_left END) - ins_rtc_recipes.std_mid), 2) as dn_left')
            ->selectRaw('ROUND((AVG(CASE WHEN ins_rtc_metrics.sensor_right <> 0 THEN ins_rtc_metrics.sensor_right END) - ins_rtc_recipes.std_mid), 2) as dn_right')
            ->selectRaw('ROUND((AVG(CASE WHEN ins_rtc_metrics.sensor_left <> 0 THEN ins_rtc_metrics.sensor_left END) - ins_rtc_recipes.std_mid) / ins_rtc_recipes.std_mid * 100, 0) as dp_left')
            ->selectRaw('ROUND((AVG(CASE WHEN ins_rtc_metrics.sensor_right <> 0 THEN ins_rtc_metrics.sensor_right END) - ins_rtc_recipes.std_mid) / ins_rtc_recipes.std_mid * 100, 0) as dp_right')
            ->selectRaw('ROUND(SUM(CASE WHEN ins_rtc_metrics.is_correcting = 1 THEN 1 ELSE 0 END) / COUNT(*), 2) as correcting_rate')
            ->whereBetween('ins_rtc_metrics.dt_client', [$start, $end]);

        if ($this->fline) {
            $rows->where('ins_rtc_devices.line', $this->fline);
        }

        $rows->groupBy('ins_rtc_devices.line', 'ins_rtc_clumps.id', 'ins_rtc_recipes.id')
            ->orderBy('end_time', 'desc');

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
                    <th>{{ __('L') }}</th>
                    <th>{{ __('S') }}</th>
                    <th>{{ __('Resep') }}</th>
                    <th>{{ __('STD') }}</th>
                    <th>{{ __('KO') }}</th>
                    <th>{{ __('AVG | Dev L') }}</th>
                    <th>{{ __('AVG | Dev R') }}</th>
                    <th>{{ __('Durasi') }}</th>
                    <th>{{ __('Waktu mulai') }}</th>
                </tr>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->clump_id }}</td>
                        <td>{{ $row->line }}</td>
                        <td>{{ $row->shift }}</td>
                        <td>{{ $row->recipe_id . '. ' . $row->recipe_name }}</td>
                        <td>{{ $row->std_mid }}</td>
                        <td class="text-xs">{{ ($row->correcting_rate > 0.8) ? 'ON' : 'OFF' }}</td>
                        </td>
                        <td>{{ $row->avg_left . ' | ' . $row->dn_left . ' ('. $row->dp_left . '%)'}}</td>
                        <td>{{ $row->avg_left . ' | ' . $row->dn_right . ' ('. $row->dp_right . '%)'}}</td>
                        <td>{{ Carbon::createFromTimestampUTC($row->duration_seconds)->format('i:s') }}</td>
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
