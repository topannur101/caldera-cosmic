<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use App\InsOmv;
use App\Models\InsOmvMetric;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] 
class extends Component {

    use HasDateRangeFilter;

    #[Url]
    public $start_at;
    #[Url]
    public $end_at;
    #[Url]
    public $line;
    #[Url]
    public $team;

    // public int $batch_total = 0;
    // public float $duration_avg = 0;
    // public float $line_avg = 0;

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisWeek();
        }
    }

    public function with(): array
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $onTimeMetrics = InsOmvMetric::query()
            ->when($this->line, function (Builder $query) {
                $query->where('line', $this->line);
            })
            ->when($this->team, function (Builder $query) {
                $query->where('team', $this->team);
            })
            ->whereBetween('start_at', [$start, $end])
            ->whereIn('eval', ['on_time', 'on_time_manual'])
            ->get();

        $mostOnTimeUsers = DB::table(function ($query) use ($onTimeMetrics, $start, $end) {
            $query->select(
                    'user_id',
                    DB::raw('COUNT(CASE WHEN eval = "on_time" THEN 1 END) as count_on_time'),
                    DB::raw('COUNT(CASE WHEN eval = "on_time_manual" THEN 1 END) as count_on_time_manual'),
                    DB::raw('COUNT(CASE WHEN eval IN ("on_time", "on_time_manual") THEN 1 END) as total_on_time')
                )
                ->from(function ($subQuery) use ($onTimeMetrics, $start, $end) {
                    $subQuery->from('ins_omv_metrics')
                        ->select('user_1_id as user_id', 'eval')
                        ->unionAll(
                            DB::table('ins_omv_metrics')->select('user_2_id as user_id', 'eval')
                        )
                        ->whereIn('eval', ['on_time', 'on_time_manual'])
                        ->whereBetween('start_at', [$start, $end])
                        ->when($this->line, function (Builder $query) {
                            $query->where('line', $this->line);
                        })
                        ->when($this->team, function (Builder $query) {
                            $query->where('team', $this->team);
                        });
                }, 'user_data')
                ->groupBy('user_id');
            })->select(
                    'user_id', 
                    'total_on_time', 
                    'count_on_time', 
                    'count_on_time_manual', 
                    DB::raw('RANK() OVER (ORDER BY total_on_time DESC) as user_rank'),
                    'users.emp_id', 
                    'users.name', 
                    'users.photo'
                )
                // Join with users table to get emp_id, name, and photo
                ->join('users', 'users.id', '=', 'user_id')
                ->limit(10) // Limit to top 10 users
                ->get();

                $highestBatchUsers = DB::table(function ($query) use ($start, $end) {
                    $query->select(
                            'user_id',
                            DB::raw('COUNT(*) as total_batch')
                        )
                        ->from(function ($subQuery) use ($start, $end) {
                            $subQuery->from('ins_omv_metrics')
                                ->select('user_1_id as user_id')
                                ->unionAll(
                                    DB::table('ins_omv_metrics')->select('user_2_id as user_id')
                                )
                                ->whereBetween('start_at', [$start, $end])
                                ->when($this->line, function (Builder $query) {
                                    $query->where('line', $this->line);
                                })
                                ->when($this->team, function (Builder $query) {
                                    $query->where('team', $this->team);
                                });
                        }, 'user_data')
                        ->groupBy('user_id');
                    })->select(
                        'user_id',
                        'total_batch',
                        DB::raw('RANK() OVER (ORDER BY total_batch DESC) as user_rank'),
                        'users.emp_id',
                        'users.name',
                        'users.photo'
                    )
                    // Join with users table to get emp_id, name, and photo
                    ->join('users', 'users.id', '=', 'user_id')
                    ->limit(10) // Limit to top 10 users
                    ->get();

        return [
            'mostOnTimeUsers' => $mostOnTimeUsers,
            'highestBatchUsers' => $highestBatchUsers,
        ];
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
                <div class="w-full lg:w-32">
                    <label for="metrics-team"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                    <x-text-input id="metrics-team" wire:model.live="team" type="text" list="metrics-teams" />
                    <datalist id="metrics-teams">
                        <option value="A"></option>
                        <option value="B"></option>
                        <option value="C"></option>
                    </datalist>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-center gap-x-2 items-center">
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
    </div>
    <div wire:key="modals"> 

    </div>
    <div wire:key="omv-summary-worker-perf" class="grid grid-cols-1 sm:grid-cols-2 gap-x-3">
        <div>
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                {{ __('Paling disiplin') }}</h1>
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('Peringkat') }}</th>
                        <th>{{ __('Nama') }}</th>
                        <th>{{ __('Nomor karyawan') }}</th>
                        <th>{{ __('Batch tepat waktu') }}</th>
                    </tr>
                    @foreach ($mostOnTimeUsers as $mostOnTimeUser)
                    <tr>
                        <td>{{ $mostOnTimeUser->user_rank }}</td>
                        <td>
                            <div class="flex gap-x-2">
                                <div class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                    @if($mostOnTimeUser->photo ?? false)
                                    <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'. $mostOnTimeUser->photo }}" />
                                    @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                    @endif
                                </div>
                                <div>{{ $mostOnTimeUser->name }}</div>
                            </div>
                        </td>
                        <td>{{ $mostOnTimeUser->emp_id }}</td>
                        <td>{{ $mostOnTimeUser->count_on_time . '/' . $mostOnTimeUser->count_on_time_manual}}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div>
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                {{ __('Paling produktif') }}</h1>
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('Peringkat') }}</th>
                        <th>{{ __('Nama') }}</th>
                        <th>{{ __('Nomor karyawan') }}</th>
                        <th>{{ __('Jumlah batch') }}</th>
                    </tr>
                    @foreach ($highestBatchUsers as $highestBatchUser)
                    <tr>
                        <td>{{ $highestBatchUser->user_rank }}</td>
                        <td>
                            <div class="flex gap-x-2">
                                <div class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                    @if($highestBatchUser->photo ?? false)
                                    <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'. $highestBatchUser->photo }}" />
                                    @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                    @endif
                                </div>
                                <div>{{ $highestBatchUser->name }}</div>
                            </div>
                        </td>
                        <td>{{ $highestBatchUser->emp_id }}</td>
                        <td>{{ $highestBatchUser->total_batch}}</td>
                    </tr>
                    @endforeach
                </table>
            </div> 
        </div>
    </div>
</div>