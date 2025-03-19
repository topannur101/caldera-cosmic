<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

use Carbon\Carbon;
use App\Models\InsOmvMetric;
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

    #[Url]
    public $team;

    #[Url]
    public $mcs;

    public $perPage = 20;

    private function getMetricsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $this->line = trim($this->line);
        $this->team = trim($this->team);

        $query = InsOmvMetric::with(['ins_omv_recipe', 'user_1', 'user_2', 'ins_rubber_batch'])
            ->whereBetween('start_at', [$start, $end]);
        

        // $query = InsOmvMetric::join('ins_omv_recipes', 'ins_omv_metrics.ins_omv_recipe_id', '=', 'ins_omv_recipes.id')
        //     ->join('users as user1', 'ins_omv_metrics.user_1_id', '=', 'user1.id')
        //     ->leftJoin('users as user2', 'ins_omv_metrics.user_2_id', '=', 'user2.id')
        //     ->select(
        //         'ins_omv_metrics.*',
        //         'ins_omv_metrics.start_at as start_at',
        //         'ins_omv_metrics.end_at as end_at',
        //         'ins_omv_recipes.name as recipe_name',
        //         'ins_omv_recipes.type as recipe_type',
        //         'user1.name as user_1_name',
        //         'user2.name as user_2_name',
        //         'user1.emp_id as user_1_emp_id',
        //         'user2.emp_id as user_2_emp_id'
        //     )
        //     ->whereBetween('ins_omv_metrics.start_at', [$start, $end]);


            if ($this->line)
            {
                $query->where('ins_omv_metrics.line', $this->line);
            }

            if ($this->team)
            {
                $query->where('ins_omv_metrics.team', $this->team);
            }

            if ($this->mcs) {
                $query->whereHas('ins_rubber_batch', function ($query) {
                    $query->where('mcs', $this->mcs);
                });
            }

        return $query->orderBy('start_at', 'DESC');
    }

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setToday();
        }

        if (!$this->line && !$this->team && Auth::user()) {
            $latestMetric = Auth::user()->ins_omv_metrics()->latest()->first();
            
            if ($latestMetric) {
                $this->line = $latestMetric->line;
            }
        }
    }

    public function with(): array
    {
        $metrics = $this->getMetricsQuery()->paginate($this->perPage);
        return [
            'metrics' => $metrics,
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    public function download()
    {
        $filename = 'omv_metrics_export_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'ID', __('Kode'), 'MCS', __('Tipe'), __('Resep'), __('Line'), __('Team'), __('Operator 1'), __('Operator 2'), __('Evaluasi'), __('Durasi'), __('Jumlah Gambar'), __('Awal'), __('Akhir'),
            __('Base original') . ' (kg)',
            __('Batch remixing') . ' (kg)',
            __('Skrap') . ' (kg)',
            __('Pigmen') . ' (gr)',
            __('IS75') . ' (gr)',
            __('RM001') . ' (gr)',
            __('TBZTD') . ' (gr)',            
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $this->getMetricsQuery()->chunk(1000, function ($metrics) use ($file) {
                foreach ($metrics as $metric) {
                    fputcsv($file, [
                        $metric->id,
                        $metric->ins_rubber_batch->code ?? '',
                        $metric->ins_rubber_batch->mcs ?? '',
                        strtoupper($metric->recipe_type),
                        $metric->ins_omv_recipe->name,
                        $metric->line,
                        $metric->team,
                        $metric->user_1->emp_id . ' - ' . $metric->user_1->name,
                        $metric->user_2->emp_id . ' - ' . $metric->user_2->name,
                        $metric->evalHuman(),
                        $metric->duration(),
                        $metric->capturesCount(),
                        $metric->start_at,
                        $metric->end_at,
                        $metric->ins_rubber_batch->composition(0),
                        $metric->ins_rubber_batch->composition(1),
                        $metric->ins_rubber_batch->composition(2),
                        $metric->ins_rubber_batch->composition(3),
                        $metric->ins_rubber_batch->composition(4),
                        $metric->ins_rubber_batch->composition(5),
                        $metric->ins_rubber_batch->composition(6),
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
                <div class="w-full lg:w-28">
                    <label for="metrics-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="metrics-line" wire:model.live="line" type="number" list="metrics-lines" step="1" />
                    <datalist id="metrics-lines">
                        <option value="1"></option>
                        <option value="2"></option>
                        <option value="3"></option>
                        <option value="4"></option>
                        <option value="5"></option>
                        <option value="6"></option>
                        <option value="7"></option>
                        <option value="8"></option>
                        <option value="9"></option>
                    </datalist>
                </div>
                <div class="w-full lg:w-28">
                    <label for="metrics-team"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                    <x-text-input id="metrics-team" wire:model.live="team" type="text" list="metrics-teams" />
                    <datalist id="metrics-teams">
                        <option value="A"></option>
                        <option value="B"></option>
                        <option value="C"></option>
                    </datalist>
                </div>
                <div class="w-full lg:w-28">
                    <label for="batch-mcs"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">MCS</label>
                    <x-text-input id="batch-mcs" wire:model.live="mcs" type="text" list="batch-mcss" />
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-between gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div wire:loading.class="hidden">{{ $metrics->total() . ' ' . __('ditemukan') }}</div>
                        <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                            <div class="relative w-3">
                                <x-spinner class="sm mono"></x-spinner>
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
        <x-modal name="batch-show" maxWidth="2xl">
            <livewire:insights.rubber-batch.show />
        </x-modal>
    </div>
    @if (!$metrics->count())
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
        <div wire:key="raw-metrics" class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Kode') }}</th>
                        <th>MCS</th>
                        <th>{{ __('Tipe') }}</th>
                        <th>{{ __('Resep') }}</th>
                        <th>{{ __('L') }}</th>
                        <th>{{ __('T') }}</th>
                        <th>{{ __('Operator') }}</th>
                        <th>{{ __('Evaluasi') }}</th>
                        <th>{{ __('Durasi') }}</th>
                        <th><i class="fa fa-images"></i></th>
                        <th>{{ __('Awal') }}</th>
                        <th>{{ __('Akhir') }}</th>
                    </tr>
                    @foreach ($metrics as $metric)
                    <tr wire:key="metric-tr-{{ $metric->id . $loop->index }}" tabindex="0"
                        x-on:click="$dispatch('open-modal', 'batch-show'); $dispatch('batch-show', { omv_metric_id: '{{ $metric->id }}', view: 'omv'})">
                            <td>{{ $metric->id }}</td>
                            <td>{{ $metric->ins_rubber_batch->code ?? '' }}</td>
                            <td>{{ $metric->ins_rubber_batch->mcs ?? '' }}</td>
                            <td>{{ strtoupper($metric->ins_omv_recipe->type) }}</td>
                            <td>{{ $metric->ins_omv_recipe->name }}</td>
                            <td>{{ $metric->line }}</td>
                            <td>{{ $metric->team }}</td>
                            <td title="{{ __('Operator 2') . ': ' . ($metric->user_2->emp_id ?? '') . ' - ' . ($metric->user_2->name ?? '') }}">{{ ($metric->user_1->emp_id ?? '') . ' - ' . ($metric->user_1->name ?? '') }}</td>
                            <td>
                                <x-pill class="uppercase"
                                color="{{ $metric->eval === 'on_time' ? 'green' : ($metric->eval === 'on_time_manual' ? 'yellow' : ($metric->eval === 'too_late' || $metric->eval === 'too_soon' ? 'red' : 'neutral')) }}">{{ $metric->evalHuman() }}</x-pill>
                            </td>
                            <td>{{ $metric->duration() }}</td>
                            <td>{{ $metric->capturesCount() }}</td>
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
