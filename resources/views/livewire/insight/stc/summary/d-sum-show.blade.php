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
        
      } else {
         $this->handleNotFound();
      }
   }

   public function customReset()
   {
      $this->reset(['id']);
   }

   public function handleNotFound()
   {
      $this->js('$dispatch("close")');
      $this->js('notyfError("' . __('Tidak ditemukan') . '")');
      $this->dispatch('updated');
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
        <div class="h-80 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden my-8" id="chart-container"
            wire:key="chart-container" wire:ignore>
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
