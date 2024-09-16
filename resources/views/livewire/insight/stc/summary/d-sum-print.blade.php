<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use App\Models\InsStcDSum;
use App\InsStc;

new #[Layout('layouts.app')] 
class extends Component {

    public string $sequence;
    public string $user_1_name;
    public string $user_1_emp_id;
    public string $user_1_photo;
    public string $user_2_name;
    public string $user_2_emp_id;
    public string $device_name;
    public string $device_code;
    public string $machine_line;
    public string $machine_name;
    public string $machine_code;
    public string $start_time;
    public string $duration;
    public string $logs_count;
    public string $position;
    public string $speed;
    public array $set_temps;

    #[On('print-prepare')]
    public function load($data)
    {
        $this->sequence      = $data['sequence'];
        $this->user_1_name   = $data['user_1_name'];
        $this->user_1_emp_id = $data['user_1_emp_id'];
        $this->user_1_photo  = $data['user_1_photo'];
        $this->user_2_name   = $data['user_2_name'];
        $this->user_2_emp_id = $data['user_2_emp_id'];
        $this->device_name   = $data['device_name'];
        $this->device_code   = $data['device_code'];
        $this->machine_line  = $data['machine_line'];
        $this->machine_name  = $data['machine_name'];
        $this->machine_code  = $data['machine_code'];
        $this->start_time    = $data['start_time'];
        $this->duration      = $data['duration'];
        $this->logs_count    = $data['logs_count'];
        $this->position      = $data['position'];
        $this->speed         = $data['speed'];
        $this->set_temps     = $data['set_temps'];
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
                        <dt class="mb-3 text-neutral-500 text-xs uppercase">{{ __('Informasi pengukuran') }}</dt>
                        <dd>
                            <table>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Urutan') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $sequence }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Pengukur 1') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td class="truncate">
                                        {{ $user_1_emp_id . ' | '. $user_1_name }}                                        
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Pengukur 2') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td class="truncate">
                                        {{ $user_2_emp_id . ' | '. $user_2_name  }} 
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Kode alat ukur') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $device_code }}
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
                                        {{ $machine_line }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Mesin') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td class="truncate">
                                        {{ $machine_code . ' | ' . $machine_name }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Posisi') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $position }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Kecepatan') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $speed . ' RPM' }}
                                    </td>
                                </tr>
                            </table>
                        </dd>
                    </div>
                    <div class="flex flex-col">
                        <dt class="mb-3 text-neutral-500 text-xs uppercase">{{ __('Suhu diatur') }}</dt>
                        <dd>
                            <div class="grid grid-cols-8 text-center gap-x-3">
                                @foreach($set_temps as $set_temp)
                                <div>
                                    <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">
                                        {{ __('S') . $loop->iteration }}</div>
                                    <div>{{ $set_temp }}</div>
                                </div>
                                @endforeach
                            </div>
                            <table class="mt-3 text-sm text-neutral-500">
                                <tr>
                                    <td class="text-xs">
                                        {{ __('Waktu awal') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        {{ $start_time }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-xs">
                                        {{ __('Durasi') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td class="truncate">
                                        {{ $duration . ' ' . __('dari') . ' ' . $logs_count . ' ' . __('baris data') }}                                      
                                    </td>
                                </tr>
                            </table>
                        </dd>
                    </div>
                </div>
            </div>
        </div>
        <div class="grow border border-neutral-500 rounded-lg overflow-hidden">
            <div id="print-chart-container" class="h-full" wire:key="print-chart-container" wire:ignore></div>
        </div>
        <div class="grow-0">
            <div id="print-container-footer">
                <div class="flex justify-between p-4">
                    <div class="flex justify-between flex-col">
                        <div>{{ __('Zona 1') . ': 70-80 °C' }}</div>
                        <div>{{ __('Zona 2') . ': 60-70 °C' }}</div>
                        <div>{{ __('Zona 3') . ': 50-60 °C' }}</div>
                        <div>{{ __('Zona 4') . ': 40-50 °C' }}</div>
                    </div>
                    <div class="flex gap-x-3">
                        <div>
                            <div class="text-center font-bold">CE</div>
                            <div class="flex justify-center">
                                <div class="w-8 h-8 my-4 bg-neutral-200 rounded-full overflow-hidden">
                                    @if ($user_1_photo)
                                        <img class="w-full h-full object-cover"
                                            src="{{ '/storage/users/' . $user_1_photo }}" />
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
                                <div class="text-xs">{{ $user_1_name }}</div>
                            </div>
                        </div>
                        <div>
                            <div class="text-center font-bold">TL</div>
                            <div class="grow">
                                <div class="w-8 h-8 my-4"></div>
                            </div>
                            <hr class="border-neutral-300 w-48">
                            <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal') }}</div>
                        </div>
                        <div>
                            <div class="text-center font-bold">GL</div>
                            <div>
                                <div class="w-8 h-8 my-4"></div>
                            </div>
                            <hr class="border-neutral-300 w-48">
                            <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal') }}</div>
                        </div>
                        <div>
                            <div class="text-center font-bold">VSM</div>
                            <div>
                                <div class="w-8 h-8 my-4"></div>
                            </div>
                            <hr class="border-neutral-300 w-48">
                            <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
