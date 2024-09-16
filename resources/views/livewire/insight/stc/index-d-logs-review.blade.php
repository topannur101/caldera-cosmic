<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use App\InsStc;

new class extends Component {
    
    public array $logs = [['taken_at' => '', 'temp' => '']];

    #[On('d-logs-review')]
    public function dLogsLoad($logs, $xzones, $yzones)
    {
        $logs   = json_decode($logs, true);
        $xzones = json_decode($xzones, true);
        $yzones = json_decode($yzones, true);
        $ymax   = $yzones ? max($yzones) + 0 : $ymax;
        $ymin   = $yzones ? min($yzones) - 0 : $ymin;

        $this->logs = $logs;

        $this->js("
            let options = " . json_encode(InsStc::getChartOptions($logs, $xzones, $yzones, $ymax, $ymin)) . ";

            const parent = \$wire.\$el.querySelector('#chart-container');
            parent.innerHTML = '';

            const newChartMain = document.createElement('div');
            newChartMain.id = 'chart-main';
            parent.appendChild(newChartMain);

            let mainChart = new ApexCharts(parent.querySelector('#chart-main'), options);
            mainChart.render();
        ");
    }
};
?>

<div class="p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Tinjau data') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
    </div>
    <div class="h-80 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden my-8" id="chart-container" wire:key="chart-container" wire:ignore>
    </div>
    <div class="grid grid-cols-2 gap-x-3">
      <div>
         <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Pembagian zona') }}
        </h2>
        <div class="mt-3">
            <div>{{ __('Preheat') }}</div>   
            <div>{{ __('Zona 1') .': 70-80°C' }}</div>      
            <div>{{ __('Zona 2') .': 60-70°C' }}</div>     
            <div>{{ __('Zona 3') .': 50-60°C' }}</div>     
            <div>{{ __('Zona 4') .': 40-50°C' }}</div>      
            <div>{{ __('Postheat') }}</div>        
        </div>
      </div>
      <div class="max-h-48 overflow-y-auto relative">
          <table class="table table-xs text-sm overflow-hidden">
              <thead class="sticky top-0 z-10">
                  <tr>
                      <th>{{ __('No.') }}</th>
                      <th>{{ __('Diambil pada') }}</th>
                      <th>{{ __('Suhu') }}</th>
                  </tr>
              </thead>
              <tbody>
                  @foreach($logs as $index => $log)
                      <tr>
                          <td>{{ $index + 1 }}</td>
                          <td>{{ $log['taken_at'] }}</td>
                          <td>{{ $log['temp'] }}</td>
                      </tr>
                  @endforeach
              </tbody>
          </table>
      </div>
  </div>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>