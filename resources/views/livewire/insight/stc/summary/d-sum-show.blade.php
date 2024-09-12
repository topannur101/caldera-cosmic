<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use App\Models\InsStcDSum;
use App\InsStc;

new #[Layout('layouts.app')] 
class extends Component {
   public int $id;
   public $dSum;

   public array $xzones = [
        'preheat' => 5,
        'zone_1'  => 12,
        'zone_2'  => 12,
        'zone_3'  => 12,
        'zone_4'  => 12,
    ];

    public array $yzones = [40, 50, 60, 70, 80];

   #[On('d_sum-show')]
   public function showDSum(int $id)
   {
      $this->id = $id;
      $dSum = InsStcDSum::find($id);

      if ($dSum) {
        $this->id = $dSum->id;
        $logs   = $dSum->ins_stc_d_logs->toArray();
        $xzones = $this->xzones;
        $yzones = $this->yzones;
        $ymax   = $yzones ? max($yzones) + 0 : $ymax;
        $ymin   = $yzones ? min($yzones) - 0 : $ymin;
        $this->dSum = $dSum;

        $this->js("
                let options = " . json_encode(InsStc::getChartOptions($logs, $xzones, $yzones, $ymax, $ymin)) . ";

                // Render visible chart
                const mainChartContainer = \$wire.\$el.querySelector('#main-chart-container');
                mainChartContainer.innerHTML = '<div id=\"main-chart\"></div>';
                let visibleChart = new ApexCharts(mainChartContainer.querySelector('#main-chart'), options);
                visibleChart.render();

                // Render hidden printable chart
                const printChartContainer = document.querySelector('#print-chart-container');
                printChartContainer.innerHTML = '<div id=\"print-chart\" style=\"aspect-ratio: 20 / 9\"></div>';
                let printableChart = new ApexCharts(printChartContainer.querySelector('#print-chart'), options);
                printableChart.render();
            ");
        
      } else {
         $this->handleNotFound();
      }
   }

   public function customReset()
   {
      $this->reset(['id', 'dSum']);
   }

   public function handleNotFound()
   {
      $this->js('$dispatch("close")');
      $this->js('notyfError("' . __('Tidak ditemukan') . '")');
      $this->dispatch('updated');
   }

   public function print()
    {
        $this->dispatch('printSheet');
    }
};

?>
<div>
    <div class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Rincian pengukuran') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="h-80 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden my-8" id="main-chart-container"
            wire:key="main-chart-container" wire:ignore>
        </div>
        <div class="flex justify-end">
        <x-primary-button type="button" wire:click="$dispatch('printChart')"><i class="fa fa-print me-2"></i>{{ __('Cetak') }}</x-primary-button>
        </div>
    </div>
    <div class="cal-offscreen">
        @if($dSum)
        <div id="print-container-header-dynamic">
            <div class="flex gap-x-6 justify-between">            
                <div class="flex flex-col">
                    <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi pengukuran') }}</dt>
                    <dd>
                        <table>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Pengukuran ke') }}
                                </td>
                                <td class="px-1">:</td>
                                <td>
                                    {{ $dSum->sequence . ' | ' . $dSum->start_time }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Pengukur 1') }}
                                </td>
                                <td class="px-1">:</td>
                                <td>
                                    {{ $dSum->user_1->name . ' ('. $dSum->user_1->emp_id .')' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Pengukur 2') }}
                                </td>
                                <td class="px-1">:</td>
                                <td>
                                    {{ $dSum->user_2 ? ($dSum->user_2->name . ' ('.$dSum->user_2->emp_id .')') : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Kode alat ukur') }}
                                </td>
                                <td class="px-1">:</td>
                                <td>
                                    {{ $dSum->ins_stc_device->code }}
                                </td>
                            </tr>
                        </table>
                    </dd>
                </div>            
                <div class="flex flex-col">
                    <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi mesin') }}</dt>
                    <dd>
                        <table>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Line') }}
                                </td>
                                <td class="px-1">:</td>
                                <td>
                                    {{ $dSum->ins_stc_machine->line }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Mesin') }}
                                </td>
                                <td class="px-1">:</td>
                                <td>
                                    {{ $dSum->ins_stc_machine->name }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Posisi') }}
                                </td>
                                <td class="px-1">:</td>
                                <td>
                                    {{ InsStc::positionHuman($dSum->position) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                    {{ __('Kecepatan') }}
                                </td>
                                <td class="px-1">:</td>
                                <td>
                                    {{ $dSum->speed . ' RPM' }}
                                </td>
                            </tr>
                        </table>
                    </dd>
                </div> 
                <div class="flex flex-col">
                    <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Suhu diatur') }}</dt>
                    <dd>
                        <div class="grid grid-cols-8 text-center gap-x-6">
                            @foreach(json_decode($dSum->set_temps, true) as $set_temp)
                            <div>
                                <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Wilayah') . ' ' . $loop->iteration}}</div>
                                <div>{{ $set_temp }}</div>
                            </div>
                            @endforeach
                        </div>
                    </dd>
                </div>
            </div>
        </div>
        <div id="print-container-footer-dynamic" class="bg-white text-neutral-800">
            <div class="flex justify-between p-4">
                <div>
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
                                @if($dSum->user_1->photo)
                                <img class="w-full h-full object-cover" src="{{ '/storage/users/'.$dSum->user_1->photo }}" />
                                @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800  opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                @endif
                            </div>
                        </div>
                        <hr class="border-neutral-300 w-48">
                        <div class="text-center">
                            <div class="text-sm">{{ $dSum->user_1->name }}</div>
                            <div class="text-xs">{{ $dSum->user_1->emp_id }}</div>
                        </div>
                    </div>    
                    <div>
                        <div class="text-center font-bold">TL</div>
                        <div class="grow">
                            <div class="w-8 h-8 my-4"></div>
                        </div>
                        <hr class="border-neutral-300 w-48">
                        <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal')}}</div>
                    </div> 
                    <div>
                        <div class="text-center font-bold">GL</div>
                        <div><div class="w-8 h-8 my-4"></div></div>
                        <hr class="border-neutral-300 w-48">
                        <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal')}}</div>
                    </div> 
                    <div>
                        <div class="text-center font-bold">VSM</div>
                        <div><div class="w-8 h-8 my-4"></div></div>
                        <hr class="border-neutral-300 w-48">
                        <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal')}}</div>
                    </div>             
                </div>
            </div>
        </div>
        @endif
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
