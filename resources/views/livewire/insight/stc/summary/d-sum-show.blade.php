<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use App\Models\InsStcDSum;
use App\Models\InsStcMLog;
use App\InsStc;

new #[Layout('layouts.app')] 
class extends Component {
    public int $id;

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

    public bool $use_m_log_sv = false;

    #[On('d_sum-show')]
    public function showDSum(int $id)
    {
        $this->id = $id;
        $dSum = InsStcDSum::find($id);

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
            $this->position         = InsStc::positionHuman($dSum->position);
            $this->speed            = $dSum->speed;
            $this->hb_temps         = [ $hb_temps['section_1'], $hb_temps['section_2'], $hb_temps['section_3'], $hb_temps['section_4'], $hb_temps['section_5'], $hb_temps['section_6'], $hb_temps['section_7'], $hb_temps['section_8'], ];
            
            if ($this->use_m_log_sv) {
                $this->sv_temps         = [0,0,0,0,0,0,0,0];

                $m_log = InsStcMLog::query()
                    ->select('*')
                    ->selectRaw('ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) as time_difference', [$dSum->created_at])
                    ->havingRaw('time_difference <= ?', [3600]) // 3600 seconds = 1 hour
                    ->orderBy('time_difference')
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
                $this->sv_temps         = json_decode($dSum->sv_temps, true);
            }

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
        <div class="h-80 overflow-hidden my-8"
            id="modal-chart-container" wire:key="modal-chart-container" wire:ignore>
        </div>
        <div class="flex flex-col mb-6 gap-6">
            <div class="flex flex-col grow">
                <div class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi pengukuran') }}</div>
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
                                {{ __('Urutan') . ': ' }}
                            </span>
                            <span class="uppercase">
                                {{ $sequence }}
                            </span>
                        </div>
                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Kode alat ukur') . ': ' }}
                            </span>
                            <span class="uppercase">
                                {{ $device_code }}
                            </span>
                        </div>
                    </div>
                    <div class="grow">
                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Line') . ': ' }}
                            </span>
                            <span>
                                {{ $machine_line }}
                            </span>                            
                            <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                                {{ __('Posisi') . ': ' }}
                            </span>
                            <span>
                                {{ $position }}
                            </span>
                        </div>
                        <table class="table-auto">
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                                    {{ __('Awal') . ': ' }}
                                </td>
                                <td class="font-mono">
                                    {{ $started_at }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                                    {{ __('Akhir') . ': ' }}
                                </td>
                                <td class="font-mono">
                                    {{ $ended_at }}
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
                                {{ __('Latensi unggah') . ': ' }}
                            </span>
                            <span>
                                {{ $upload_latency }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col grow">
                <dd>
                    <div class="grid grid-cols-9 text-center gap-x-3">
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">S</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">1</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">2</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">3</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">4</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">5</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">6</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">7</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">8</div>

                        <div>HB</div>
                        @foreach($hb_temps as $hb_temp)
                            <div>{{ $hb_temp }}</div>
                        @endforeach

                        <div>SV</div>
                        @foreach($sv_temps as $sv_temp)
                            <div>{{ $sv_temp }}</div>
                        @endforeach
                    </div>
                </dd>
            </div>
        </div>
        <div class="flex justify-between items-end">
            <div>
                <x-toggle name="use_m_log_sv" wire:model.live="use_m_log_sv"
                    :checked="$use_m_log_sv ? true : false">{{ __('Gunakan SV mesin') }}<x-text-button type="button"
                    class="ml-2" x-data="" x-on:click="$dispatch('open-modal', 'use_m_log_sv-help')">
                    <i class="far fa-question-circle"></i></x-text-button>
                </x-toggle>
            </div>
            <x-primary-button type="button" wire:click="printPrepare"><i class="fa fa-print me-2"></i>{{ __('Cetak') }}</x-primary-button>
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>