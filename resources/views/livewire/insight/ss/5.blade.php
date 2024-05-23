<?php

use Livewire\Volt\Component;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\DB;
use App\Models\InsRtcDevice;

new class extends Component {

    #[Url]
    public $device_id;
    public $devices = [];

    public function mount(): void
    {
        $this->devices = InsRtcDevice::all();
    }

    public function with(): array
    {
        $start = Carbon::now()->startOfDay()->addHours(6);
        $end = $start->copy()->addHours(24);
        $clumps = DB::table('ins_rtc_metrics')
            ->join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')
            ->join('ins_rtc_devices', 'ins_rtc_devices.id', '=', 'ins_rtc_clumps.ins_rtc_device_id')
            ->join('ins_rtc_recipes', 'ins_rtc_recipes.id', '=', 'ins_rtc_clumps.ins_rtc_recipe_id')
            ->select(
                'ins_rtc_devices.line',
                'ins_rtc_clumps.id as id',
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

            $clumps->where('ins_rtc_devices.id', $this->device_id);

        $clumps->groupBy('ins_rtc_devices.line', 'ins_rtc_clumps.id', 'ins_rtc_recipes.id')
            ->orderBy('end_time', 'desc');

        $clumps = $clumps->limit(5)->get();

        return [
            'clumps' => $clumps,
        ];
    }

};

?>

<div wire:poll.30s class="w-full h-screen p-4">
    <div class="p-4">
        <div class="flex w-full justify-between items-center">
            <div class="truncate">
                <div>
                    <div class="text-6xl text-neutral-400 uppercase m-1"><x-link class="inline-block"
                            href="{{ route('insight.rtc.slideshows') }}"><i class="fa fa-fw fa-chevron-left"></i></x-link>
                        {{ __('Ringkasan Gilingan') }}</div>
                </div>
            </div>
            <div class="pl-4 w-80">
                <div>
                    <div class="text-6xl text-neutral-400 dark:text-white uppercase m-1">{{ __('Line') }}</div>
                    <x-select wire:model.live="device_id" class="text-6xl font-bold">
                        <option value=""></option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}">{{ $device->line }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
        </div>
    </div>
    <div wire:key="table-container" class="text-4xl mt-10">
        @if (!$clumps->count())
            <div wire:key="no-match" class="py-20 text-neutral-400 dark:text-neutral-700">
                <div class="text-center text-5xl mb-8">
                    <i class="fa fa-ghost"></i>
                </div>
                <div class="text-center">{{ __('Tidak ada yang cocok') }}
                </div>
            </div>
        @else
            <div wire:key="clumps-table" class="p-0 sm:p-1 overflow-auto">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                    <table class="table table-xl table-truncate text-neutral-600 dark:text-white">
                        <tr class="uppercase text-xs">
                            <th>{{ __('IDG') }}</th>
                            <th>{{ __('Resep') }}</th>
                            <th>{{ __('Std') }}</th>
                            <th>{{ __('Oto') }}</th>
                            <th>{{ __('Rerata | Dev Ki') }}</th>
                            <th>{{ __('Rerata | Dev Ka') }}</th>
                            <th>{{ __('Durasi') }}</th>
                            <th>{{ __('Mulai') }}</th>
                        </tr>
                        @foreach ($clumps as $clump)
                            <tr>
                                <td>{{ $clump->id }}</td>
                                <td>{{ $clump->recipe_id . '. ' . $clump->recipe_name }}</td>
                                <td>{{ $clump->std_mid }}</td>
                                <td class="text-xs">{{ $clump->correcting_rate > 0.8 ? 'ON' : 'OFF' }}</td>
                                <td>{{ $clump->avg_left . ' | ' . $clump->dn_left . ' (' . $clump->dp_left . '%)' }}
                                </td>
                                <td>{{ $clump->avg_left . ' | ' . $clump->dn_right . ' (' . $clump->dp_right . '%)' }}
                                </td>
                                <td>{{ Carbon::createFromTimestampUTC($clump->duration_seconds)->format('i:s') }}</td>
                                <td>{{ Carbon::parse($clump->start_time)->format('H:m') }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
