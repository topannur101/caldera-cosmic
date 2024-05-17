<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use App\Models\InsRtcDevice;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Reactive]
    public $start_at;

    #[Reactive]
    public $fline;

    public function with(): array
    {
        // Define the date range for the entire day
        $start = Carbon::parse($this->start_at)->startOfDay();
        $end = $start->copy()->addDay()->startOfDay();

        // Define the shifts
        $shifts = [
            1 => [$start->copy()->addHours(6), $start->copy()->addHours(14)],
            2 => [$start->copy()->addHours(14), $start->copy()->addHours(22)],
            3 => [$start->copy()->addHours(22), $start->copy()->addHours(30)],
        ];

        // Initialize the query builder for devices
        $devicesQuery = InsRtcDevice::query();

        if ($this->fline) {
            $devicesQuery->where('line', $this->fline);
        }

        // Fetch devices with their metrics in one go
        $devices = $devicesQuery->get()->map(function ($device) use ($shifts) {
            $device->shifts = collect();

            foreach ($shifts as $shift => [$shiftStart, $shiftEnd]) {
                // Query to get total_time and clump_count
                $metrics = DB::table('ins_rtc_metrics')
                    ->join('ins_rtc_clumps', 'ins_rtc_metrics.ins_rtc_clump_id', '=', 'ins_rtc_clumps.id')
                    ->where('ins_rtc_clumps.ins_rtc_device_id', $device->id)
                    ->whereBetween('ins_rtc_metrics.dt_client', [$shiftStart, $shiftEnd])
                    ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as total_time, COUNT(DISTINCT ins_rtc_clumps.id) as clump_count')
                    ->first();

                $total_time = $metrics->total_time;
                $clump_count = $metrics->clump_count;

                // Query to get durations
                $durations = DB::table('ins_rtc_metrics')
                    ->join('ins_rtc_clumps', 'ins_rtc_metrics.ins_rtc_clump_id', '=', 'ins_rtc_clumps.id')
                    ->where('ins_rtc_clumps.ins_rtc_device_id', $device->id)
                    ->whereBetween('ins_rtc_metrics.dt_client', [$shiftStart, $shiftEnd])
                    ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as duration')
                    ->groupBy('ins_rtc_clumps.id')
                    ->pluck('duration')
                    ->toArray();

                $device->shifts[$shift] = [
                    'clump_count' => $clump_count,
                    'total_time' => $total_time ? $total_time : 0,
                    'durations' => $durations,
                    'active_time' => array_sum($durations),
                    'passive_time' => $total_time ? $total_time - array_sum($durations) : 0,
                    'avg_clump_duration' => empty($durations) ? 0 : (int) (array_sum($durations) / count($durations)),
                ];
            }

            return $device;
        });

        return [
            'devices' => $devices,
        ];
    }
};

?>

<div class="w-full">
    <h1 class="text-2xl mb-6 text-neutral-900 dark:text-neutral-100 px-5">
        {{ __('Ringkasan Harian') }}</h1>

    @if (!$devices->count())

        <div wire:key="no-match" class="py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="fa fa-ghost"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
            </div>
        </div>
    @else
        <div wire:key="line-all-devices" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto">
            <table class="table table-sm table-truncate text-neutral-600 dark:text-neutral-400">
                <thead>
                    <tr class="uppercase text-xs">
                        <th>{{ __('Line') }}</th>
                        <th>{{ __('Shift') }}</th>
                        <th>{{ __('Gilingan') }}</th>
                        <th>{{ __('RDG') }}</th>
                        <th>{{ __('W. Total') }}</th>
                        <th>{{ __('W. Aktif') }}</th>
                        <th>{{ __('W. Pasif') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($devices as $device)
                        @foreach ($device->shifts as $shift => $data)
                            <tr>
                                @if ($loop->first)
                                    <td rowspan="3">{{ $device->line }}</td>
                                @endif
                                <td>{{ $shift }}</td>
                                <td>{{ $data['clump_count'] }}</td>
                                <td>{{ Carbon::createFromTimestampUTC($data['avg_clump_duration'])->format('i:s') }}</td>
                                <td>{{ Carbon::createFromTimestampUTC($data['total_time'])->format('H:i:s') }}</td>
                                <td>{{ Carbon::createFromTimestampUTC($data['active_time'])->format('H:i:s') }}</td>
                                <td>{{ Carbon::createFromTimestampUTC($data['passive_time'])->format('H:i:s') }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
