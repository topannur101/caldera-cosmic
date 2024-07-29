<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use App\Models\InsOmvMetric;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Reactive]
    public $start_at;

    #[Reactive]
    public $end_at;

    #[Reactive]
    public $device_id;

    #[Reactive]
    public $fquery;

    #[Reactive]
    public $ftype;

    // public $integrity = 0;
    public $days = 0;

    public $perPage = 20;

    public function with(): array
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->addDay();

        $metrics = InsOmvMetric::whereBetween('start_at', [$start, $end]);

        $metrics = InsOmvMetric::join('ins_omv_recipes', 'ins_omv_metrics.ins_omv_recipe_id', '=', 'ins_omv_recipes.id')
        ->join('users as user1', 'ins_omv_metrics.user_1_id', '=', 'user1.id')
        ->join('users as user2', 'ins_omv_metrics.user_2_id', '=', 'user2.id')
        ->select(
            'ins_omv_metrics.*',
            'ins_omv_metrics.start_at as start_at',
            'ins_omv_metrics.end_at as end_at',
            'ins_omv_recipes.name as recipe_name',
            'user1.name as user_1_name',
            'user2.name as user_2_name',
            'user1.emp_id as user_1_emp_id',
            'user2.emp_id as user_2_emp_id'
        );
        // if ($this->device_id) {
        //     $metrics->where('device_id', $this->device_id);
        // }

        switch ($this->ftype) {
            case 'recipe':
                $metrics->where('ins_omv_recipes.name', 'LIKE', '%' . $this->fquery . '%');
            break;
            // case 'line':
            //     $metric->where('ins_ldc_groups.line', 'LIKE', '%' . $this->fquery . '%');
            // break;
            case 'emp_id':
            $metrics->where(function (Builder $query) {
                $query
                    ->orWhere('user1.emp_id', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('user2.emp_id', 'LIKE', '%' . $this->fquery . '%');
            });
            break;
            
            default:
                $metrics->where(function (Builder $query) {
                $query
                    ->orWhere('ins_omv_recipes.name', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('user1.emp_id', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('user2.emp_id', 'LIKE', '%' . $this->fquery . '%');
                });
                break;
        }

        $metrics = $metrics->orderBy('start_at', 'DESC')->paginate($this->perPage);

        // Statistics
        // hitung tanggal, jam, line
        // $u = DB::table('ins_rtc_metrics')
        //     ->join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')  // Join with ins_rtc_clumps
        //     ->select(DB::raw('CONCAT(DATE(dt_client), LPAD(HOUR(dt_client), 2, "0"), ins_rtc_clumps.ins_rtc_device_id) as date_hour_device_id'))  // Adjusted for correct path to ins_rtc_device_id
        //     ->whereBetween('dt_client', [$start, $end]);

        // if ($this->device_id) {
        //     // Make sure to use the correct column from the correct table for the device ID
        //     $u->where('ins_rtc_clumps.ins_rtc_device_id', $this->device_id);
        // }

        // $numeratorIntegrity = $u->groupBy('date_hour_device_id')->get()->count();


        // hitung tanggal, line
        // $v = DB::table('ins_rtc_metrics')
        //     ->join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')  // Join with ins_rtc_clumps
        //     ->select(DB::raw('CONCAT(DATE(dt_client), ins_rtc_clumps.ins_rtc_device_id) as date_device_id'))  // Use correct reference to ins_rtc_device_id
        //     ->whereBetween('dt_client', [$start, $end]);

        // if ($this->device_id) {
        //     $v->where('ins_rtc_clumps.ins_rtc_device_id', $this->device_id);  // Adjusted to correct column
        // }

        // $denominatorIntegrity = $v->groupBy('date_device_id')->get()->count() * 21;


        // hitung tanggal
        $w = DB::table('ins_omv_metrics')
            ->select(DB::raw('DATE(start_at) as date'))
            ->whereBetween('start_at', [$start, $end]);
        // if ($this->device_id) {
        //     $w->where('device_id', $this->device_id);
        // }
        $this->days = $w->groupBy('date')->get()->count();

        // if ($denominatorIntegrity > 0) {
        //     $this->integrity = (int) (($numeratorIntegrity / $denominatorIntegrity) * 100);
        // }

        $x = InsOmvMetric::whereBetween('start_at', [$start, $end]);
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
                {{-- <div class="text-sm"><span class="text-neutral-500">{{  __('Integritas:') . ' ' }}</span><span>{{ $integrity . '% ' }}</span></div> --}}
                <x-secondary-button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')"><i class="fa fa-fw fa-question"></i></x-secondary-button>
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
                {{-- <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400"><span
                        class="font-bold">{{ __('Integritas:') . ' ' }}</span>
                    {{ __('Mengindikasikan persentase data yang hadir di tiap jamnya. Contoh: Jika ada data setiap jam selama 21 jam dalam 1 hari, maka integritas bernilai 100%. Jika hanya ada data selama 10.5 jam selama 21 jam dalam 1 hari, maka integritas bernilai 50%') }}
                </p> --}}
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
                        <i class="fa fa-calendar relative"><i
                                class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Tentukan rentang tanggal') }}
                    </div>
                </div>
            @else
                <div wire:key="no-match" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="fa fa-ghost"></i>
                    </div>
                    <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
                    </div>
                </div>
            @endif
        @else
            <div wire:key="raw-metrics" class="p-0 sm:p-1 overflow-auto">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table">
                    <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                        <tr class="uppercase text-xs">
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Tipe') }}</th>
                            <th>{{ __('Resep') }}</th>
                            <th>{{ __('S') }}</th>
                            <th>{{ __('Operator 1') }}</th>
                            <th>{{ __('Operator 2') }}</th>
                            <th>{{ __('Evaluasi') }}</th>
                            <th>{{ __('Durasi') }}</th>
                            <th><i class="fa fa-images"></i></th>
                            <th>{{ __('Awal') }}</th>
                            <th>{{ __('Akhir') }}</th>
                        </tr>
                        @foreach ($metrics as $metric)
                            <tr>
                                <td>{{ $metric->id }}</td>
                                <td>{{ $metric->shift }}</td>
                                <td>{{ ($metric->user_1->emp_id ?? '') . ' - ' . ($metric->user_1->name ?? '') }}</td>
                                <td>{{ ($metric->user_2->emp_id ?? '') . ' - ' . ($metric->user_2->name ?? '') }}</td>
                                <td>{{ $metric->eval }}</td>
                                <td></td>
                                <td></td>
                                <td>{{ $metric->start_at }}</td>
                                <td>{{ $metric->end_at }}</td>
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
    <div wire:key="captures">
        <x-modal name="captures">
            <livewire:insight.omv.captures />
        </x-modal>
    </div>
</div>
