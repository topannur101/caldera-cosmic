<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use App\Models\InsOmvMetric;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] 
class extends Component {
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
        $end = Carbon::parse($this->end_at)->endOfDay();

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
        )
        ->whereBetween('ins_omv_metrics.start_at', [$start, $end]);

        // if ($this->device_id) {
        //     $metrics->where('device_id', $this->device_id);
        // }

        switch ($this->ftype) {
            case 'recipe':
                $metrics->where('ins_omv_recipes.name', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'line':
                $metric->where('ins_omv_metrics.line', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'team':
                $metric->where('ins_omv_metrics.team', 'LIKE', '%' . $this->fquery . '%');
            break;
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
                <x-secondary-button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')"><i class="fa fa-fw fa-question"></i></x-secondary-button>
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
            <x-modal name="metric-show" maxWidth="2xl">
                <livewire:insight.omv.summary.metric-show />
            </x-modal>
            {{-- <x-modal name="captures">
                <livewire:insight.omv.summary.metric-captures />
            </x-modal> --}}
        </div>
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
                            <th>{{ __('Kode') }}</th>
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
                            x-on:click="$dispatch('open-modal', 'metric-show'); $dispatch('metric-show', { id: '{{ $metric->id }}'})">
                                <td>{{ $metric->id }}</td>
                                <td>{{ $metric->ins_rubber_batch->code ?? '' }}</td>
                                <td>{{ strtoupper($metric->ins_omv_recipe->type) }}</td>
                                <td>{{ $metric->ins_omv_recipe->name }}</td>
                                <td>{{ $metric->line }}</td>
                                <td>{{ $metric->team }}</td>
                                <td title="{{ __('Operator 2') . ': ' . ($metric->user_2->emp_id ?? '') . ' - ' . ($metric->user_2->name ?? '') }}">{{ ($metric->user_1->emp_id ?? '') . ' - ' . ($metric->user_1->name ?? '') }}</td>
                                <td>{{ $metric->evalFriendly() }}</td>
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
</div>
