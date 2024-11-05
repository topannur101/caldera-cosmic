<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

use Carbon\Carbon;
use App\InsStc;
use App\Models\InsStcDSum;
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

    private function getDSumsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsStcDSum::join('ins_stc_machines', 'ins_stc_d_sums.ins_stc_machine_id', '=', 'ins_stc_machines.id')
            ->join('users as user1', 'ins_stc_d_sums.user_1_id', '=', 'user1.id')
            ->leftjoin('users as user2', 'ins_stc_d_sums.user_2_id', '=', 'user2.id')
            ->select(
                'ins_stc_d_sums.*',
                'ins_stc_d_sums.updated_at as d_sum_updated_at',
                'ins_stc_machines.line as machine_line',
                'user1.emp_id as user1_emp_id',
                'user1.name as user1_name',
                'user2.emp_id as user2_emp_id',
                'user2.name as user2_name'
            )
            ->whereBetween('ins_stc_d_sums.updated_at', [$start, $end]);

            if ($this->line)
            {
                $query->where('ins_stc_machines.line', 'LIKE', '%' . $this->line . '%');
            }

    
        // switch ($this->ftype) {
        //     case 'recipe':
        //         $query->where('ins_stc_recipes.name', 'LIKE', '%' . $this->fquery . '%');
        //         break;
        //     case 'line':
        //         $query->where('ins_stc_d_sums.line', 'LIKE', '%' . $this->fquery . '%');
        //         break;
        //     case 'team':
        //         $query->where('ins_stc_d_sums.team', 'LIKE', '%' . $this->fquery . '%');
        //         break;
        //     case 'emp_id':
        //         $query->where(function (Builder $query) {
        //             $query->orWhere('user1.emp_id', 'LIKE', '%' . $this->fquery . '%')
        //                 ->orWhere('user2.emp_id', 'LIKE', '%' . $this->fquery . '%');
        //         });
        //         break;
        //     default:
        //         $query->where(function (Builder $query) {
        //             $query->orWhere('ins_stc_recipes.name', 'LIKE', '%' . $this->fquery . '%')
        //                 ->orWhere('user1.emp_id', 'LIKE', '%' . $this->fquery . '%')
        //                 ->orWhere('user2.emp_id', 'LIKE', '%' . $this->fquery . '%');
        //         });
        //         break;
        // }

        return $query->orderBy('start_time', 'DESC');
    }

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisWeek();
        }

        $this->lines = InsStcMachine::orderBy('line')->get()->pluck('line')->toArray();

        // if (!$this->line && !$this->team && Auth::user()) {
        //     $latestDsum = Auth::user()->ins_stc_d_sums()->latest()->first();
            
        //     if ($latestDsum) {
        //         $this->line = $latestDsum->line;
        //     }
        // }
    }

    public function with(): array
    {
        $dSums = $this->getDSumsQuery()->paginate($this->perPage);

        return [
            'd_sums' => $dSums,
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    public function download()
    {
        $this->js('notyfSuccess("' . __('Pengunduhan dimulai...') . '")');
        $filename = 'd_sums_export_' . now()->format('Y-m-d_His') . '.csv';

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

            $this->getDSumsQuery()->chunk(1000, function ($dSums) use ($file) {
                foreach ($dSums as $dSum) {
                    fputcsv($file, [
                        $dSum->d_sum_updated_at,
                        $dSum->machine_line,
                        InsStc::positionHuman($dSum->position),
                        $dSum->speed,
                        $dSum->z_1_temp,
                        $dSum->z_2_temp,
                        $dSum->z_3_temp,
                        $dSum->z_4_temp,
                        $dSum->user1_name . ' - ' . $dSum->user1_emp_id,
                        $dSum->user2_name . ' - ' . $dSum->user2_emp_id,
                        $dSum->start_time,
                        $dSum->duration(),
                        $dSum->uploadLatency(),
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
                    <label for="d_sums-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="d_sums-line" wire:model.live="line" type="number" list="d_sums-lines" step="1" />
                    <datalist id="d_sums-lines">
                        @foreach($lines as $line)
                        <option value="{{ $line }}"></option>
                        @endforeach
                    </datalist>
                </div>
                {{-- <div class="w-full lg:w-32">
                    <label for="d_sums-team"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                    <x-text-input id="d_sums-team" wire:model.live="team" type="text" list="d_sums-teams" />
                    <datalist id="d_sums-teams">
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
                        <div wire:loading.class="hidden">{{ $d_sums->total() . ' ' . __('ditemukan') }}</div>
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
        <div wire:key="raw-d_sums" class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('Diperbarui pada') }}</th> 
                        <th>{{ __('Line') }}</th>
                        <th>{{ __('Posisi') }}</th>  
                        <th>{{ __('RPM') }}</th>   
                        <th>{{ __('Median suhu') }}</th>
                        <th>{{ __('Pengukur') }}</th>
                        <th>{{ __('Waktu mulai') }}</th>
                        <th>{{ __('Durasi') }}</th>
                        <th>{{ __('Latensi unggah') }}</th>
                    </tr>
                    @foreach ($d_sums as $d_sum)
                        <tr wire:key="d_sum-tr-{{ $d_sum->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'd_sum-show'); $dispatch('d_sum-show', { id: '{{ $d_sum->id }}'})">
                            <td>{{ $d_sum->d_sum_updated_at }}</td>
                            <td>{{ $d_sum->machine_line }}</td>
                            <td>{{ InsStc::positionHuman($d_sum->position) }}</td>
                            <td>{{ $d_sum->speed }}</td>
                            <td>{{ $d_sum->z_1_temp . ' | ' . $d_sum->z_2_temp . ' | ' . $d_sum->z_3_temp . ' | ' . $d_sum->z_4_temp  }}</td>
                            <td>{{ $d_sum->user1_name }}</td>
                            <td>{{ $d_sum->start_time }}</td>
                            <td>{{ $d_sum->duration() }}</td>
                            <td>{{ $d_sum->uploadLatency() }}</td>
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
