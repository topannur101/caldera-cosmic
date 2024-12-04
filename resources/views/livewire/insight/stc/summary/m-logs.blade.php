<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

use Carbon\Carbon;
use App\InsStc;
use App\Models\InsStcMlog;
use App\Models\InsStcMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Traits\HasDateRangeFilter;

new #[Layout('layouts.app')] 
class extends Component {

    use WithPagination;
    use HasDateRangeFilter;

    #[Url]
    public $start_at;
    #[Url]
    public $end_at;
    #[Url]
    public $line;

    public $lines;

    public $perPage = 20;

    private function getMlogsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsStcMlog::join('ins_stc_machines', 'ins_stc_m_logs.ins_stc_machine_id', '=', 'ins_stc_machines.id')
            ->select(
                'ins_stc_m_logs.*',
                'ins_stc_m_logs.updated_at as m_log_updated_at',
                'ins_stc_machines.line as machine_line',
            )
            ->whereBetween('ins_stc_m_logs.updated_at', [$start, $end]);

            if ($this->line)
            {
                $query->where('ins_stc_machines.line', 'LIKE', '%' . $this->line . '%');
            }

        return $query;
    }

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisWeek();
        }

        $this->lines = InsStcMachine::orderBy('line')->get()->pluck('line')->toArray();

        // if (!$this->line && !$this->team && Auth::user()) {
        //     $latestMlog = Auth::user()->ins_stc_m_logs()->latest()->first();
            
        //     if ($latestMlog) {
        //         $this->line = $latestMlog->line;
        //     }
        // }
    }

    public function with(): array
    {
        $mlogs = $this->getMlogsQuery()->paginate($this->perPage);

        return [
            'm_logs' => $mlogs,
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    public function download()
    {
        $this->js('notyfSuccess("' . __('Pengunduhan dimulai...') . '")');
        $filename = 'm_logs_export_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            __('Diperbarui pada'), __('Line'), __('Posisi'), __('RPM'), 'Z1 Temp', 'Z2 Temp', 'Z3 Temp', 'Z4 Temp',
            __('Operator') . ' 1' , __('Operator') . ' 2', __('Awal'), __('Durasi'), __('Latensi unggah')
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $this->getMlogsQuery()->chunk(1000, function ($mlogs) use ($file) {
                foreach ($mlogs as $mlog) {
                    fputcsv($file, [
                        $mlog->m_log_updated_at,
                        $mlog->machine_line,
                        InsStc::positionHuman($mlog->position),
                        $mlog->speed,
                        $mlog->z_1_temp,
                        $mlog->z_2_temp,
                        $mlog->z_3_temp,
                        $mlog->z_4_temp,
                        $mlog->user1_name . ' - ' . $mlog->user1_emp_id,
                        $mlog->user2_name . ' - ' . $mlog->user2_emp_id,
                        $mlog->start_time,
                        $mlog->duration(),
                        $mlog->uploadLatency(),
                    ]);
                }
            });

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
};

?>

<div>
    <div class="p-0 sm:p-1 mb-6">
        <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div>
                <div class="flex mb-2 text-xs text-neutral-500">
                    <div class="flex">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <x-text-button class="uppercase ml-3">{{ __('Rentang') }}<i class="fa fa-fw fa-chevron-down ms-1"></i></x-text-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setToday">
                                    {{ __('Hari ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                    {{ __('Kemarin') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                    {{ __('Minggu ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                    {{ __('Minggu lalu') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                    {{ __('Bulan ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                    {{ __('Bulan lalu') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at"  id="cal-date-end" type="date"></x-text-input>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex gap-3">
                <div class="w-full lg:w-32">
                    <label for="m_logs-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="m_logs-line" wire:model.live="line" type="number" list="m_logs-lines" step="1" />
                    <datalist id="m_logs-lines">
                        @foreach($lines as $line)
                        <option value="{{ $line }}"></option>
                        @endforeach
                    </datalist>
                </div>
                {{-- <div class="w-full lg:w-32">
                    <label for="m_logs-team"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                    <x-text-input id="m_logs-team" wire:model.live="team" type="text" list="m_logs-teams" />
                    <datalist id="m_logs-teams">
                        <option value="A"></option>
                        <option value="B"></option>
                        <option value="C"></option>
                    </datalist>
                </div> --}}
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-between gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div wire:loading.class="hidden">{{ $m_logs->total() . ' ' . __('ditemukan') }}</div>
                        <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                            <div class="relative w-3">
                                <x-spinner class="sm white"></x-spinner>
                            </div>
                            <div>
                                {{ __('Memuat...') }}
                            </div>
                        </div>
                    </div>
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')">
                            <i class="fa fa-fw me-2"></i>{{ __('Statistik ')}}
                        </x-dropdown-link>
                        <hr
                            class="border-neutral-300 dark:border-neutral-600" />
                        <x-dropdown-link href="#" wire:click.prevent="download">
                            <i class="fa fa-fw fa-download me-2"></i>{{ __('Unduh sebagai CSV') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
    <div wire:key="modals"> 
        <x-modal name="raw-stats-info">
            <div class="p-6">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Statistik data mentah') }}
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
            {{-- <livewire:insight.stc.summary.m-log-show /> --}}
        </x-modal>
    </div>
    @if (!$m_logs->count())
        @if (!$start_at || !$end_at)
            <div wire:key="no-range" class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="fa fa-calendar relative"><i
                            class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                </div>
                <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih rentang tanggal') }}
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
        <div wire:key="raw-m_logs" class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('Diperbarui pada') }}</th> 
                        <th>{{ __('Line') }}</th>
                        <th>{{ __('Posisi') }}</th>
                        <th>PV R</th>
                        <th>SV C</th>
                        <th>SV W</th>
                        <th>SV R</th>
                    </tr>
                    @foreach ($m_logs as $m_log)
                        <tr wire:key="m_log-tr-{{ $m_log->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'm_log-show'); $dispatch('m_log-show', { id: '{{ $m_log->id }}'})">
                            <td>{{ $m_log->m_log_updated_at }}</td>
                            <td>{{ $m_log->machine_line }}</td>
                            <td>{{ InsStc::positionHuman($m_log->position) }}</td>
                            <td class="font-mono"> {{ $m_log->pv_r_1 . ', ' . $m_log->pv_r_2 . ', ' . $m_log->pv_r_3 . ', ' . $m_log->pv_r_4 . ', ' . $m_log->pv_r_5 . ', ' . $m_log->pv_r_6 . ', ' . $m_log->pv_r_7 . ', ' . $m_log->pv_r_8 }}</td>
                            <td class="font-mono"> {{ $m_log->sv_p_1 . ', ' . $m_log->sv_p_2 . ', ' . $m_log->sv_p_3 . ', ' . $m_log->sv_p_4 . ', ' . $m_log->sv_p_5 . ', ' . $m_log->sv_p_6 . ', ' . $m_log->sv_p_7 . ', ' . $m_log->sv_p_8 }}</td>
                            <td class="font-mono"> {{ $m_log->sv_w_1 . ', ' . $m_log->sv_w_2 . ', ' . $m_log->sv_w_3 . ', ' . $m_log->sv_w_4 . ', ' . $m_log->sv_w_5 . ', ' . $m_log->sv_w_6 . ', ' . $m_log->sv_w_7 . ', ' . $m_log->sv_w_8 }}</td>
                            <td class="font-mono"> {{ $m_log->sv_r_1 . ', ' . $m_log->sv_r_2 . ', ' . $m_log->sv_r_3 . ', ' . $m_log->sv_r_4 . ', ' . $m_log->sv_r_5 . ', ' . $m_log->sv_r_6 . ', ' . $m_log->sv_r_7 . ', ' . $m_log->sv_r_8 }}</td>
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
