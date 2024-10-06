<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\InsOmvMetric;
use App\InsOmv;

new #[Layout('layouts.app')] 
class extends Component {
    public int $id;
    public bool $showChart;

    public string $batch_code;
    public string $rdc_eval;
    public string $rdc_eval_human;
    public string $recipe_type;
    public string $recipe_name;
    public string $duration;
    public string $eval;
    public string $eval_human;
    public int $line;
    public string $team;
    public string $user_1_emp_id;
    public string $user_1_name;
    public string $user_2_emp_id;
    public string $user_2_name;
    public string $start_at;
    public string $end_at;

    #[On('metric-show')]
    public function showMetric(int $id)
    {
        $metric = InsOmvMetric::find($id);
        
        if ($metric) {

            $this->id           = $metric->id;
            $this->recipe_type  = $metric->ins_omv_recipe->type;
            $this->recipe_name  = $metric->ins_omv_recipe->name;
            $this->duration     = $metric->duration();
            $this->eval         = $metric->eval;
            $this->eval_human   = $metric->evalHuman();
            $this->line         = $metric->line;
            $this->team         = $metric->team;
            $this->user_1_emp_id = $metric->user_1->emp_id;
            $this->user_1_name  = $metric->user_1->name;
            $this->user_2_emp_id = $metric->user_2->emp_id ?? '-';
            $this->user_2_name  = $metric->user_2->name ?? '-';
            $this->start_at     = $metric->start_at;
            $this->end_at       = $metric->end_at;

            if ($metric->ins_rubber_batch) {
                $this->batch_code       = $metric->ins_rubber_batch->code ?: '-';
                $this->rdc_eval         = $metric->ins_rubber_batch->rdc_eval ?: '-';
                $this->rdc_eval_human   = $metric->ins_rubber_batch->rdcEvalHuman() ?: '-';
            } else {
                $this->batch_code       = '-';
                $this->rdc_eval         = '-';
                $this->rdc_eval_human   = '-';
            }

            $data = json_decode($metric->data, true) ?: [ 'amps' => [] ];
            
            if ($data['amps']) {
                // Koleksi durasi di setiap step resep dan di inkrementalkan
                $steps = json_decode($metric->ins_omv_recipe->steps, true) ?: [];
                $step_durations = [];
                $inc_durations = 0;

                foreach ($steps as $step) {
                    $inc_durations += $step['duration'];
                    $step_durations[] = $inc_durations;
                }

                // koleksi titik foto
                $capture_points = json_decode($metric->ins_omv_recipe->capture_points, true) ?: [];

                $this->js(
                    "
                    let modalOptions = " .
                        json_encode(InsOmv::getChartOptions($data['amps'], $metric->start_at, $step_durations, $capture_points, 100)) .
                        ";

                    // Render modal chart
                    const modalChartContainer = \$wire.\$el.querySelector('#modal-chart-container');
                    modalChartContainer.innerHTML = '<div id=\"modal-chart\"></div>';
                    let modalChart = new ApexCharts(modalChartContainer.querySelector('#modal-chart'), modalOptions);
                    modalChart.render();
                ",
                );
                $this->showChart = true;
            } else {
                $this->showChart = false;
            }

        } else {
            $this->handleNotFound();
        }
    }

    public function customReset()
    {
        $this->reset([
            'id', 
            'showChart', 
            'rdc_eval', 
            'rdc_eval_human', 
            'batch_code', 
            'recipe_type', 
            'recipe_name', 
            'duration', 
            'eval', 
            'eval_human', 
            'line', 
            'team', 
            'user_1_emp_id', 
            'user_1_name', 
            'user_2_emp_id', 
            'user_2_name', 
            'start_at', 
            'end_at'
        ]);
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
                {{ __('Rincian') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div wire:key="amps-exists" class="{{ $showChart ? '' : 'hidden' }} ">
            <div wire:key="modal-chart-container" wire:ignore class="h-80 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden my-8"
                id="modal-chart-container">
            </div>
        </div>
        <div wire:key="amps-none" class="{{ $showChart ? 'hidden' : '' }} py-20 rounded-lg border border-neutral-300 dark:border-neutral-600 my-6">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="fa fa-bolt-lightning relative"><i
                        class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
            </div>
            <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Tidak ada data arus listrik') }}
            </div>
        </div>
        <div class="flex flex-col mb-6 gap-6">
            <div class="flex flex-col grow">
                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi batch') }}</dt>
                <dd>
                    <div>
                        <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                            {{ __('Kode') . ': ' }}
                        </span>
                        <span>
                            {{ $batch_code ?? __('Tak ada kode') }}
                        </span>
                    </div>
                    <div>
                        <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                            {{ __('Hasil rheo') . ': ' }}
                        </span>
                        <x-pill class="uppercase"
                        color="{{ $rdc_eval === 'queue' ? 'yellow' : ($rdc_eval === 'pass' ? 'green' : ($rdc_eval === 'fail' ? 'red' : 'neutral')) }}">{{ $rdc_eval_human }}</x-pill> 
                    </div>
                </dd>
            </div>
            <div class="flex flex-col grow">
                <div class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi OMV') }}</div>
                <div class="flex flex-col md:flex-row gap-6">
                    <div class="grow">
                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Operator') . ': ' }}
                            </span>
                        </div>
                        <div class=>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm font-mono">1.</span>
                            <span class="font-mono">{{ ' ' . $user_1_emp_id }}</span>
                            <span>{{ ' - ' . $user_1_name }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm font-mono">2.</span>
                            <span class="font-mono">{{ ' ' . $user_2_emp_id }}</span>
                            <span>{{ ' - ' . $user_2_name }}</span>
                        </div>
                        <div class="mt-3">
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Tipe') . ': ' }}
                            </span>
                            <span class="uppercase">
                                {{ $recipe_type }}
                            </span>
                        </div>
                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Resep') . ': ' }}
                            </span>
                            <span class="uppercase">
                                {{ $recipe_name }}
                            </span>
                        </div>
                    </div>
                    <div class="grow">
                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Tim') . ': ' }}
                            </span>
                            <span>
                                {{ $team }}
                            </span>                            
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Line') . ': ' }}
                            </span>
                            <span>
                                {{ $line }}
                            </span>
                        </div>
                        <table class="table-auto">
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                                    {{ __('Awal') . ': ' }}
                                </td>
                                <td class="font-mono">
                                    {{ $start_at }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                                    {{ __('Akhir') . ': ' }}
                                </td>
                                <td class="font-mono">
                                    {{ $end_at }}
                                </td>
                            </tr>
                        </table>                                           
                        <div class="mt-3">
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Durasi') . ': ' }}
                            </span>
                            <span>
                                {{ $duration }}
                            </span>
                        </div>
                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Hasil') . ': ' }}
                            </span>
                            <x-pill class="uppercase"
                        color="{{ $eval === 'on_time' ? 'green' : ($eval === 'too_late' || $eval === 'too_soon' ? 'red' : 'neutral') }}">{{ $eval_human }}</x-pill>    
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
