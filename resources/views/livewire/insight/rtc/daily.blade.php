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
        // Define the date range
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->start_at)->addDay();

        // Initialize the query builder for devices
        $devicesQuery = InsRtcDevice::query();

        if ($this->fline) {
            $devicesQuery->where('line', $this->fline);
        }

        // Fetch devices with their metrics in one go
        $devices = $devicesQuery->with(['ins_rtc_clumps', 'ins_rtc_clumps.ins_rtc_metrics' => function ($query) use ($start, $end) {
            $query->whereBetween('dt_client', [$start, $end]);
        }])
        ->get()
        ->map(function ($device) use ($start, $end) {
            $total_time = DB::table('ins_rtc_metrics')
                ->join('ins_rtc_clumps', 'ins_rtc_metrics.ins_rtc_clump_id', '=', 'ins_rtc_clumps.id')
                ->where('ins_rtc_clumps.ins_rtc_device_id', $device->id)
                ->whereBetween('ins_rtc_metrics.dt_client', [$start, $end])
                ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as total_time')
                ->value('total_time');
            
            $device->total_time = $total_time ? $total_time : 0;

            $device->durations = DB::table('ins_rtc_metrics')
                ->join('ins_rtc_clumps', 'ins_rtc_metrics.ins_rtc_clump_id', '=', 'ins_rtc_clumps.id')
                ->where('ins_rtc_clumps.ins_rtc_device_id', $device->id)
                ->whereBetween('ins_rtc_metrics.dt_client', [$start, $end])
                ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as duration')
                ->groupBy('ins_rtc_clumps.id')
                ->pluck('duration')
                ->toArray();

            $device->active_time = array_sum($device->durations);
            $device->passive_time = $device->total_time - $device->active_time;
            $device->avg_clump_duration = empty($device->durations) ? 0 : (int) (array_sum($device->durations) / count($device->durations));

            return $device;
        });

        return [
            'devices' => $devices
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
                <tr class="uppercase text-xs">
                    <th>{{ __('Line') }}</th>
                    <th>{{ __('Gilingan') }}</th>
                    <th>{{ __('Rerata') }}</th>
                    <th>{{ __('W. Total') }}</th>
                    <th>{{ __('W. Aktif') }}</th>
                    <th>{{ __('W. Pasif') }}</th>
                </tr>
                @foreach ($devices as $device)
                    <tr>
                        <td>{{ $device->line }}</td>
                        <td>{{ $device->ins_rtc_clumps->count() }}</td>
                        <td>{{ Carbon::createFromTimestampUTC($device->avg_clump_duration)->format('i:s') }}</td>
                        <td>{{ Carbon::createFromTimestampUTC($device->total_time)->format('H:i:s') }}</td>
                        <td>{{ Carbon::createFromTimestampUTC($device->active_time)->format('H:i:s') }}</td>
                        <td>{{ Carbon::createFromTimestampUTC($device->passive_time)->format('H:i:s') }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</div>
