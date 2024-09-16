<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\InsStcDSum;

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

        $dSumsQuery = InsStcDSum::join('ins_stc_machines', 'ins_stc_d_sums.ins_stc_machine_id', '=', 'ins_stc_machines.id')
        ->join('users as user1', 'ins_stc_d_sums.user_1_id', '=', 'user1.id')
        ->leftjoin('users as user2', 'ins_stc_d_sums.user_2_id', '=', 'user2.id') // leftjoin cause user2 can be null
        ->select(
            'ins_stc_d_sums.*',
            'ins_stc_d_sums.updated_at as d_sum_updated_at',
            'ins_stc_machines.line as machine_line',
            'user1.emp_id as user1_emp_id',
            'user1.name as user1_name',
            'user2.emp_id as user2_emp_id',
            'user2.name as user2_name'
        );

        $dSumsQuery->whereBetween('ins_stc_d_sums.updated_at', [$start, $end]);

        switch ($this->ftype) {
            case 'emp_id':
                $dSumsQuery->where(function (Builder $query) {
                $query
                    ->orWhere('user1.emp_id', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('user2.emp_id', 'LIKE', '%' . $this->fquery . '%');
                });
                break;
            
            default:
                $dSumsQuery->where(function (Builder $query) {
                $query
                    ->orWhere('user1.emp_id', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('user2.emp_id', 'LIKE', '%' . $this->fquery . '%');
                });
                break;
        }

        $dSumsQuery->orderBy('ins_stc_d_sums.updated_at', 'DESC');

        // switch ($this->sort) {
        //     case 'updated':
        //         if (!$this->is_workdate) {
        //             $dSumsQuery->orderBy('ins_stc_d_sums.updated_at', 'DESC');
        //         } else {
        //             $dSumsQuery->orderBy('ins_stc_groups.workdate', 'DESC');
        //         }
        //         break;
            
        //     case 'sf_low':
        //     $dSumsQuery->orderBy(DB::raw('ins_stc_d_sums.area_vn + ins_stc_d_sums.area_ab + ins_stc_d_sums.area_qt'), 'ASC');
        //         break;

        //     case 'sf_high':
        //     $dSumsQuery->orderBy(DB::raw('ins_stc_d_sums.area_vn + ins_stc_d_sums.area_ab + ins_stc_d_sums.area_qt'), 'DESC');
        //         break;
        // }

        $dSums = $dSumsQuery->paginate($this->perPage);

        return [
            'd_sums' => $dSums,
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
        <div wire:key="modals"> 
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
            <x-modal name="d_sum-show" maxWidth="xl">
                <livewire:insight.stc.summary.d-sum-show />
            </x-modal>
        </div>
        @if (!$d_sums->count())
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
                            <th>{{ __('Waktu mulai') }}</th>
                            <th>{{ __('Durasi') }}</th>
                            <th>{{ __('Line') }}</th>
                            <th>{{ __('Posisi') }}</th>  
                            <th>{{ __('RPM') }}</th>   
                            <th>{{ __('Median suhu') }}</th>
                            <th>{{ __('Pengukur') }}</th>
                            <th>{{ __('Diperbarui pada') }}</th> 
                        </tr>
                        @foreach ($d_sums as $d_sum)
                        <tr wire:key="d_sum-tr-{{ $d_sum->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'd_sum-show'); $dispatch('d_sum-show', { id: '{{ $d_sum->id }}'})">
                            <td>{{ $d_sum->start_time }}</td>
                            <td>{{ $d_sum->duration() }}</td>
                            <td>{{ $d_sum->machine_line }}</td>
                            <td>{{ $d_sum->position }}</td>
                            <td>{{ $d_sum->speed }}</td>
                            <td>{{ $d_sum->z_1_temp . ' | ' . $d_sum->z_2_temp . ' | ' . $d_sum->z_3_temp . ' | ' . $d_sum->z_4_temp  }}</td>
                            <td>{{ $d_sum->user1_name }}</td>
                            <td>{{ $d_sum->d_sum_updated_at }}</td>
                        </tr>
                    @endforeach
                    </table>
                </div>
            </div>
            <div class="flex items-center relative h-16">
                @if (!$d_sums->isEmpty())
                    @if ($d_sums->hasMorePages())
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

