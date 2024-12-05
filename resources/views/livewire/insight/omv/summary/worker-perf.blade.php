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
use App\Models\User;

new #[Layout('layouts.app')] class extends Component {
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
        if (!$this->start_at || !$this->end_at) {
            $this->setThisWeek();
        }
    }

    public function with(): array
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $metrics = InsOmvMetric::query()
            ->when($this->line, function (Builder $query) {
                $query->where('line', $this->line);
            })
            ->when($this->team, function (Builder $query) {
                $query->where('team', $this->team);
            })
            ->whereBetween('start_at', [$start, $end])
            ->get()
            ->toArray();

        $highOnTimeUsers = [];

        foreach ($metrics as $metric) {
            $user1Id = $metric['user_1_id'];
            $user2Id = $metric['user_2_id'];

            // Skip if either user_id is null
            if ($user1Id === null || $user2Id === null) {
                continue;
            }

            // Initialize users if not already in the array
            foreach ([$user1Id, $user2Id] as $userId) {
                if (!isset($highOnTimeUsers[$userId])) {
                    $user = User::find($userId);

                    // Skip if user not found
                    if (!$user) {
                        continue;
                    }

                    $highOnTimeUsers[$userId] = [
                        'id' => $userId,
                        'name' => $user->name,
                        'photo' => $user->photo,
                        'emp_id' => $user->emp_id,
                        'count_on_time' => 0,
                        'count_on_time_manual' => 0,
                        'count_too_soon' => 0,
                        'count_too_late' => 0,
                        'total_count_on_time' => 0,
                        'total_count' => 0,
                        'on_time_ratio' => 0,
                    ];
                }
            }

            // Increment counts based on eval
            if ($metric['eval'] === 'on_time') {
                $highOnTimeUsers[$user1Id]['count_on_time']++;
                $highOnTimeUsers[$user2Id]['count_on_time']++;
            } elseif ($metric['eval'] === 'on_time_manual') {
                $highOnTimeUsers[$user1Id]['count_on_time_manual']++;
                $highOnTimeUsers[$user2Id]['count_on_time_manual']++;
            } elseif ($metric['eval'] === 'too_soon') {
                $highOnTimeUsers[$user1Id]['count_too_soon']++;
                $highOnTimeUsers[$user2Id]['count_too_soon']++;
            } elseif ($metric['eval'] === 'too_late') {
                $highOnTimeUsers[$user1Id]['count_too_late']++;
                $highOnTimeUsers[$user2Id]['count_too_late']++;
            }
        }

        // Calculate total counts and on_time_ratio for each user
        foreach ($highOnTimeUsers as &$user) {
            $user['total_count_on_time'] = $user['count_on_time'] + $user['count_on_time_manual'];
            $user['total_count'] = $user['count_on_time'] + $user['count_on_time_manual'] + $user['count_too_soon'] + $user['count_too_late'];

            $user['on_time_ratio'] = $user['total_count'] > 0 ? round(($user['total_count_on_time'] / $user['total_count']) * 100, 1) : 0;
        }

        $highOnTimeUsers = array_map(fn($item) => $item, $highOnTimeUsers);

        usort($highOnTimeUsers, function ($a, $b) {
            if ($b['on_time_ratio'] !== $a['on_time_ratio']) {
                return $b['on_time_ratio'] <=> $a['on_time_ratio'];
            }

            return $b['total_count'] <=> $a['total_count'];
        });

        $highBatchUsers = [];

        foreach ($metrics as $metric) {
            $user1Id = $metric['user_1_id'];
            $user2Id = $metric['user_2_id'];

            // Skip if either user_id is null
            if ($user1Id === null || $user2Id === null) {
                continue;
            }

            // Initialize users if not already in the array
            foreach ([$user1Id, $user2Id] as $userId) {
                if (!isset($highBatchUsers[$userId])) {
                    $user = User::find($userId);

                    // Skip if user not found
                    if (!$user) {
                        continue;
                    }

                    $highBatchUsers[$userId] = [
                        'id' => $userId,
                        'name' => $user->name,
                        'photo' => $user->photo,
                        'emp_id' => $user->emp_id,
                        'total_batch' => 0,
                    ];
                }
            }

            $highBatchUsers[$user1Id]['total_batch']++;
            $highBatchUsers[$user2Id]['total_batch']++;
        }
        usort($highBatchUsers, function ($a, $b) {
            return $b['total_batch'] <=> $a['total_batch'];
        });

        return [
            'highOnTimeUsers' => $highOnTimeUsers,
            'highBatchUsers' => $highBatchUsers,
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
                                <x-text-button class="uppercase ml-3">{{ __('Rentang') }}<i
                                        class="fa fa-fw fa-chevron-down ms-1"></i></x-text-button>
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
                    <x-text-input wire:model.live="end_at" id="cal-date-end" type="date"></x-text-input>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="flex gap-3">
                <div class="w-full lg:w-32">
                    <label for="metrics-line"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-text-input id="metrics-line" wire:model.live="line" type="number" list="metrics-lines"
                        step="1" />
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
    <div wire:key="omv-summary-worker-perf" class="grid grid-cols-1 lg:grid-cols-2 gap-x-3 gap-y-8">
        <div>
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                {{ __('Tepat waktu tertinggi') }}</h1>
            @if ($highOnTimeUsers)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-3 gap-y-6 mb-6 mx-8">
                <div class="mt-0 md:mt-8 order-2 md:order-1">
                    @if(isset($highOnTimeUsers[1]))
                    <div class="relative flex flex-col items-center text-neutral-600 dark:text-neutral-400">
                        <div class="relative w-16 h-16 mb-2">
                            <div class="absolute -top-1 -left-1 text-sm bg-gray-400 text-black rounded-full w-6 h-6 flex items-center justify-center font-bold">
                                2
                            </div>
                            <div class="w-16 h-16 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">

                                @if($highOnTimeUsers[1]['photo'])
                                    <img class="w-full h-full object-cover dark:brightness-80" src="/storage/users/{{ $highOnTimeUsers[1]['photo'] }}" />
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                @endif
                            </div>
                        </div>
                        <div class="text-sm">{{ $highOnTimeUsers[1]['name'] }}</div>
                        <div class="text-xs">{{ $highOnTimeUsers[1]['emp_id'] }}</div>
                        <div class="mt-2">{{ $highOnTimeUsers[1]['on_time_ratio'] . '%' . ' (' . $highOnTimeUsers[1]['total_count_on_time'] . '/' . $highOnTimeUsers[1]['total_count'] . ')' }}</div>

                    </div>
                    @endif
                </div>
                <div class="order-1 md:order-2 mt-6 md:mt-0">
                    @if(isset($highOnTimeUsers[0]))
                    <div class="relative flex flex-col items-center text-neutral-600 dark:text-neutral-400">
                        <div class="relative w-16 h-16 mb-2">
                            <div class="absolute -top-1 -left-1 text-sm bg-yellow-400 text-black rounded-full w-6 h-6 flex items-center justify-center font-bold">
                                1
                            </div>
                            <div class="w-16 h-16 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">

                                @if($highOnTimeUsers[0]['photo'])
                                    <img class="w-full h-full object-cover dark:brightness-80" src="/storage/users/{{ $highOnTimeUsers[0]['photo'] }}" />
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                @endif
                            </div>
                        </div>
                        <div class="text-sm">{{ $highOnTimeUsers[0]['name'] }}</div>
                        <div class="text-xs">{{ $highOnTimeUsers[0]['emp_id'] }}</div>
                        <div class="mt-2">{{ $highOnTimeUsers[0]['on_time_ratio'] . '%' . ' (' . $highOnTimeUsers[0]['total_count_on_time'] . '/' . $highOnTimeUsers[0]['total_count'] . ')' }}</div>
                    </div>
                    @endif
                </div>
                <div class="mt-0 md:mt-8 order-3">
                    @if(isset($highOnTimeUsers[2]))
                    <div class="relative flex flex-col items-center text-neutral-600 dark:text-neutral-400">
                        <div class="relative w-16 h-16 mb-2">
                            <div class="absolute -top-1 -left-1 text-sm bg-orange-300  text-black rounded-full w-6 h-6 flex items-center justify-center font-bold">
                                3
                            </div>
                            <div class="w-16 h-16 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">

                                @if($highOnTimeUsers[1]['photo'])
                                    <img class="w-full h-full object-cover dark:brightness-80" src="/storage/users/{{ $highOnTimeUsers[2]['photo'] }}" />
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                @endif
                            </div>
                        </div>
                        <div class="text-sm">{{ $highOnTimeUsers[2]['name'] }}</div>
                        <div class="text-xs">{{ $highOnTimeUsers[2]['emp_id'] }}</div>
                        <div class="mt-2">{{ $highOnTimeUsers[2]['on_time_ratio'] . '%' . ' (' . $highOnTimeUsers[2]['total_count_on_time'] . '/' . $highOnTimeUsers[2]['total_count'] . ')' }}</div>

                    </div>
                    @endif
                </div>
            </div>
            @if(isset($highOnTimeUsers[3]))
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                    <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                        <tr class="uppercase text-xs">
                            <th>{{ __('Peringkat') }}</th>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Nomor karyawan') }}</th>
                            <th>{{ __('Rasio %') }}</th>
                        </tr>
                        @foreach ($highOnTimeUsers as $highOnTimeUser)
                            @if ($loop->iteration > 3)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="flex gap-x-2">
                                        <div
                                            class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                            @if ($highOnTimeUser['photo'] ?? false)
                                                <img class="w-full h-full object-cover dark:brightness-75"
                                                    src="{{ '/storage/users/' . $highOnTimeUser['photo'] }}" />
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                                    viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                                    <path
                                                        d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div>{{ $highOnTimeUser['name'] }}</div>
                                    </div>
                                </td>
                                <td>{{ $highOnTimeUser['emp_id'] }}</td>
                                <td>{{ $highOnTimeUser['on_time_ratio'] . '%' . ' (' . $highOnTimeUser['total_count_on_time'] . '/' . $highOnTimeUser['total_count'] . ')' }}
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    </table>
                </div>
                @endif
            @else
                <div class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="fa fa-list relative"><i
                                class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Tak ada data') }}
                    </div>
                </div>
            @endif
        </div>
        <div>
            <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                {{ __('Jumlah batch tertinggi') }}</h1>
            @if ($highBatchUsers)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-3 gap-y-6 mb-6 mx-8">
                <div class="mt-0 md:mt-8 order-2 md:order-1">
                    @if(isset($highBatchUsers[1]))
                    <div class="relative flex flex-col items-center text-neutral-600 dark:text-neutral-400">
                        <div class="relative w-16 h-16 mb-2">
                            <div class="absolute -top-1 -left-1 text-sm bg-gray-400 text-black rounded-full w-6 h-6 flex items-center justify-center font-bold">
                                2
                            </div>
                            <div class="w-16 h-16 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">

                                @if($highBatchUsers[1]['photo'])
                                    <img class="w-full h-full object-cover dark:brightness-80" src="/storage/users/{{ $highBatchUsers[1]['photo'] }}" />
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                @endif
                            </div>
                        </div>
                        <div class="text-sm">{{ $highBatchUsers[1]['name'] }}</div>
                        <div class="text-xs">{{ $highBatchUsers[1]['emp_id'] }}</div>
                        <div class="mt-2">{{ $highBatchUsers[1]['total_batch'] }}</div>
                    </div>
                    @endif
                </div>
                <div class="order-1 md:order-2 mt-6 md:mt-0">
                    @if(isset($highBatchUsers[0]))
                    <div class="relative flex flex-col items-center text-neutral-600 dark:text-neutral-400">
                        <div class="relative w-16 h-16 mb-2">
                            <div class="absolute -top-1 -left-1 text-sm bg-yellow-400 text-black rounded-full w-6 h-6 flex items-center justify-center font-bold">
                                1
                            </div>
                            <div class="w-16 h-16 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">

                                @if($highBatchUsers[0]['photo'])
                                    <img class="w-full h-full object-cover dark:brightness-80" src="/storage/users/{{ $highBatchUsers[0]['photo'] }}" />
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                @endif
                            </div>
                        </div>
                        <div class="text-sm">{{ $highBatchUsers[0]['name'] }}</div>
                        <div class="text-xs">{{ $highBatchUsers[0]['emp_id'] }}</div>
                        <div class="mt-2">{{ $highBatchUsers[0]['total_batch'] }}</div>
                    </div>
                    @endif
                </div>
                <div class="mt-0 md:mt-8 order-3">
                    @if(isset($highBatchUsers[2]))
                    <div class="relative flex flex-col items-center text-neutral-600 dark:text-neutral-400">
                        <div class="relative w-16 h-16 mb-2">
                            <div class="absolute -top-1 -left-1 text-sm bg-orange-300  text-black rounded-full w-6 h-6 flex items-center justify-center font-bold">
                                3
                            </div>
                            <div class="w-16 h-16 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">

                                @if($highBatchUsers[1]['photo'])
                                    <img class="w-full h-full object-cover dark:brightness-80" src="/storage/users/{{ $highBatchUsers[2]['photo'] }}" />
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                @endif
                            </div>
                        </div>
                        <div class="text-sm">{{ $highBatchUsers[2]['name'] }}</div>
                        <div class="text-xs">{{ $highBatchUsers[2]['emp_id'] }}</div>
                        <div class="mt-2">{{ $highBatchUsers[2]['total_batch'] }}</div>
                    </div>
                    @endif
                </div>
            </div>
            @if(isset($highBatchUsers[3]))
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                    <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                        <tr class="uppercase text-xs">
                            <th>{{ __('Peringkat') }}</th>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Nomor karyawan') }}</th>
                            <th>{{ __('Jumlah batch') }}</th>
                        </tr>
                        @foreach ($highBatchUsers as $highBatchUser)
                            @if ($loop->iteration > 3)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="flex gap-x-2">
                                        <div
                                            class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                            @if ($highBatchUser['photo'] ?? false)
                                                <img class="w-full h-full object-cover dark:brightness-75"
                                                    src="{{ '/storage/users/' . $highBatchUser['photo'] }}" />
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                                    viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                                    <path
                                                        d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div>{{ $highBatchUser['name'] }}</div>
                                    </div>
                                </td>
                                <td>{{ $highBatchUser['emp_id'] }}</td>
                                <td>{{ $highBatchUser['total_batch'] }}</td>
                            </tr>
                            @endif
                        @endforeach
                    </table>
                </div>
                @endif
            @else
                <div class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="fa fa-list relative"><i
                                class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Tak ada data') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
