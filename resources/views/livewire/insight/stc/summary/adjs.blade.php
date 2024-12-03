<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

use Carbon\Carbon;
use App\InsStc;
use App\Models\InsStcAdj;
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

    private function getAdjsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsStcAdj::join('ins_stc_machines', 'ins_stc_adjs.ins_stc_machine_id', '=', 'ins_stc_machines.id')
        ->join('users', 'ins_stc_adjs.user_id', '=', 'users.id')
         ->select(
                'ins_stc_adjs.*',
                'ins_stc_adjs.created_at as adj_created_at',
                'ins_stc_machines.line as machine_line',
                'users.name as user_name',
                'users.emp_id as user_emp_id'
            )
            ->whereBetween('ins_stc_adjs.created_at', [$start, $end]);

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
        //     $latestAdj = Auth::user()->ins_stc_adjs()->latest()->first();
            
        //     if ($latestAdj) {
        //         $this->line = $latestAdj->line;
        //     }
        // }
    }

    public function with(): array
    {
        $adjs = $this->getAdjsQuery()->paginate($this->perPage);

        return [
            'adjs' => $adjs,
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    public function download()
    {
        $this->js('notyfSuccess("' . __('Pengunduhan dimulai...') . '")');
        $filename = 'adjs_export_' . now()->format('Y-m-d_His') . '.csv';

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

            $this->getAdjsQuery()->chunk(1000, function ($adjs) use ($file) {
                foreach ($adjs as $adj) {
                    fputcsv($file, [
                        $adj->adj_created_at,
                        $adj->machine_line,
                        InsStc::positionHuman($adj->position),
                        $adj->speed,
                        $adj->z_1_temp,
                        $adj->z_2_temp,
                        $adj->z_3_temp,
                        $adj->z_4_temp,
                        $adj->user1_name . ' - ' . $adj->user1_emp_id,
                        $adj->user2_name . ' - ' . $adj->user2_emp_id,
                        $adj->start_time,
                        $adj->duration(),
                        $adj->uploadLatency(),
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
                    <label for="adjs-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="adjs-line" wire:model.live="line" type="number" list="adjs-lines" step="1" />
                    <datalist id="adjs-lines">
                        @foreach($lines as $line)
                        <option value="{{ $line }}"></option>
                        @endforeach
                    </datalist>
                </div>
                {{-- <div class="w-full lg:w-32">
                    <label for="adjs-team"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                    <x-text-input id="adjs-team" wire:model.live="team" type="text" list="adjs-teams" />
                    <datalist id="adjs-teams">
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
                        <div wire:loading.class="hidden">{{ $adjs->total() . ' ' . __('ditemukan') }}</div>
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
        <x-modal name="adj-show" maxWidth="xl">
            {{-- <livewire:insight.stc.summary.m-log-show /> --}}
        </x-modal>
    </div>
    @if (!$adjs->count())
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
        <div wire:key="raw-adjs" class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('Dibuat pada') }}</th> 
                        <th>{{ __('Line') }}</th>
                        <th>{{ __('Posisi') }}</th>
                        <th>{{ __('SVP') }}</th>
                        <th>{{ __('FID')}}</th>
                        <th>{{ __('Dikirim?') }}</th>
                        <th>{{ __('NIK') }}</th>
                        <th>{{ __('Nama') }}</th>
                    </tr>
                    @foreach ($adjs as $adj)
                        <tr wire:key="adj-tr-{{ $adj->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'adj-show'); $dispatch('adj-show', { id: '{{ $adj->id }}'})">
                            <td>{{ $adj->adj_created_at }}</td>
                            <td>{{ $adj->machine_line }}</td>
                            <td>{{ InsStc::positionHuman($adj->position) }}</td>
                            <td>{{ $adj->sv_p_1 . '|' . $adj->sv_p_2 . '|' . $adj->sv_p_3 . '|' . $adj->sv_p_4 . '|' . $adj->sv_p_5 . '|' . $adj->sv_p_6 . '|' . $adj->sv_p_7 . '|' . $adj->sv_p_8 }}</td>
                            <td>{{ $adj->formula_id }}</td>
                            <td>{{ $adj->is_sent ? '•' : '' }}</td>
                            <td>{{ $adj->user_emp_id }}</td>
                            <td>{{ $adj->user_name }}</td>
                           </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="flex items-center relative h-16">
            @if (!$adjs->isEmpty())
                @if ($adjs->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((adjs) => {
                                adjs.forEach(adj => {
                                    if (adj.isIntersecting) {
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
