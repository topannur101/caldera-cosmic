<?php

use Livewire\Volt\Component;

use App\Models\InsStcMachine;
use App\Models\InsStcDSum;
use App\Models\InsStcMLog;

new class extends Component {
    public function with(): array
    {
        $machines = InsStcMachine::orderBy('line')->get()->toArray();

        foreach ($machines as $key => $machine) {
            $upper_d_sum = InsStcDSum::where('position', 'upper')
                ->where('ins_stc_machine_id', $machine['id'])
                ->latest('ended_at')
                ->first();
            $upper_m_log = InsStcMLog::where('position', 'upper')
                ->where('ins_stc_machine_id', $machine['id'])
                ->latest('created_at')
                ->first();
            $upper_d_sum_sv_temps = $upper_d_sum ? ($upper_d_sum_sv_temps = json_decode($upper_d_sum->sv_temps, true)) : [];
            $machines[$key]['upper']['d_sum'] = $upper_d_sum ? $upper_d_sum->toArray() : [];
            $machines[$key]['upper']['d_sum']['sv_temps'] = $upper_d_sum_sv_temps;
            $machines[$key]['upper']['m_log'] = $upper_m_log ? $upper_m_log->toArray() : [];

            $lower_d_sum = InsStcDSum::where('position', 'lower')
                ->where('ins_stc_machine_id', $machine['id'])
                ->latest('ended_at')
                ->first();
            $lower_m_log = InsStcMLog::where('position', 'lower')
                ->where('ins_stc_machine_id', $machine['id'])
                ->latest('created_at')
                ->first();
            $lower_d_sum_sv_temps = $lower_d_sum ? ($lower_d_sum_sv_temps = json_decode($lower_d_sum->sv_temps, true)) : [];
            $machines[$key]['lower']['d_sum'] = $lower_d_sum ? $lower_d_sum->toArray() : [];
            $machines[$key]['lower']['d_sum']['sv_temps'] = $lower_d_sum_sv_temps;
            $machines[$key]['lower']['m_log'] = $lower_m_log ? $lower_m_log->toArray() : [];
        }

        return [
            'machines' => $machines,
        ];
    }
};

?>

<div wire:poll.60s>
    <div wire:key="modals">
        <x-modal name="d_sum-show" maxWidth="xl">
            <livewire:insight.stc.summary.d-sum-show />
        </x-modal>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach ($machines as $machine)
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
                <div class="flex">
                    <div
                        class="flex items-center border-r border-neutral-100 dark:border-neutral-700 p-4 font-mono text-2xl">
                        {{ sprintf('%02d', $machine['line']) }}
                    </div>
                    <div class="grow">
                        <div
                            class="flex items-center px-3 py-6 gap-x-3 border-b border-neutral-100 dark:border-neutral-700">
                            <div>
                                <i class="fa fa-chevron-up"></i>
                            </div>
                            <div class="grow grid grid-cols-1 gap-y-6">
                                <div>
                                    <label class="flex gap-x-2 px-3 mb-4 uppercase text-xs text-neutral-500">
                                        <div>{{ __('Pembacaan alat') }}</div>
                                        <div>•</div>
                                        @if (isset($machine['upper']['d_sum']['ended_at']))
                                            <x-text-button type="button"
                                                x-on:click="$dispatch('open-modal', 'd_sum-show'); $dispatch('d_sum-show', { id: '{{ $machine['upper']['d_sum']['id'] ?? 0 }}'})">
                                                <div class="flex gap-x-2 uppercase">
                                                    <div>
                                                        {{ Carbon\Carbon::parse($machine['upper']['d_sum']['ended_at'])->diffForHumans() }}
                                                    </div>
                                                    <i class="fa fa-arrow-up-right-from-square"></i>
                                                </div>
                                            </x-text-button>
                                        @else
                                            <div class="text-red-500"><i
                                                    class="fa fa-exclamation-circle mr-2"></i>{{ __('Tak ditemukan') }}
                                            </div>
                                        @endif
                                    </label>
                                    <div
                                        class="grid grid-cols-9 text-center gap-x-3 mb-1 text-xs uppercase font-normal leading-none text-neutral-500">
                                        <div>S</div>
                                        <div>1</div>
                                        <div>2</div>
                                        <div>3</div>
                                        <div>4</div>
                                        <div>5</div>
                                        <div>6</div>
                                        <div>7</div>
                                        <div>8</div>
                                    </div>
                                    <div class="grid grid-cols-9 text-center gap-x-3">
                                        <div>HB</div>
                                        <div>{{ $machine['upper']['d_sum']['section_1'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['section_2'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['section_3'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['section_4'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['section_5'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['section_6'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['section_7'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['section_8'] ?? '-' }}</div>

                                        <div>SV</div>
                                        <div>{{ $machine['upper']['d_sum']['sv_temps'][0] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['sv_temps'][1] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['sv_temps'][2] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['sv_temps'][3] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['sv_temps'][4] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['sv_temps'][5] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['sv_temps'][6] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['d_sum']['sv_temps'][7] ?? '-' }}</div>

                                    </div>
                                </div>
                                <div>
                                    <label class="flex gap-x-2 px-3 mb-4 uppercase text-xs text-neutral-500">
                                        <div>{{ __('Pembacaan mesin') }}</div>
                                        <div>•</div>
                                        @if (isset($machine['upper']['m_log']['created_at']))
                                            <div>
                                                {{ Carbon\Carbon::parse($machine['upper']['m_log']['created_at'])->diffForHumans() }}
                                            </div>
                                        @else
                                            <div class="text-red-500"><i
                                                    class="fa fa-exclamation-circle mr-2"></i>{{ __('Tak ditemukan') }}
                                            </div>
                                        @endif
                                    </label>
                                    <div class="grid grid-cols-9 text-center gap-x-3">
                                        <div>PV</div>
                                        <div>{{ $machine['upper']['m_log']['pv_r_1'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['pv_r_2'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['pv_r_3'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['pv_r_4'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['pv_r_5'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['pv_r_6'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['pv_r_7'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['pv_r_8'] ?? '-' }}</div>
                                    </div>
                                    <div class="grid grid-cols-9 text-center gap-x-3">
                                        <div>SV</div>
                                        <div>{{ $machine['upper']['m_log']['sv_r_1'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['sv_r_2'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['sv_r_3'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['sv_r_4'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['sv_r_5'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['sv_r_6'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['sv_r_7'] ?? '-' }}</div>
                                        <div>{{ $machine['upper']['m_log']['sv_r_8'] ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center px-3 py-6 gap-x-3">
                            <div>
                                <i class="fa fa-chevron-down"></i>
                            </div>
                            <div class="grow grid grid-cols-1 gap-y-6">
                                <div>
                                    <label class="flex gap-x-2 px-3 mb-4 uppercase text-xs text-neutral-500">
                                        <div>{{ __('Pembacaan alat') }}</div>
                                        <div>•</div>
                                        @if (isset($machine['lower']['d_sum']['ended_at']))
                                            <x-text-button type="button"
                                                x-on:click="$dispatch('open-modal', 'd_sum-show'); $dispatch('d_sum-show', { id: '{{ $machine['lower']['d_sum']['id'] ?? 0 }}'})">
                                                <div class="flex gap-x-2 uppercase">
                                                    <div>
                                                        {{ Carbon\Carbon::parse($machine['lower']['d_sum']['ended_at'])->diffForHumans() }}
                                                    </div>
                                                    <i class="fa fa-arrow-up-right-from-square"></i>
                                                </div>
                                            </x-text-button>
                                        @else
                                            <div class="text-red-500"><i
                                                    class="fa fa-exclamation-circle mr-2"></i>{{ __('Tak ditemukan') }}
                                            </div>
                                        @endif
                                    </label>
                                    <div
                                        class="grid grid-cols-9 text-center gap-x-3 mb-1 text-xs uppercase font-normal leading-none text-neutral-500">
                                        <div>S</div>
                                        <div>1</div>
                                        <div>2</div>
                                        <div>3</div>
                                        <div>4</div>
                                        <div>5</div>
                                        <div>6</div>
                                        <div>7</div>
                                        <div>8</div>
                                    </div>
                                    <div class="grid grid-cols-9 text-center gap-x-3">
                                        <div>HB</div>
                                        <div>{{ $machine['lower']['d_sum']['section_1'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['section_2'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['section_3'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['section_4'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['section_5'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['section_6'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['section_7'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['section_8'] ?? '-' }}</div>

                                        <div>SV</div>
                                        <div>{{ $machine['lower']['d_sum']['sv_temps'][0] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['sv_temps'][1] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['sv_temps'][2] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['sv_temps'][3] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['sv_temps'][4] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['sv_temps'][5] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['sv_temps'][6] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['d_sum']['sv_temps'][7] ?? '-' }}</div>

                                    </div>
                                </div>
                                <div>
                                    <label class="flex gap-x-2 px-3 mb-4 uppercase text-xs text-neutral-500">
                                        <div>{{ __('Pembacaan mesin') }}</div>
                                        <div>•</div>
                                        @if (isset($machine['lower']['m_log']['created_at']))
                                            <div>
                                                {{ Carbon\Carbon::parse($machine['lower']['m_log']['created_at'])->diffForHumans() }}
                                            </div>
                                        @else
                                            <div class="text-red-500"><i
                                                    class="fa fa-exclamation-circle mr-2"></i>{{ __('Tak ditemukan') }}
                                            </div>
                                        @endif
                                    </label>
                                    <div class="grid grid-cols-9 text-center gap-x-3">
                                        <div>PV</div>
                                        <div>{{ $machine['lower']['m_log']['pv_r_1'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['pv_r_2'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['pv_r_3'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['pv_r_4'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['pv_r_5'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['pv_r_6'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['pv_r_7'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['pv_r_8'] ?? '-' }}</div>
                                    </div>
                                    <div class="grid grid-cols-9 text-center gap-x-3">
                                        <div>SV</div>
                                        <div>{{ $machine['lower']['m_log']['sv_r_1'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['sv_r_2'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['sv_r_3'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['sv_r_4'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['sv_r_5'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['sv_r_6'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['sv_r_7'] ?? '-' }}</div>
                                        <div>{{ $machine['lower']['m_log']['sv_r_8'] ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</div>
