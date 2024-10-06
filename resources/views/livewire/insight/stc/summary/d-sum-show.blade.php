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
    public string $end_time;
    public string $duration;
    public string $logs_count;
    public string $position;
    public string $speed;
    public array $set_temps;

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
            $this->start_time       = $dSum->start_time;
            $this->end_time         = $dSum->end_time;
            $this->duration         = InsStc::duration($dSum->start_time, $dSum->end_time);
            $this->logs_count       = $dSum->ins_stc_d_logs->count();
            $this->position         = InsStc::positionHuman($dSum->position);
            $this->speed            = $dSum->speed;
            $this->set_temps        = json_decode($dSum->set_temps, true);

            $logs = $dSum->ins_stc_d_logs->toArray();
            $xzones = $this->xzones;
            $yzones = $this->yzones;
            $ymax = $yzones ? max($yzones) + 5 : $ymax;
            $ymin = $yzones ? min($yzones) - 10 : $ymin;

            $this->js(
                "
                let modalOptions = " .
                    json_encode(InsStc::getChartOptions($logs, $xzones, $yzones, $ymax, $ymin, 100)) .
                    ";

                // Render modal chart
                const modalChartContainer = \$wire.\$el.querySelector('#modal-chart-container');
                modalChartContainer.innerHTML = '<div id=\"modal-chart\"></div>';
                let modalChart = new ApexCharts(modalChartContainer.querySelector('#modal-chart'), modalOptions);
                modalChart.render();

                let printOptions = " .
                    json_encode(InsStc::getChartOptions($logs, $xzones, $yzones, $ymax, $ymin, 85)) .
                    ";

                // // Render hidden printable chart
                const printChartContainer = document.querySelector('#print-chart-container');
                printChartContainer.innerHTML = '<div id=\"print-chart\"></div>';
                let printChart = new ApexCharts(printChartContainer.querySelector('#print-chart'), printOptions);
                printChart.render();
            ",
            );
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
            'start_time'    => $this->start_time,
            'duration'      => $this->duration,
            'logs_count'    => $this->logs_count,
            'position'      => $this->position,
            'speed'         => $this->speed,
            'set_temps'     => $this->set_temps
        ];
        $this->dispatch('print-prepare', $data);
    }

    #[On('print-execute')]
    public function printExecute()
    {
        $this->js('window.print()');
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
        <div class="h-80 bg-white dark:brightness-75 text-neutral-900 rounded overflow-hidden my-8"
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
                                    {{ $start_time }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                                    {{ __('Akhir') . ': ' }}
                                </td>
                                <td class="font-mono">
                                    {{ $end_time }}
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
                    </div>
                </div>
            </div>
            <div class="flex flex-col grow">
                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Suhu diatur') }}</dt>
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
                </dd>
            </div>
        </div>
        <div class="flex justify-end">
            <x-primary-button type="button" wire:click="printPrepare"><i
                    class="fa fa-print me-2"></i>{{ __('Cetak') }}</x-primary-button>
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
