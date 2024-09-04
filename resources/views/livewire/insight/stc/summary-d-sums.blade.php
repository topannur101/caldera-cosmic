<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\InsRdcDSum;

new #[Layout('layouts.app')] 
class extends Component {
    use WithPagination;

    #[Reactive]
    public $start_at;

    #[Reactive]
    public $end_at;

    #[Reactive]
    public $fquery;

    #[Reactive]
    public $ftype;

    public $perPage = 20;

    public $sort = 'updated';

    public function with(): array
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        // $dSumsQuery = InsRdcDSum::join('ins_rubber_batches', 'ins_rdc_d_sums.ins_rubber_batch_id', '=', 'ins_rubber_batches.id')
        // ->join('users', 'ins_rdc_d_sums.user_id', '=', 'users.id')
        // ->select(
        // 'ins_rdc_d_sums.*',
        // 'ins_rdc_d_sums.updated_at as d_sum_updated_at',
        // 'ins_rubber_batches.code as batch_code',
        // 'ins_rubber_batches.model as batch_model',
        // 'ins_rubber_batches.color as batch_color',
        // 'ins_rubber_batches.mcs as batch_mcs',
        // 'users.emp_id as user_emp_id',
        // 'users.name as user_name');

        $dSumsQuery = InsRdcDSum::join('ins_rubber_batches', 'ins_rdc_d_sums.ins_rubber_batch_id', '=', 'ins_rubber_batches.id')
        ->join('users', 'ins_rdc_d_sums.user_id', '=', 'users.id')
        ->select(
        'ins_rdc_d_sums.*',
        'ins_rdc_d_sums.updated_at as d_sum_updated_at',
        'ins_rubber_batches.code as batch_code',
        'ins_rubber_batches.model as batch_model',
        'ins_rubber_batches.color as batch_color',
        'ins_rubber_batches.mcs as batch_mcs',
        'users.emp_id as user_emp_id',
        'users.name as user_name');

        $dSumsQuery->whereBetween('ins_rdc_d_sums.updated_at', [$start, $end]);

        switch ($this->ftype) {
            case 'code':
                $dSumsQuery->where('ins_rubber_batches.code', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'model':
                $dSumsQuery->where('ins_rubber_batches.model', 'LIKE', '%' . $this->fquery . '%');
                break;
            case 'color':
                $dSumsQuery->where('ins_rubber_batches.color', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'mcs':
                $dSumsQuery->where('ins_rubber_batches.mcs', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'eval':
                $dSumsQuery->where('ins_rdc_d_sums.eval', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'emp_id':
                $dSumsQuery->where('users.emp_id', 'LIKE', '%' . $this->fquery . '%');
            break;
            
            default:
                $dSumsQuery->where(function (Builder $query) {
                $query
                    ->orWhere('ins_rubber_batches.code', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('ins_rubber_batches.model', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('ins_rubber_batches.color', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('ins_rubber_batches.mcs', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('users.emp_id', 'LIKE', '%' . $this->fquery . '%');
                });
                break;
        }

        $dSumsQuery->orderBy('ins_rdc_d_sums.updated_at', 'DESC');

        // switch ($this->sort) {
        //     case 'updated':
        //         if (!$this->is_workdate) {
        //             $dSumsQuery->orderBy('ins_rdc_d_sums.updated_at', 'DESC');
        //         } else {
        //             $dSumsQuery->orderBy('ins_rdc_groups.workdate', 'DESC');
        //         }
        //         break;
            
        //     case 'sf_low':
        //     $dSumsQuery->orderBy(DB::raw('ins_rdc_d_sums.area_vn + ins_rdc_d_sums.area_ab + ins_rdc_d_sums.area_qt'), 'ASC');
        //         break;

        //     case 'sf_high':
        //     $dSumsQuery->orderBy(DB::raw('ins_rdc_d_sums.area_vn + ins_rdc_d_sums.area_ab + ins_rdc_d_sums.area_qt'), 'DESC');
        //         break;
        // }

        $dSums = $dSumsQuery->paginate($this->perPage);
        // $sum_area_vn = $dSumsQuery->sum('area_vn');
        // $sum_area_ab = $dSumsQuery->sum('area_ab');

        return [
            'd_sums' => $dSums,
            // 'sum_area_vn' => $sum_area_vn,
            // 'sum_area_ab' => $sum_area_ab
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};

?>

<div class="overflow-auto w-full">
    <div>
        <div class="flex justify-between items-center mb-6 px-5 py-1">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">
                {{ __('Hasil Ukur') }}</h1>
            <div class="flex gap-x-2 items-center">
                <x-secondary-button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')"><i class="fa fa-fw fa-question"></i></x-secondary-button>
            </div>
        </div>
        <x-modal name="raw-stats-info">
            <div class="p-6">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Statistik hasil ukur') }}
                </h2>
                <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Belum ada informasi statistik yang tersedia.') }}
                </p>
                <div class="mt-6 flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Paham') }}
                    </x-primary-button>
                </div>
            </div>
        </x-modal>
        @if (!$dSums->count())
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
            <div wire:poll.30s wire:key="raw-d_sums" class="p-0 sm:p-1 overflow-auto">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table">
                    <table class="table table-sm table-truncate text-sm text-neutral-600 dark:text-neutral-400">
                        <tr class="uppercase text-xs">
                            <th>{{ __('Waktu') }}</th>
                            <th>{{ __('Durasi') }}</th>
                            <th>{{ __('Median suhu') }}</th>
                            <th>{{ __('RPM') }}</th>    
                        </tr>
                        @foreach ($dSums as $d_sum)
                        <tr>
                            <td>{{ $d_sum->d_sum_queued_at }}</td>
                            <td>{{ $d_sum->batch_code }}</td>
                            <td>{{ $d_sum->batch_model ? $d_sum->batch_model : '-' }}</td>
                            <td>{{ $d_sum->batch_color ? $d_sum->batch_color : '-'  }}</td>
                            <td>{{ $d_sum->batch_mcs ? $d_sum->batch_mcs : '-' }}</td>
                            <td><x-pill class="uppercase" color="{{ 
                                $d_sum->eval === 'queue' ? 'yellow' : 
                                ($d_sum->eval === 'pass' ? 'green' : 
                                ($d_sum->eval === 'fail' ? 'red' : ''))
                                }}">{{ $d_sum->evalHuman() }}</x-pill></td>
                            <td>{{ $d_sum->user_name }}</td>
                            <td>{{ $d_sum->d_sum_updated_at }}</td>
                        </tr>
                    @endforeach
                    </table>
                </div>
            </div>
            <div class="flex items-center relative h-16">
                @if (!$dSums->isEmpty())
                    @if ($dSums->hasMorePages())
                        <div wire:key="more" x-data="{
                            observe() {
                                const observer = new IntersectionObserver((d_sums) => {
                                    d_sums.forEach(d_sum => {
                                        if (d_sum.isIntersecting) {
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
