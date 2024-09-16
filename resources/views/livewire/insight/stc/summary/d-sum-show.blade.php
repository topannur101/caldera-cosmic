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

   public array $xzones = [];
   public array $yzones = [];

   public function mount()
    {
        $this->xzones = InsStc::zones('x');
        $this->yzones = InsStc::zones('y');
    }

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

                // Render modal chart
                const modalChartContainer = \$wire.\$el.querySelector('#modal-chart-container');
                modalChartContainer.innerHTML = '<div id=\"modal-chart\"></div>';
                let modalChart = new ApexCharts(modalChartContainer.querySelector('#modal-chart'), options);
                modalChart.render();

                // Render hidden printable chart
                const printChartContainer = \$wire.\$el.querySelector('#print-chart-container');
                printChartContainer.innerHTML = '<div id=\"print-chart\"></div>';
                let printChart = new ApexCharts(printChartContainer.querySelector('#print-chart'), options);
                printChart.render();
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
        $data = [];
        $this->dispatch('printDSum', $data);
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
        <div class="h-80 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden my-8" id="modal-chart-container"
            wire:key="modal-chart-container" wire:ignore>
        </div>
        <div class="flex justify-end">
        <x-primary-button type="button" wire:click="$dispatch('printChart')"><i class="fa fa-print me-2"></i>{{ __('Cetak') }}</x-primary-button>
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
