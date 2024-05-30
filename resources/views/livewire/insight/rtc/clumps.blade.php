<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Url;
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
        $clumps = DB::table('ins_rtc_metrics')
            ->join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')
            ->join('ins_rtc_devices', 'ins_rtc_devices.id', '=', 'ins_rtc_clumps.ins_rtc_device_id')
            ->join('ins_rtc_recipes', 'ins_rtc_recipes.id', '=', 'ins_rtc_clumps.ins_rtc_recipe_id')
            ->select('ins_rtc_devices.line', 'ins_rtc_clumps.id as id', 'ins_rtc_recipes.name as recipe_name', 'ins_rtc_recipes.id as recipe_id', 'ins_rtc_recipes.std_mid as std_mid')
            // Waktu mulai batch
            ->selectRaw('MIN(ins_rtc_metrics.dt_client) as start_time')
            // Waktu akhir batch
            ->selectRaw('MAX(ins_rtc_metrics.dt_client) as end_time')
            // Hitung durasi batch
            ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as duration_seconds')
            // Assign Shift 1, 2, 3
            ->selectRaw('CASE WHEN HOUR(MIN(ins_rtc_metrics.dt_client)) BETWEEN 6 AND 13 THEN "1" WHEN HOUR(MIN(ins_rtc_metrics.dt_client)) BETWEEN 14 AND 21 THEN "2" ELSE "3" END AS shift')

            // Calculate mean (avg_left and avg_right)
            ->selectRaw('ROUND(AVG(CASE WHEN ins_rtc_metrics.sensor_left <> 0 THEN ins_rtc_metrics.sensor_left END), 2) as avg_left')
            ->selectRaw('ROUND(AVG(CASE WHEN ins_rtc_metrics.sensor_right <> 0 THEN ins_rtc_metrics.sensor_right END), 2) as avg_right')

            // Calculate standard deviation number (dn_left and dn_right)
            ->selectRaw('ROUND(
                SQRT(
                    SUM(
                        POWER(
                        CASE WHEN ins_rtc_metrics.sensor_left <> 0 
                            THEN ins_rtc_metrics.sensor_left - (SELECT AVG(sensor_left) FROM ins_rtc_metrics WHERE sensor_left <> 0) 
                        END, 2)
                    ) / COUNT(CASE WHEN ins_rtc_metrics.sensor_left <> 0 THEN 1 END)
                ), 2
            ) AS dn_left',)
            ->selectRaw('ROUND(
                SQRT(
                    SUM(
                        POWER(
                        CASE WHEN ins_rtc_metrics.sensor_right <> 0 
                            THEN ins_rtc_metrics.sensor_right - (SELECT AVG(sensor_right) FROM ins_rtc_metrics WHERE sensor_right <> 0) 
                        END, 2)
                    ) / COUNT(CASE WHEN ins_rtc_metrics.sensor_right <> 0 THEN 1 END)
                ), 2
            ) AS dn_right',)
            // Calculate deviation percent (dp_left and dp_right)
            ->selectRaw('ROUND(
                SQRT(
                    AVG(
                        POWER(
                            CASE WHEN ins_rtc_metrics.sensor_left <> 0 THEN ins_rtc_metrics.sensor_left - 
                            (SELECT AVG(sensor_left) FROM ins_rtc_metrics WHERE sensor_left <> 0) END,
                            2
                        )
                    )
                ) / ins_rtc_recipes.std_mid * 100, 1) as dp_left',)
            ->selectRaw(
                'ROUND(
        SQRT(
            AVG(
                POWER(
                    CASE WHEN ins_rtc_metrics.sensor_right <> 0 THEN ins_rtc_metrics.sensor_right - 
                    (SELECT AVG(sensor_right) FROM ins_rtc_metrics WHERE sensor_right <> 0) END,
                    2
                )
            )
        ) / ins_rtc_recipes.std_mid * 100, 1) as dp_right',
            )

            // Untuk menghitung presentase trigger nyala brp kali dalam %
            ->selectRaw('ROUND(SUM(CASE WHEN ins_rtc_metrics.is_correcting = 1 THEN 1 ELSE 0 END) / COUNT(*), 2) as correcting_rate')
            ->whereBetween('ins_rtc_metrics.dt_client', [$start, $end]);

        if ($this->fline) {
            $clumps->where('ins_rtc_devices.line', $this->fline);
        }

        $clumps->groupBy('ins_rtc_clumps.id', 'ins_rtc_devices.line', 'ins_rtc_recipes.name', 'ins_rtc_recipes.id', 'ins_rtc_recipes.std_mid')->orderBy('end_time', 'desc');

        $clumps = $clumps->paginate($this->perPage);

        return [
            'clumps' => $clumps,
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};

?>

<div class="overflow-auto w-full">
    <h1 class="text-2xl mb-6 text-neutral-900 dark:text-neutral-100 px-5">
        {{ __('Ringkasan Gilingan') }}</h1>

    @if (!$clumps->count())
        <div wire:key="no-match" class="py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="fa fa-ghost"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
            </div>
        </div>
    @else
        <div wire:key="clumps-table" class="p-0 sm:p-1 overflow-auto">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('IDG') }}</th>
                        <th>{{ __('L') }}</th>
                        <th>{{ __('S') }}</th>
                        <th>{{ __('Resep') }}</th>
                        <th>{{ __('Std') }}</th>
                        <th>{{ __('Oto') }}</th>
                        <th>{{ __('Rerata | Dev Ki') }}</th>
                        <th>{{ __('Rerata | Dev Ka') }}</th>
                        <th>{{ __('Durasi') }}</th>
                        <th>{{ __('Mulai') }}</th>
                    </tr>
                    @foreach ($clumps as $clump)
                        <tr wire:key="clump-tr-{{ $clump->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'clump'); $dispatch('clump-load', { id: '{{ $clump->id }}'} )">
                            <td>{{ $clump->id }}</td>
                            <td>{{ $clump->line }}</td>
                            <td>{{ $clump->shift }}</td>
                            <td>{{ $clump->recipe_id . '. ' . $clump->recipe_name }}</td>
                            <td>{{ $clump->std_mid }}</td>
                            <td>{{ $clump->correcting_rate > 0.8 ? 'ON' : 'OFF' }}</td>
                            <td>{{ $clump->avg_left ? $clump->avg_left . ' | ' . $clump->dn_left . ' (' . $clump->dp_left . '%)' : __('Tak ada data') }}
                            </td>
                            <td>{{ $clump->avg_right ? $clump->avg_right . ' | ' . $clump->dn_right . ' (' . $clump->dp_right . '%)' : __('Tak ada data') }}
                            </td>
                            <td>{{ Carbon::createFromTimestampUTC($clump->duration_seconds)->format('i:s') }}</td>
                            <td>{{ $clump->start_time }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="flex items-center relative h-16">
            @if (!$clumps->isEmpty())
                @if ($clumps->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((clumps) => {
                                clumps.forEach(clump => {
                                    if (clump.isIntersecting) {
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
    <div wire:key="clump">
        <x-modal name="clump" maxWidth="2xl">
            <livewire:insight.rtc.clump />
        </x-modal>
    </div>
</div>
