<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsOmvMetric;
use App\InsOmv;

new class extends Component {
    public int $id;
    public bool $amps_exists;

    public string   $batch_code;
    public string   $rdc_eval;
    public string   $rdc_eval_human;
    public string   $recipe_type;
    public string   $recipe_name;
    public string   $duration;
    public int      $duration_seconds;
    public string   $eval;
    public string   $eval_human;
    public int      $line;
    public string   $team;
    public string   $user_1_emp_id;
    public string   $user_1_name;
    public string   $user_1_photo;
    public string   $user_2_emp_id;
    public string   $user_2_name;
    public string   $user_2_photo;
    public string   $start_at;
    public string   $end_at;

    public array    $captures = [];
    public int      $capture_n = 0;

    #[On('metric-show')]
    public function showMetric(int $id)
    {
        $this->customReset();
        $metric = InsOmvMetric::find($id);
        
        if ($metric) {

            $this->id           = $metric->id;
            $this->recipe_type  = $metric->ins_omv_recipe->type;
            $this->recipe_name  = $metric->ins_omv_recipe->name;
            $this->duration     = $metric->duration();
            $this->duration_seconds = $metric->durationSeconds();
            $this->eval         = $metric->eval;
            $this->eval_human   = $metric->evalHuman();
            $this->line         = $metric->line;
            $this->team         = $metric->team;
            $this->user_1_emp_id = $metric->user_1->emp_id;
            $this->user_1_name  = $metric->user_1->name;
            $this->user_1_photo  = $metric->user_1->photo ?? '';
            $this->user_2_emp_id = $metric->user_2->emp_id ?? '';
            $this->user_2_name  = $metric->user_2->name ?? '';
            $this->user_2_photo  = $metric->user_2->photo ?? '';
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
            $this->captures = $metric->ins_omv_captures->toArray() ?: [];
            $this->capture_n = 0;
            
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
                $this->amps_exists = true;
            } else {
                $this->amps_exists = false;
            }

        } else {
            $this->handleNotFound();
        }
    }

    public function capturesNavigate($action)
    {
        switch ($action) {
            case 'next':
                if ($this->capture_n < count($this->captures)) {
                    $this->capture_n++;
                }
                break;
            
            case 'prev':
                if ($this->capture_n > 1) {
                    $this->capture_n--;
                }
                break;
            
            case 'toggle':
                $this->capture_n = $this->capture_n ? 0 : 1;
                break;
        }
    }



    public function customReset()
    {
        $this->reset([
            'id', 
            'amps_exists', 
            'rdc_eval', 
            'rdc_eval_human', 
            'batch_code', 
            'recipe_type', 
            'recipe_name', 
            'duration', 
            'duration_seconds',
            'eval', 
            'eval_human', 
            'line', 
            'team', 
            'user_1_emp_id', 
            'user_1_name', 
            'user_1_photo', 
            'user_2_emp_id', 
            'user_2_name', 
            'user_2_photo',
            'start_at', 
            'end_at',
            'captures',
            'capture_n'
        ]);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
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
        {{-- Chart View --}}
        <div wire:key="capture-selected-none" class="{{ $capture_n ? 'hidden' : '' }}">
            <div wire:key="amps-exists" class="{{ $amps_exists ? '' : 'hidden' }} ">
                <div wire:key="modal-chart-container" wire:ignore class="h-96 mt-8 overflow-hidden"
                    id="modal-chart-container">
                </div>
            </div>
            <div wire:key="amps-none" class="{{ $amps_exists ? 'hidden' : '' }} py-6 rounded-lg my-6">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="fa fa-bolt-lightning relative"><i
                            class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                </div>
                <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Tidak ada data arus listrik') }}</div>
            </div>
        </div>

        {{-- Image View --}}
        <div wire:key="capture-selected-any" class="{{ $capture_n ? '' : 'hidden' }}">
            <div class="w-full h-96 mt-8 overflow-hidden rounded-lg flex items-center justify-center relative group">
                @if(count($captures) && $capture_n > 0)
                    <img
                        src="{{ asset('storage/omv-captures/' . $captures[$capture_n - 1]['file_name']) }}"
                        class="object-cover w-full h-full" 
                        alt="Capture {{ $capture_n }}"
                    />
                    
                    @if(count($captures) > 1)
                        {{-- Previous Button --}}
                        @if($capture_n > 1)
                            <button
                                wire:click="capturesNavigate('prev')"
                                class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 flex justify-center items-center bg-black/50 rounded-full text-white 
                                    opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/70">
                                <i class="fa fa-fw fa-chevron-left"></i>
                            </button>
                        @endif

                        {{-- Next Button --}}
                        @if($capture_n < count($captures))
                            <button
                                wire:click="capturesNavigate('next')"
                                class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 flex justify-center items-center bg-black/50 rounded-full text-white 
                                    opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/70">
                                <i class="fa fa-fw fa-chevron-right"></i>
                            </button>
                        @endif

                        {{-- Image Counter --}}
                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 bg-black/50 text-white px-3 py-1 rounded-full text-sm">
                            {{ $capture_n }} / {{ count($captures) }}
                        </div>
                    @endif
                @endif
            </div>            
        </div>

        {{-- Timeline Navigation --}}
        <div wire:key="captures-exists" class="{{ count($captures) ? '' : 'hidden' }} mt-8">
            <div class="flex items-center gap-3">
                <div>
                    <x-secondary-button type="button" wire:click="capturesNavigate('toggle')">
                        @if($capture_n)
                            <i class="fa fa-fw fa-line-chart"></i>
                        @else
                            <i class="fa fa-fw fa-images"></i>
                        @endif
                    </x-secondary-button>
                </div>                
                <div class="flex-grow">
                    <div class="relative w-full h-8">
                        <div class="absolute top-1/2 w-full h-px bg-neutral-300 dark:bg-neutral-700"></div>
                        @foreach($captures as $capture_i => $capture)
                            <button
                                wire:click="$set('capture_n', {{ $capture_i + 1 }})"
                                class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-2 h-2 rounded-full transition-colors
                                    {{ $capture_i == ($capture_n - 1) ? 'bg-caldy-500 z-10 opacity-100' : 'bg-neutral-300 hover:bg-neutral-400 dark:bg-neutral-600 dark:hover:bg-neutral-700 opacity-50' }}"
                                style="left: {{ ($capture['taken_at'] / $duration_seconds) * 100 }}%"
                            ></button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col mt-8 mb-6 gap-6">
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
                        <div class="flex items-center gap-x-1">
                            <div class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                @if($user_1_photo ?? false)
                                <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$user_1_photo }}" />
                                @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                @endif
                            </div>
                            <div class="font-mono">{{ ' ' . $user_1_emp_id }}</div>
                            <div>•</div>
                            <div>{{ $user_1_name }}</div>
                        </div>
                        @if($user_2_emp_id)
                            <div class="flex items-center gap-x-1">
                                <div class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                    @if($user_2_photo ?? false)
                                    <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$user_2_photo }}" />
                                    @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                    @endif
                                </div>
                                <div class="font-mono">{{ ' ' . $user_2_emp_id }}</div>
                                <div>•</div>
                                <div>{{ $user_2_name }}</div>
                            </div>
                        @endif
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
                        color="{{ $eval === 'on_time' ? 'green' : ($eval === 'on_time_manual' ? 'yellow' : ($eval === 'too_late' || $eval === 'too_soon' ? 'red' : 'neutral')) }}">{{ $eval_human }}</x-pill>    
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="capturesNavigate"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="capturesNavigate" class="hidden"></x-spinner>
</div>
