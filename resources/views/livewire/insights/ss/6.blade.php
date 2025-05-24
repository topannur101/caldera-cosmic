<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Carbon\Carbon;
use App\Models\InsRtcMetric;
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
        $metrics = InsRtcMetric::join('ins_rtc_clumps', 'ins_rtc_metrics.ins_rtc_clump_id', '=', 'ins_rtc_clumps.id')->latest('dt_client');
        $metrics->where('ins_rtc_clumps.ins_rtc_device_id', $this->device_id);
        $metrics = $metrics->orderBy('dt_client', 'DESC')->limit(10)->get();

        return [
            'metrics' => $metrics,
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
                            href="{{ route('insights.rtc.slideshows') }}"><i class="icon-chevron-left"></i></x-link>
                        {{ __('Data Mentah') }}</div>
                </div>
            </div>
            <div class="pl-4 w-80">
                <div>
                    <div class="text-6xl text-neutral-400 dark:text-white uppercase m-1">{{ __('Line') }}</div>
                    <x-select wire:model.live="device_id" class="text-6xl font-bold w-full">
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
        @if (!$metrics->count())
         <div wire:key="no-match" class="py-20 text-neutral-400 dark:text-neutral-700">
            <div class="text-center text-5xl mb-8">
               <i class="icon-ghost"></i>
            </div>
            <div class="text-center">{{ __('Tidak ada yang cocok') }}
            </div>
      </div>
        @else
            <div wire:key="raw-metrics" class="p-0 sm:p-1 overflow-auto">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table">
                    <table class="table table-xl table-truncate text-neutral-600 dark:text-white">
                        <tr class="uppercase text-xs">
                            <th>{{ __('Line') }}</th>
                            <th>{{ __('IDG') }}</th>
                            <th>{{ __('Resep') }}</th>
                            <th>{{ __('Std') }}</th>
                            <th>{{ __('Oto') }}</th>
                            <th></th>
                            <th>{{ __('Ki') }}</th>
                            <th></th>
                            <th>{{ __('Ka') }}</th>
                            <th>{{ __('Waktu') }}</th>

                        </tr>
                        @foreach ($metrics as $metric)
                            <tr>
                                <td>{{ $metric->ins_rtc_clump->ins_rtc_device->line }}</td>
                                <td>{{ $metric->ins_rtc_clump_id }}
                                <td>{{ $metric->ins_rtc_clump->ins_rtc_recipe_id . '. ' . $metric->ins_rtc_clump->ins_rtc_recipe->name }}
                                </td>
                                <td>{{ $metric->ins_rtc_clump->ins_rtc_recipe->std_mid ?? '' }}</td>
                                <td class="text-xs">{{ ((bool) $metric->is_correcting) ? 'ON' : 'OFF' }}</td>

                                <td>
                                    @switch($metric->action_left)
                                        @case('thin')
                                            <i class="icon-chevron-down"></i>
                                        @break

                                        @case('thick')
                                            <i class="icon-chevron-up"></i>
                                        @break
                                    @endswitch
                                </td>
                                <td>{{ $metric->sensor_left }}</td>

                                <td>
                                    @switch($metric->action_right)
                                        @case('thin')
                                            <i class="icon-chevron-down"></i>
                                        @break

                                        @case('thick')
                                            <i class="icon-chevron-up"></i>
                                        @break
                                    @endswitch
                                </td>
                                <td>{{ $metric->sensor_right }}</td>
                                <td>{{ $metric->dt_client }}</td>

                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
