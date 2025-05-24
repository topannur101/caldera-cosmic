<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use App\Models\InsRtcMetric;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Reactive]
    public $start_at;

    #[Reactive]
    public $end_at;

    #[Reactive]
    public $device_id;

    public $integrity = 0;
    public $days = 0;

    public $perPage = 20;

    public function with(): array
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->addDay();

        $metrics = InsRtcMetric::whereBetween('dt_client', [$start, $end]);
        if ($this->device_id) {
            $metrics->where('device_id', $this->device_id);
        }
        $metrics = $metrics->orderBy('dt_client', 'DESC')->paginate($this->perPage);

        // Statistics
        // hitung tanggal, jam, line
        $u = DB::table('ins_rtc_metrics')
            ->join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')  // Join with ins_rtc_clumps
            ->select(DB::raw('CONCAT(DATE(dt_client), LPAD(HOUR(dt_client), 2, "0"), ins_rtc_clumps.ins_rtc_device_id) as date_hour_device_id'))  // Adjusted for correct path to ins_rtc_device_id
            ->whereBetween('dt_client', [$start, $end]);

        if ($this->device_id) {
            // Make sure to use the correct column from the correct table for the device ID
            $u->where('ins_rtc_clumps.ins_rtc_device_id', $this->device_id);
        }

        $numeratorIntegrity = $u->groupBy('date_hour_device_id')->get()->count();


        // hitung tanggal, line
        $v = DB::table('ins_rtc_metrics')
            ->join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')  // Join with ins_rtc_clumps
            ->select(DB::raw('CONCAT(DATE(dt_client), ins_rtc_clumps.ins_rtc_device_id) as date_device_id'))  // Use correct reference to ins_rtc_device_id
            ->whereBetween('dt_client', [$start, $end]);

        if ($this->device_id) {
            $v->where('ins_rtc_clumps.ins_rtc_device_id', $this->device_id);  // Adjusted to correct column
        }

        $denominatorIntegrity = $v->groupBy('date_device_id')->get()->count() * 21;


        // hitung tanggal
        $w = DB::table('ins_rtc_metrics')
            ->select(DB::raw('DATE(dt_client) as date'))
            ->whereBetween('dt_client', [$start, $end]);
        if ($this->device_id) {
            $w->where('device_id', $this->device_id);
        }
        $this->days = $w->groupBy('date')->get()->count();

        if ($denominatorIntegrity > 0) {
            $this->integrity = (int) (($numeratorIntegrity / $denominatorIntegrity) * 100);
        }

        $x = InsRtcMetric::whereBetween('dt_client', [$start, $end]);
        if ($this->device_id) {
            $x->where('device_id', $this->device_id);
        }

        return [
            'metrics' => $metrics,
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};

?>

<div wire:poll class="overflow-auto w-full">
    <div>
        <div class="flex justify-between items-center mb-6 px-5 py-1">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">
                {{ __('Data Mentah') }}</h1>
            <div class="flex gap-x-2 items-center">
                <div class="text-sm"><span class="text-neutral-500">{{  __('Hari:') . ' ' }}</span><span>{{ $days }}</span></div>
                <div class="text-sm"><span class="text-neutral-500">{{  __('Integritas:') . ' ' }}</span><span>{{ $integrity . '% ' }}</span></div>
                <x-secondary-button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')"><i class="icon-circle-help"></i></x-secondary-button>
            </div>
        </div>
        <x-modal name="raw-stats-info">
            <div class="p-6">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Statistik data mentah') }}
                </h2>
                <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400"><span
                        class="font-bold">{{ __('Hari:') . ' ' }}</span>
                    {{ __('Jumlah hari yang mengandung data. Digunakan sebagai referensi berapa hari kerja pada rentang tanggal yang ditentukan.') }}
                </p>
                <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400"><span
                        class="font-bold">{{ __('Integritas:') . ' ' }}</span>
                    {{ __('Mengindikasikan persentase data yang hadir di tiap jamnya. Contoh: Jika ada data setiap jam selama 21 jam dalam 1 hari, maka integritas bernilai 100%. Jika hanya ada data selama 10.5 jam selama 21 jam dalam 1 hari, maka integritas bernilai 50%') }}
                </p>
                <div class="mt-6 flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Paham') }}
                    </x-primary-button>
                </div>
            </div>
        </x-modal>
        @if (!$metrics->count())
            @if (!$start_at || !$end_at)
                <div wire:key="no-range" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-calendar relative"><i
                                class="icon-circle-help absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih rentang tanggal') }}
                    </div>
                </div>
            @else
                <div wire:key="no-match" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-ghost"></i>
                    </div>
                    <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
                    </div>
                </div>
            @endif
        @else
            <div wire:key="raw-metrics" class="p-0 sm:p-1 overflow-auto">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table">
                    <table class="table table-sm table-truncate text-neutral-600 dark:text-neutral-400">
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
                                <td>{{ $metric->ins_rtc_clump->ins_rtc_recipe_id . '. ' . $metric->ins_rtc_clump->ins_rtc_recipe->name }}</td>
                                <td>{{ $metric->ins_rtc_clump->ins_rtc_recipe->std_mid ?? '' }}</td>
                                <td class="text-xs">{{ ((bool) $metric->is_correcting) ? 'ON' : 'OFF' }}</td>
        
                                <td title="{{ $metric->push_left }}">
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
        
                                <td title="{{ $metric->push_right }}">
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
            <div class="flex items-center relative h-16">
                @if (!$metrics->isEmpty())
                    @if ($metrics->hasMorePages())
                        <div wire:key="more" x-data="{
                            observe() {
                                const observer = new IntersectionObserver((metrics) => {
                                    metrics.forEach(metric => {
                                        if (metric.isIntersecting) {
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
</div>
