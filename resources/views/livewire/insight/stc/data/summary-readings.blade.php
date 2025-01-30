<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\Models\InsStcMachine;
use App\Models\InsStcDSum;
use App\Models\InsStcMLog;
use App\Models\InsStcDLog;
use App\InsStc;
use Carbon\Carbon;

new class extends Component {

    public array $d_sum_ids = [];
    public string $present_mode = 'standard';

    #[On('update')]
    public function update()
    {
        $d_sums = InsStcDLog::whereIn('ins_stc_d_sum_id', $this->d_sum_ids)
            ->get()
            ->groupBy('ins_stc_d_sum_id');

        switch ($this->present_mode) {
        case 'standard':
            $chartOptions = InsStc::getStandardZoneChartOptions($d_sums, 100, 100);
            break;

        case 'adaptive':
            $chartOptions = InsStc::getBoxplotChartOptions($d_sums, 100, 100);
            break;
        }

        $this->js(
                "
                let recentsOptions = " .
                    json_encode($chartOptions) .
                    ";

                // Render recents chart
                const recentsChartContainer = \$wire.\$el.querySelector('#recents-chart-container');
                recentsChartContainer.innerHTML = '<div id=\"recents-chart\"></div>';
                let recentsChart = new ApexCharts(recentsChartContainer.querySelector('#recents-chart'), recentsOptions);
                recentsChart.render();
            ",
            );        
    }

    public function with(): array
    {
        $machines = InsStcMachine::orderBy('line')->get()->toArray();

        foreach ($machines as $key => $machine) {
            $upper_d_sum = InsStcDSum::where('position', 'upper')
                ->where('ins_stc_machine_id', $machine['id'])
                ->where('created_at', '>=', Carbon::now()->subHours(5))
                ->latest('created_at')
                ->first();

            $tvs =InsStc::$target_values; // [ 78, 73, 68, 63, 58, 53, 48, 43 ]

            $upper_d_sum_hb_values = $upper_d_sum ? json_decode($upper_d_sum->hb_values, true) : [];
            $machines[$key]['upper']['d_sum'] = $upper_d_sum ? $upper_d_sum->toArray() : [];
            $machines[$key]['upper']['d_sum']['hb_values'] = $upper_d_sum_hb_values;
            $machines[$key]['upper']['d_sum']['hb_diff'] = array_map(function ($hb, $tv) {
                return $hb ? $hb - $tv : 0;
            }, $upper_d_sum_hb_values, $tvs);
            $machines[$key]['upper']['d_sum']['hb_eval'] = array_map(function ($diff) {
                return $diff > 5 || $diff < -5;
            }, $machines[$key]['upper']['d_sum']['hb_diff']);

            $lower_d_sum = InsStcDSum::where('position', 'lower')
                ->where('ins_stc_machine_id', $machine['id'])
                ->where('created_at', '>=', Carbon::now()->subHours(5))
                ->latest('created_at')
                ->first();

            $lower_d_sum_hb_values = $lower_d_sum ? json_decode($lower_d_sum->hb_values, true) : [];
            $machines[$key]['lower']['d_sum'] = $lower_d_sum ? $lower_d_sum->toArray() : [];
            $machines[$key]['lower']['d_sum']['hb_values'] = $lower_d_sum_hb_values;
            $machines[$key]['lower']['d_sum']['hb_diff'] = array_map(function ($hb, $tv) {
                return $hb ? $hb - $tv : 0;
            }, $lower_d_sum_hb_values, $tvs);
            $machines[$key]['lower']['d_sum']['hb_eval'] = array_map(function ($diff) {
                return $diff > 5 || $diff < -5;
            }, $machines[$key]['lower']['d_sum']['hb_diff']);
        }

        $this->d_sum_ids = $this->extractDSumIds($machines);

        return [
            'machines' => $machines,
        ];
    }

    private function extractDSumIds($data)
    {
        $ids = [];
            
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    if (isset($value['d_sum']['id'])) {
                        $ids[] = $value['d_sum']['id'];
                    }
                    
                    // Recursively search nested arrays
                    $nestedIds = $this->extractDSumIds($value);
                    $ids = array_merge($ids, $nestedIds);
                }
            }
            
            return $ids;
    }

    public function updated()
    {
        $this->update();
    }
};

?>

<div wire:poll.60s>
    <div wire:key="modals">
        <x-modal name="d_sum-show" maxWidth="3xl">
            <livewire:insight.stc.data.d-sum-show />
        </x-modal>
        <x-modal name="different-zoning-help">
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Pembagian zona') }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')"><i
                            class="fa fa-times"></i></x-text-button>
                </div>
                <div class="grid gap-y-6 mt-6 text-sm text-neutral-600 dark:text-neutral-400">
                    <div>
                        {{ __('Terdapat dua modus pembagian zona pada bagan.') }}
                    </div>
                    <div>
                        <span class="font-bold">{{ __('Pembagian zona standar') . ': ' }}</span>{{ __('Menggunakan semua titik data dan ditempatkan pada pembagian zona standar pada panjang data yang tetap sebesar 54 menit.') }}
                    </div>
                    <div>
                        <span class="font-bold">{{ __('Pembagian zona adaptif') . ': ' }}</span>{{ __('Mengabaikan titik data yang dianggap temperatur ruangan (di bawah 40°C) agar SV prediksi akurat sehingga pembagian zona akan tergantung dengan panjang data.') }}
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Paham') }}
                    </x-primary-button>
                </div>
            </div>
        </x-modal>
    </div>
    <div class="flex justify-between gap-8 flex-col lg:flex-row">
        <div class="px-8 py-4 text-sm">
            @switch($present_mode)
                @case('adaptive')
                    {{ __('Pembagian zona adaptif diterapkan pada bagan berikut') }}
                    @break
                @case('standard')
                    {{ __('Pembagian zona standar diterapkan pada bagan berikut') }}
                    @break                    
            @endswitch
            <x-text-button type="button"
            class="ml-2" x-data="" x-on:click="$dispatch('open-modal', 'different-zoning-help')"><i
                class="far fa-question-circle"></i></x-text-button>
        </div>
        <div class="btn-group h-10">
            <x-radio-button wire:model.live="present_mode" grow value="adaptive" name="present_mode" id="present_mode_adaptive">
                {{ __('Adaptif') }}
            </x-radio-button>
            <x-radio-button wire:model.live="present_mode" grow value="standard" name="present_mode" id="present_mode_standard">
                {{ __('Standard') }}
            </x-radio-button>
        </div>
    </div>
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg h-80 p-4 mb-8"
    id="recents-chart-container" wire:key="recents-chart-container" wire:ignore>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach ($machines as $machine)
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg text-sm">
                <div class="flex">
                    <div
                        class="flex items-center border-r border-neutral-100 dark:border-neutral-700 p-4 font-mono text-2xl">
                        {{ sprintf('%02d', $machine['line']) }}
                    </div>
                    <div class="grow">
                        <div
                            class="flex items-center border-b border-neutral-100 dark:border-neutral-700">
                            <div class="px-2">△</div>
                            <div class="grow grid grid-cols-1 py-3">
                                <div>
                                    <div
                                        class="grid grid-cols-8 text-center px-3 gap-x-3 mb-1 text-xs uppercase font-normal leading-none text-neutral-500">
                                        <div>1</div>
                                        <div>2</div>
                                        <div>3</div>
                                        <div>4</div>
                                        <div>5</div>
                                        <div>6</div>
                                        <div>7</div>
                                        <div>8</div>
                                    </div>
                                    <div class="grid grid-cols-8 text-center px-3 gap-x-3">
                                        <div class="rounded {{ $machine['upper']['d_sum']['hb_eval'][0] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['upper']['d_sum']['hb_values'][0] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['upper']['d_sum']['hb_eval'][1] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['upper']['d_sum']['hb_values'][1] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['upper']['d_sum']['hb_eval'][2] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['upper']['d_sum']['hb_values'][2] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['upper']['d_sum']['hb_eval'][3] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['upper']['d_sum']['hb_values'][3] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['upper']['d_sum']['hb_eval'][4] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['upper']['d_sum']['hb_values'][4] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['upper']['d_sum']['hb_eval'][5] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['upper']['d_sum']['hb_values'][5] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['upper']['d_sum']['hb_eval'][6] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['upper']['d_sum']['hb_values'][6] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['upper']['d_sum']['hb_eval'][7] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['upper']['d_sum']['hb_values'][7] ?? '-' }}</div>                                    
                                    </div>
                                </div>
                            </div>
                            <div class="flex px-2 flex-col">
                                @if (isset($machine['upper']['d_sum']['ended_at']))
                                <x-text-button type="button" x-data="" class="px-3 py-2" type="button"
                                x-on:click="$dispatch('open-modal', 'd_sum-show'); $dispatch('d_sum-show', { id: '{{ $machine['upper']['d_sum']['id'] ?? 0 }}'})"><i class="fa-regular fa-eye"></i></x-text-button>
                                @endif
                                <x-link href="{{ route('insight.stc.data.index', [ 'view' => 'history', 'mode' => 'recents', 'line' => $machine['line'], 'position' => 'upper' ])}}" class="px-3 py-2" wire:navigate>
                                    <i class="fa-regular fa-clock"></i>
                                </x-link>
                            </div>
                        </div>
                        <div
                            class="flex items-center">
                            <div class="px-2">▽</div>
                            <div class="grow grid grid-cols-1 py-3">
                                <div>
                                    <div
                                        class="grid grid-cols-8 text-center px-3 gap-x-3 mb-1 text-xs uppercase font-normal leading-none text-neutral-500">
                                        <div>1</div>
                                        <div>2</div>
                                        <div>3</div>
                                        <div>4</div>
                                        <div>5</div>
                                        <div>6</div>
                                        <div>7</div>
                                        <div>8</div>
                                    </div>
                                    <div class="grid grid-cols-8 text-center px-3 gap-x-3">
                                        <div class="rounded {{ $machine['lower']['d_sum']['hb_eval'][0] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['lower']['d_sum']['hb_values'][0] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['lower']['d_sum']['hb_eval'][1] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['lower']['d_sum']['hb_values'][1] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['lower']['d_sum']['hb_eval'][2] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['lower']['d_sum']['hb_values'][2] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['lower']['d_sum']['hb_eval'][3] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['lower']['d_sum']['hb_values'][3] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['lower']['d_sum']['hb_eval'][4] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['lower']['d_sum']['hb_values'][4] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['lower']['d_sum']['hb_eval'][5] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['lower']['d_sum']['hb_values'][5] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['lower']['d_sum']['hb_eval'][6] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['lower']['d_sum']['hb_values'][6] ?? '-' }}</div>
                                        <div class="rounded {{ $machine['lower']['d_sum']['hb_eval'][7] ? 'bg-red-200 dark:bg-red-800' : '' }}">{{ $machine['lower']['d_sum']['hb_values'][7] ?? '-' }}</div>                                    
                                    </div>
                                </div>
                            </div>
                            <div class="flex px-2 flex-col">
                                @if (isset($machine['lower']['d_sum']['ended_at']))
                                <x-text-button type="button" x-data="" class="px-3 py-2" type="button"
                                x-on:click="$dispatch('open-modal', 'd_sum-show'); $dispatch('d_sum-show', { id: '{{ $machine['lower']['d_sum']['id'] ?? 0 }}'})"><i class="fa-regular fa-eye"></i></x-text-button>
                                @endif
                                <x-link href="{{ route('insight.stc.data.index', [ 'view' => 'history', 'mode' => 'recents', 'line' => $machine['line'], 'position' => 'lower' ])}}" class="px-3 py-2" wire:navigate>
                                    <i class="fa-regular fa-clock"></i>
                                </x-link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript