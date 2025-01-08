<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsStcDSum;
use App\Models\InsStcAdj;
use App\Models\InsStcMLog;
use App\InsStc;

new #[Layout('layouts.app')] 
class extends Component {
    public int $id;
    public int $formula_id = 412;

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
    public string $started_at;
    public string $ended_at;
    public string $duration;
    public string $upload_latency;
    public string $logs_count;
    public string $position;
    public string $speed;

    public array $hb_temps = [0, 0, 0, 0, 0, 0, 0, 0];
    public array $sv_temps = [0, 0, 0, 0, 0, 0, 0, 0];
    public array $svpb_temps = [0, 0, 0, 0, 0, 0, 0, 0];
    public array $svp_temps = [0, 0, 0, 0, 0, 0, 0, 0];
    
    public bool $use_m_log_sv = false;

    public string $last_adj_at = '';
    public string $svp_status = '';

    #[On('d_sum-show')]
    public function showDSum(int $id)
    {
        $this->id = $id;
        $dSum = InsStcDSum::find($id);
        $adj = InsStcAdj::where('created_at', '<', $dSum->created_at)
        ->where('ins_stc_machine_id', $dSum->ins_stc_machine_id)
        ->where('position', $dSum->position)
        ->where('created_at', '>=', $dSum->created_at->subHours(6))
        ->orderBy('created_at', 'desc') // To get the most recent record in the 6-hour window
        ->first(); // Retrieve the first matching record

        if ($dSum) {
            $logs = $dSum->ins_stc_d_logs->toArray();
            $temps = array_map(fn($item) => $item['temp'], $logs);
            $hb_temps = InsStc::getMediansBySection($temps);

            $this->id = $dSum->id;
            $this->sequence         = $dSum->sequence;
            $this->user_1_name      = $dSum->user_1->name;
            $this->user_1_emp_id    = $dSum->user_1->emp_id;
            $this->user_1_photo     = $dSum->user_1->photo ?? '';
            $this->user_2_name      = $dSum->user_2->name ?? '-';
            $this->user_2_emp_id    = $dSum->user_2->emp_id ?? '-';
            $this->device_name      = $dSum->ins_stc_device->name;
            $this->device_code      = $dSum->ins_stc_device->code;
            $this->machine_line     = $dSum->ins_stc_machine->line;
            $this->machine_name     = $dSum->ins_stc_machine->name;
            $this->machine_code     = $dSum->ins_stc_machine->code;
            $this->started_at       = $dSum->started_at;
            $this->ended_at         = $dSum->ended_at;
            $this->duration         = InsStc::duration($dSum->started_at, $dSum->ended_at);
            $this->upload_latency   = InsStc::duration($dSum->ended_at, $dSum->updated_at);
            $this->logs_count       = $dSum->ins_stc_d_logs->count();
            $this->position         = $dSum->position;
            $this->speed            = $dSum->speed;
            $this->hb_temps         = [ $hb_temps['section_1'], $hb_temps['section_2'], $hb_temps['section_3'], $hb_temps['section_4'], $hb_temps['section_5'], $hb_temps['section_6'], $hb_temps['section_7'], $hb_temps['section_8'], ];

            if ($this->use_m_log_sv) {

                $this->sv_temps         = [0,0,0,0,0,0,0,0];

                $m_log = InsStcMLog::where('created_at', '>', $dSum->created_at)
                    ->where('ins_stc_machine_id', $dSum->ins_stc_machine_id)
                    ->where('position', $dSum->position)
                    ->where('created_at', '>=', $dSum->created_at->subHour())
                    ->orderBy('created_at', 'desc') 
                    ->first();

                if ($m_log) {
                    for ($i = 1; $i <= 8; $i++) {
                        $key = "sv_r_$i";
                        if (isset($m_log[$key])) {
                            $this->sv_temps[$i - 1] = $m_log[$key];
                        }
                    }
                }

            } else {
                $this->sv_temps = json_decode($dSum->sv_temps, true);
            }

            $this->last_adj_at = $adj ? $adj->created_at : '';
            
            $this->svpb_temps[0] = $adj ? $adj->sv_p_1 : 0;
            $this->svpb_temps[1] = $adj ? $adj->sv_p_2 : 0;
            $this->svpb_temps[2] = $adj ? $adj->sv_p_3 : 0;
            $this->svpb_temps[3] = $adj ? $adj->sv_p_4 : 0;
            $this->svpb_temps[4] = $adj ? $adj->sv_p_5 : 0;
            $this->svpb_temps[5] = $adj ? $adj->sv_p_6 : 0;
            $this->svpb_temps[6] = $adj ? $adj->sv_p_7 : 0;
            $this->svpb_temps[7] = $adj ? $adj->sv_p_8 : 0;

            $this->js("
            const options = " . json_encode(InsStc::getChartJsOptions($logs, $this->sv_temps)) . ";
        
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
        $data = [
            'sequence'      => $this->sequence,
            'user_1_name'   => $this->user_1_name,
            'user_1_emp_id' => $this->user_1_emp_id,
            'user_1_photo'  => $this->user_1_photo,
            'user_2_name'   => $this->user_2_name,
            'user_2_emp_id' => $this->user_2_emp_id,
            'device_name'   => $this->device_name,
            'device_code'   => $this->device_code,
            'machine_line'  => $this->machine_line,
            'machine_name'  => $this->machine_name,
            'machine_code'  => $this->machine_code,
            'started_at'    => $this->started_at,
            'duration'      => $this->duration,
            'upload_latency'=> $this->upload_latency,
            'logs_count'    => $this->logs_count,
            'position'      => $this->position,
            'speed'         => $this->speed,
            'sv_temps'     => $this->sv_temps
        ];
        $this->dispatch('print-prepare', $data);
    }

    #[On('print-execute')]
    public function printExecute()
    {
        $this->js("window.print()");
    }

    public function updated($property)
    {
        if ($property == 'use_m_log_sv') {
            if ($this->id) {
                $this->showDSum($this->id);
            }
        }
   }

   public function with(): array
   {
        if ($this->formula_id && $this->hb_temps && $this->sv_temps) {
            $svp_values = InsStc::calculateSVP($this->hb_temps, $this->sv_temps, $this->formula_id);
            foreach ($svp_values as $key => $value) {
                $this->svp_temps[$key] = $value['absolute'];
            }

        } else {
            $this->svp_temps = [0, 0, 0, 0, 0, 0, 0, 0];
        }

        if (array_sum($this->svpb_temps)) {
            if ($this->sv_temps == $this->svpb_temps) {
                $this->svp_status = 'match';
            } else {
                $this->svp_status = 'mismatch';
            }
        } else {
            $this->svp_status = '';
        }

        return [];
   }
};

?>
<div class="p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Rincian pengukuran') }}
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
                    @foreach($hb_temps as $hb_temp)
                        <td>{{ $hb_temp }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('SVPB') }}</td>
                    @foreach($svpb_temps as $svpb_temp)
                        <td>{{ $svpb_temp }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('SV') }}</td>
                    @foreach($sv_temps as $sv_temp)
                        <td>{{ $sv_temp }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('SVP') }}</td>
                    @foreach($svp_temps as $svp_temp)
                        <td>{{ $svp_temp }}</td>
                    @endforeach
                </tr>
            </table>
            <div class="flex justify-between items-center mt-6">
                <x-select id="adj-formula_id" wire:model.live="formula_id">
                    <option value="0"></option>
                    <option value="411">{{ __('v4.1.1 - Diff aggresive') }}</option>
                    <option value="412">{{ __('v4.1.2 - Diff delicate') }}</option>
                    <option value="421">{{ __('v4.2.1 - Ratio') }}</option>
                </x-select>
                <x-toggle name="use_m_log_sv" wire:model.live="use_m_log_sv"
                    :checked="$use_m_log_sv ? true : false">{{ __('Gunakan SV mesin') }}<x-text-button type="button"
                    class="ml-2 whitespace-nowrap" x-data="" x-on:click="$dispatch('open-modal', 'use_m_log_sv-help')"></x-text-button>
                </x-toggle>
            </div>
        </div>
        <div>
            <div class="grid grid-cols-1 gap-6">
                <div class="flex gap-6">
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Line') }}</div>
                        <div>
                            <span>
                                {{ $machine_line . ' ' . ($position == 'upper' ? '△' : ($position == 'lower' ? '▽' : '')) }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Urutan') }}</div>
                        <div>
                            <span class="uppercase">
                                {{ $sequence }}
                            </span>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Operator') }}</div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                            @if($user_1_photo ?? false)
                            <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$user_1_photo }}" />
                            @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                            @endif
                        </div>
                        <div class="text-sm px-2"><span class="text-neutral-500 dark:text-neutral-400">{{ ' ' . $user_1_emp_id . ' ' }}</span><span>{{ $user_1_name }}</span></div>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Alat ukur') }}</div>
                    <div>
                        <span class="uppercase">
                            {{ $device_code }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Awal') }}</div>
                    <div>
                        <span class="font-mono">
                            {{ $started_at }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Akhir') }}</div>
                    <div>
                        <span class="font-mono">
                            {{ $ended_at }}
                        </span>
                    </div>
                </div>                
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Durasi') }}</div>
                    <div>
                        <span>
                            {{ $duration }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Latensi') }}</div>
                    <div>
                        <span class="">
                            {{ $upload_latency }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Penyetelan terakhir') }}</div>
                    <div>
                        <span class="">
                            {{ $last_adj_at ?: __('Tak ada') }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Status SV') }}</div>
                    @switch($svp_status)

                        @case('match')
                            <div><i class="fa fa-check-circle me-2 text-green-500"></i>{{ __('Cocok dengan SVPB')}}</div>
                            @break
                        
                        @case('mismatch')
                            <div><i class="fa fa-exclamation-circle me-2 text-red-500"></i>{{ __('Tak cocok dengan SVPB')}}</div>
                        @break

                        @default
                            <div>{{ __('Tak ada penyetelan')}}</div>

                    @endswitch
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