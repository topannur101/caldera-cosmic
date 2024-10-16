<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\InsStcMLog;
use App\InsStc;

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

        $MlogsQuery = InsStcMlog::join('ins_stc_machines', 'ins_stc_m_logs.ins_stc_machine_id', '=', 'ins_stc_machines.id')
        // ->join('users as user1', 'ins_stc_m_logs.user_1_id', '=', 'user1.id')
        // ->leftjoin('users as user2', 'ins_stc_m_logs.user_2_id', '=', 'user2.id') // leftjoin cause user2 can be null
        ->select(
            'ins_stc_m_logs.*',
            'ins_stc_m_logs.created_at as m_log_created_at',
            // 'ins_stc_machines.line as machine_line',
            // 'user1.emp_id as user1_emp_id',
            // 'user1.name as user1_name',
            // 'user2.emp_id as user2_emp_id',
            // 'user2.name as user2_name'
        );

        $MlogsQuery->whereBetween('ins_stc_m_logs.created_at', [$start, $end]);

        // switch ($this->ftype) {
        //     case 'emp_id':
        //         $MlogsQuery->where(function (Builder $query) {
        //         $query
        //             ->orWhere('user1.emp_id', 'LIKE', '%' . $this->fquery . '%')
        //             ->orWhere('user2.emp_id', 'LIKE', '%' . $this->fquery . '%');
        //         });
        //         break;
            
        //     default:
        //         $MlogsQuery->where(function (Builder $query) {
        //         $query
        //             ->orWhere('user1.emp_id', 'LIKE', '%' . $this->fquery . '%')
        //             ->orWhere('user2.emp_id', 'LIKE', '%' . $this->fquery . '%');
        //         });
        //         break;
        // }

        $MlogsQuery->orderBy('ins_stc_m_logs.created_at', 'DESC');

        // switch ($this->sort) {
        //     case 'updated':
        //         if (!$this->is_workdate) {
        //             $MlogsQuery->orderBy('ins_stc_m_logs.created_at', 'DESC');
        //         } else {
        //             $MlogsQuery->orderBy('ins_stc_groups.workdate', 'DESC');
        //         }
        //         break;
            
        //     case 'sf_low':
        //     $MlogsQuery->orderBy(DB::raw('ins_stc_m_logs.area_vn + ins_stc_m_logs.area_ab + ins_stc_m_logs.area_qt'), 'ASC');
        //         break;

        //     case 'sf_high':
        //     $MlogsQuery->orderBy(DB::raw('ins_stc_m_logs.area_vn + ins_stc_m_logs.area_ab + ins_stc_m_logs.area_qt'), 'DESC');
        //         break;
        // }

        $Mlogs = $MlogsQuery->paginate($this->perPage);

        return [
            'm_logs' => $Mlogs,
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};

?>

<div wire:key="m-logs" class="overflow-auto w-full">
    <div>
        <div class="flex justify-between items-center mb-6 px-5 py-1">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">
                {{ __('Metrik mesin') }}</h1>
            <div class="flex gap-x-2 items-center">
                <x-secondary-button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')"><i class="fa fa-fw fa-question"></i></x-secondary-button>
            </div>
        </div>
        <div wire:key="modals"> 
            <x-modal name="raw-stats-info">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Statistik suhu mesin') }}
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
            <x-modal name="m_log-show" maxWidth="xl">
                Hi, please replace me
            </x-modal>
        </div>
        @if (!$m_logs->count())
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
            <div wire:poll.30s wire:key="raw-m_logs" class="p-0 sm:p-1 overflow-auto">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table">
                    <table class="table table-sm table-truncate text-sm text-neutral-600 dark:text-neutral-400">
                        <tr class="uppercase text-xs">
                            <th>{{ __('Diambil pada') }}</th>
                            <th>{{ __('Posisi') }}</th>
                            <th>{{ __('Suhu PV') }}</th>
                            <th>{{ __('Suhu SV') }}</th>
                        </tr>
                        @foreach ($m_logs as $m_log)
                        <tr wire:key="m_log-tr-{{ $m_log->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'm_log-show'); $dispatch('m_log-show', { id: '{{ $m_log->id }}'})">
                            <td>{{ $m_log->created_at }}</td>
                            <td>{{ $m_log->position }}</td>
                            <td>{{ $m_log->pv_1 . ' | ' . $m_log->pv_2 . ' | ' . $m_log->pv_3 . ' | ' . $m_log->pv_4 . ' | ' . $m_log->pv_5 . ' | ' . $m_log->pv_6 . ' | ' . $m_log->pv_7 . ' | ' . $m_log->pv_8 }}</td>
                            <td>{{ $m_log->sv_1 . ' | ' . $m_log->sv_2 . ' | ' . $m_log->sv_3 . ' | ' . $m_log->sv_4 . ' | ' . $m_log->sv_5 . ' | ' . $m_log->sv_6 . ' | ' . $m_log->sv_7 . ' | ' . $m_log->sv_8 }}</td>
                        </tr>
                    @endforeach
                    </table>
                </div>
            </div>
            <div class="flex items-center relative h-16">
                @if (!$m_logs->isEmpty())
                    @if ($m_logs->hasMorePages())
                        <div wire:key="more" x-data="{
                            observe() {
                                const observer = new IntersectionObserver((m_logs) => {
                                    m_logs.forEach(m_log => {
                                        if (m_log.isIntersecting) {
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

