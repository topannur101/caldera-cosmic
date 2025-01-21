<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsStcDSum;
use App\InsStc;

new class extends Component {
    public int $id;

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
            'line'          => ''
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

    #[On('d_sum-show')]
    public function showDSum(int $id)
    {
        $this->id = $id;
        $dSum =InsStcDSum::with(['user', 'ins_stc_machine', 'ins_stc_d_logs', 'ins_stc_device'])->find($id);

        if ($dSum) {
            $this->d_sum = $dSum->toArray();

            // Decode JSON for specified keys
            foreach (['sv_values', 'target_values', 'hb_values', 'svp_values'] as $key) {
                $this->d_sum[$key] = json_decode($this->d_sum[$key], true);
            }

            // calculated
            foreach (['duration', 'latency', 'adjustment_friendly', 'integrity_friendly'] as $key) {
                $this->d_sum[$key] = $dSum->$key();
            }

            $this->js("
            const options = " . json_encode(InsStc::getChartJsOptions($this->d_sum['ins_stc_d_logs'], $this->d_sum['sv_values'])) . ";
        
            // Add tooltip configuration
            options.options.plugins.tooltip = {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y + '°C';
                    },
                    title: function(context) {
                        if (!context[0]) return '';
                        const date = new Date(context[0].parsed.x);
                        return date.toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                }
            };
        
            // Render modal chart
            const modalChartContainer = \$wire.\$el.querySelector('#modal-chart-container');
            modalChartContainer.innerHTML = '';
            const modalCanvas = document.createElement('canvas');
            modalCanvas.id = 'modal-chart';
            modalChartContainer.appendChild(modalCanvas);
            new Chart(modalCanvas, options);
        
            // Render print chart
            const printChartContainer = document.querySelector('#print-chart-container');
            printChartContainer.innerHTML = '';
            const printCanvas = document.createElement('canvas');
            printCanvas.id = 'print-chart';
            printChartContainer.appendChild(printCanvas);
            new Chart(printCanvas, options);
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

    public function printPrepare()
    {
        $this->dispatch('print-prepare', $this->d_sum);
    }

    #[On('print-execute')]
    public function printExecute()
    {
        $this->js("window.print()");
    }

};

?>
<div class="p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Rincian') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="col-span-2">
            <div class="h-80 overflow-hidden mt-6"
                id="modal-chart-container" wire:key="modal-chart-container" wire:ignore>
            </div>
            <table class="table table-xs text-sm text-center mt-6">
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
        </div>
        <div>
            <div class="grid grid-cols-1 gap-6">
                <div class="flex gap-6">
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Line') }}</div>
                        <div>
                            <span>
                                {{ $d_sum['ins_stc_machine']['line'] . ' ' . ($d_sum['position'] == 'upper' ? '△' : ($d_sum['position'] == 'lower' ? '▽' : '')) }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Urutan') }}</div>
                        <div>
                            <span class="uppercase">
                                {{ $d_sum['sequence'] }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('RPM') }}</div>
                        <div>
                            <span class="uppercase">
                                {{ $d_sum['speed'] }}
                            </span>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Penyetelan') }}</div>
                    <div>
                        
                        {!! $d_sum['adjustment_friendly'] !!}
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Integritas') }}</div>
                    <div>
                        {!! $d_sum['integrity_friendly'] !!}
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Operator') }}</div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                            @if($d_sum['user']['photo'] ?? false)
                            <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$d_sum['user']['photo'] }}" />
                            @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                            @endif
                        </div>
                        <div class="text-sm px-2"><span class="text-neutral-500 dark:text-neutral-400">{{ ' ' . $d_sum['user']['emp_id'] . ' ' }}</span><span>{{ $d_sum['user']['name'] }}</span></div>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Alat ukur') }}</div>
                    <div>
                        <span class="uppercase">
                            {{ $d_sum['ins_stc_device']['code'] . ' | ' .$d_sum['ins_stc_device']['name'] }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Awal') }}</div>
                    <div>
                        <span class="font-mono">
                            {{ $d_sum['started_at'] }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Akhir') }}</div>
                    <div>
                        <span class="font-mono">
                            {{ $d_sum['ended_at'] }}
                        </span>
                    </div>
                </div>  
                <div class="flex gap-6">
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Durasi') }}</div>
                        <div>
                            <span>
                                {{ $d_sum['duration'] }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Latensi') }}</div>
                        <div>
                            <span class="">
                                {{ $d_sum['latency'] }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>         
        </div>
    </div>
    <div class="flex justify-end items-end mt-6">
        <x-primary-button type="button" wire:click="printPrepare"><i class="fa fa-print me-2"></i>{{ __('Cetak') }}</x-primary-button>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>