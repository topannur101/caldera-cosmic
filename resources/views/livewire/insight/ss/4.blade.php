<?php

use Livewire\Volt\Component;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\InsRtcDevice;
use Illuminate\Database\Eloquent\Builder;

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
        // Define the date range for the entire day
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addDay()->startOfDay();

        // Define the shifts
        $shifts = [
            1 => [$start->copy()->addHours(6), $start->copy()->addHours(14)],
            2 => [$start->copy()->addHours(14), $start->copy()->addHours(22)],
            3 => [$start->copy()->addHours(22), $start->copy()->addHours(30)],
        ];

        // Fetch devices with their metrics in one go
        $rows = InsRtcDevice::where('id', $this->device_id)->get()->map(function ($row) use ($shifts) {
            $row->shifts = collect();

            foreach ($shifts as $shift => [$shiftStart, $shiftEnd]) {
                // Query to get durations
                $durations = DB::table('ins_rtc_metrics')
                    ->join('ins_rtc_clumps', 'ins_rtc_metrics.ins_rtc_clump_id', '=', 'ins_rtc_clumps.id')
                    ->where('ins_rtc_clumps.ins_rtc_device_id', $row->id)
                    ->whereBetween('ins_rtc_metrics.dt_client', [$shiftStart, $shiftEnd])
                    ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as duration')
                    ->groupBy('ins_rtc_clumps.id')
                    ->pluck('duration')
                    ->toArray();

                $row->shifts[$shift] = [
                    'clump_count' => count($durations),
                    'durations' => $durations,
                    'active_time' => array_sum($durations),
                    'passive_time' => 28800 - array_sum($durations),
                    'avg_clump_duration' => empty($durations) ? 0 : (int) (array_sum($durations) / count($durations)),
                ];
            }

            return $row;
        });

        return [
            'rows' => $rows,
        ];
    }
};

?>

<div wire:poll.30s class="w-full h-screen p-4">
   <div class="p-4">
       <div class="flex w-full justify-between items-center">
           <div class="truncate">
               <div>
                   <div class="text-6xl text-neutral-400 uppercase m-1"><x-link class="inline-block" href="{{ route('insight.rtc.slideshows')}}"><i class="fa fa-fw fa-chevron-left"></i></x-link>
                       {{ __('Ringkasan Harian') }}</div>
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
      @if (!$rows->count())

      <div wire:key="no-match" class="py-20 text-neutral-400 dark:text-neutral-700">
        <div class="text-center text-5xl mb-8">
            <i class="fa fa-ghost"></i>
        </div>
        <div class="text-center">{{ __('Tidak ada yang cocok') }}
        </div>
    </div>
    @else
        <div wire:key="line-all-devices" class="p-0 sm:p-1 overflow-auto">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-2xl table-truncate text-neutral-600 dark:text-white">
                    <thead>
                        <tr class="uppercase text-xs">
                            <th>{{ __('Shift') }}</th>
                            <th>{{ __('Gilingan') }}</th>
                            <th>{{ __('Rerata') }}</th>
                            <th>{{ __('W. Aktif') }}</th>
                            <th>{{ __('W. Pasif') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @foreach ($row->shifts as $shift => $data)
                                <tr>
                                    <td>{{ $shift }}</td>
                                    <td>{{ $data['clump_count'] }}</td>
                                    <td>{{ Carbon::createFromTimestampUTC($data['avg_clump_duration'])->format('i:s') }}
                                    </td>
                                    <td>{{ Carbon::createFromTimestampUTC($data['active_time'])->format('H:i:s') }}
                                    </td>
                                    <td>{{ Carbon::createFromTimestampUTC($data['passive_time'])->format('H:i:s') }}
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
   </div>
</div>

