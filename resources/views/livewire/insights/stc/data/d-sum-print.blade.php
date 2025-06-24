<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {

    public array $d_sum = [

        // defaults
        'created_at'        => '',
        'started_at'        => '',
        'ended_at'          => '',
        'speed'             => '',
        'sequence'          => '',
        'position'          => '',
        'sv_values'         => [],
        'formula_id'        => '',
        'sv_used'           => '',
        'is_applied'        => '',
        'target_values'     => [],
        'hb_values'         => [],
        'svp_values'        => [],
        
        // relationship
        'user' => [
            'photo'         => '',
            'name'          => '',
            'emp_id'        => ''
        ],
        'ins_stc_machine'   => [
            'line'          => '',
            'name'          => '',
        ],
        'ins_stc_device'    => [
            'code'          => '',
            'name'          => '',
        ],
        'ins_stc_d_logs'    => [],
        
        // calculated
        'duration'              => '',
        'latency'               => '',
        'adjustment_friendly'   => '',
        'integrity_friendly'    => '',
    ];

    #[On('print-prepare')]
    public function load($d_sum)
    {
        $this->d_sum = $d_sum;
        $this->dispatch('print-execute');
    }
};

?>

<div id="print-container" class="w-[1200px] mx-auto p-8 aspect-[297/210] bg-white text-neutral-900 cal-offscreen">
    <div class="flex flex-col gap-6 w-full h-full">
        <div class="grow-0">
            <div id="print-container-header">
                <div class="flex gap-x-6 justify-between">
                    <div class="flex flex-col">
                        <dt class="mb-3 text-neutral-500 text-xs uppercase">{{ __('Informasi dasar') }}</dt>
                        <dd>
                            <table>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Urutan') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $d_sum['sequence'] }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Operator') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td class="truncate">
                                        {{ $d_sum['user']['emp_id'] . ' | '. $d_sum['user']['name'] }}                                        
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Kode alat ukur') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $d_sum['ins_stc_device']['code'] . ' | ' . $d_sum['ins_stc_device']['name'] }}
                                    </td>
                                </tr>
                            </table>
                        </dd>
                    </div>
                    <div class="flex flex-col">
                        <dt class="mb-3 text-neutral-500 text-xs uppercase">{{ __('Informasi mesin') }}</dt>
                        <dd>
                            <table>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Line') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $d_sum['ins_stc_machine']['line'] . ' ' . ($d_sum['position'] == 'upper' ? '△' : ($d_sum['position'] == 'lower' ? '▽' : '')) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Mesin') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td class="truncate">
                                        {{ $d_sum['ins_stc_machine']['name'] }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Kecepatan') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $d_sum['speed'] . ' RPM' }}
                                    </td>
                                </tr>
                            </table>
                        </dd>
                    </div>
                    <div class="flex flex-col">
                    <dt class="mb-3 text-neutral-500 text-xs uppercase">{{ __('Informasi pengukuran') }}</dt>
                        <dd>
                            <table class="mt-3 text-sm text-neutral-500">
                                <tr>
                                    <td class="text-xs">
                                        {{ __('Waktu awal') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $d_sum['started_at'] }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-xs">
                                        {{ __('Durasi') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td class="truncate">
                                        {{ $d_sum['duration'] . ' ' . __('dari') . ' ' . count($d_sum['ins_stc_d_logs']) . ' ' . __('baris data') }}                                      
                                    </td>
                                </tr>
                            </table>
                        </dd>
                    </div>
                </div>
            </div>
        </div>
        <div class="grow border border-neutral-500 rounded-lg overflow-hidden">
            <div id="print-chart-container" class="h-[85%]" wire:key="print-chart-container" wire:ignore></div>
        </div>
        <div class="grow-0">
            <div id="print-container-footer">
                <div class="grid grid-cols-3 gap-8 p-4">
                    <table class="w-full text-sm text-center">
                        <tr class="text-xs uppercase text-neutral-500 dark:text-neutral-400 border-b border-neutral-300 dark:border-neutral-700">
                            <td></td>
                            <td>1</td>
                            <td>2</td>
                            <td>3</td>
                            <td>4</td>
                            <td>5</td>
                            <td>6</td>
                            <td>7</td>
                            <td>8</td>
                        </tr>
                        <tr>
                            <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('HB') }}</td>
                            @foreach($d_sum['hb_values'] as $hb_value)
                                <td>{{ $hb_value }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('SV') }}</td>
                            @foreach($d_sum['sv_values'] as $sv_value)
                                <td>{{ $sv_value }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('SVP') }}</td>
                            @foreach($d_sum['svp_values'] as $svp_value)
                                <td>{{ $svp_value }}</td>
                            @endforeach
                        </tr>
                    </table>
                    <div>
                        <table class="w-full text-sm">
                            <tr class="text-xs uppercase text-neutral-500 dark:text-neutral-400 border-b border-neutral-300 dark:border-neutral-700">
                                <td></td>
                                <td>{{ __('Legenda') }}</td>
                            </tr>
                            <tr>
                                <td class="text-center px-3">HB</td>
                                <td>{{ __('Median hasil ukur hobo')}}</td>
                            </tr>
                            <tr>
                                <td class="text-center px-3">SV</td>
                                <td>{{ __('SV ketika mesin diukur dengan hobo')}}</td>
                            </tr>
                            <tr>
                                <td class="text-center px-3">SVP</td>
                                <td>{{ __('SV prediksi setelah diukur dengan hobo')}}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="flex justify-end">                        
                        <div class="flex gap-x-3">
                            <div>
                                <div class="text-center font-bold">CE</div>
                                <div class="flex justify-center">
                                    <div class="w-8 h-8 my-4 bg-neutral-200 rounded-full overflow-hidden">
                                        @if ($d_sum['user']['photo'])
                                            <img class="w-full h-full object-cover"
                                                src="{{ '/storage/users/' . $d_sum['user']['photo'] }}" />
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                class="block fill-current text-neutral-800  opacity-25" viewBox="0 0 1000 1000"
                                                xmlns:v="https://vecta.io/nano">
                                                <path
                                                    d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                            </svg>
                                        @endif
                                    </div>
                                </div>
                                <hr class="border-neutral-300 w-48">
                                <div class="text-center">
                                    <div class="text-xs">{{ $d_sum['user']['name'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
