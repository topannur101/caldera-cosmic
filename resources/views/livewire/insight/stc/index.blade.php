<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Validator;
use App\Models\InsStcMachine;
use App\Models\InsStcDevice;
use App\Models\InsStcDLog;
use App\Models\InsStcDSum;
use App\Models\User;
use Carbon\Carbon;
use App\InsStc;

new #[Layout('layouts.app')] 
class extends Component {

    use WithFileUploads;

    public $file;
    public array $logs = [['taken_at' => '', 'temp' => '']];

    public int $sequence;
    public string $userq = '';
    public int $user_2_id;
    public string $user_2_name;
    public string $user_2_emp_id;
    public int $machine_id;
    public string $position;
    public float $speed;
    public string $set_temps_raw;
    public array $set_temps = [];
    public string $device_code = '';

    public string $start_time;
    public string $end_time;
    public float $preheat_temp = 0;
    public float $z_1_temp = 0;
    public float $z_2_temp = 0;
    public float $z_3_temp = 0;
    public float $z_4_temp = 0;

    public string $view = 'initial';
    public string $logs_count_eval;
    public string $logs_count_eval_human;
    public string $duration;

    public array $xzones = [
        'preheat' => 5,
        'zone_1'  => 12,
        'zone_2'  => 12,
        'zone_3'  => 12,
        'zone_4'  => 12,
    ];

    public array $yzones = [40, 50, 60, 70, 80];

    public const COUNT_TOLERANCE = 5;

    public function with(): array
    {
        return [
            'machines' => InsStcMachine::orderBy('line')->get()
        ];
    }

    public function submitInitial()
    {
        $this->device_code = strtoupper(trim($this->device_code));
        
        $this->userq = trim($this->userq);
        if ($this->userq) {
            $user_2 = User::where('emp_id', $this->userq)->first();
            if ($user_2) {
                $this->user_2_id        = $user_2->id;
                $this->user_2_name      = $user_2->name;
                $this->user_2_emp_id    = $user_2->emp_id;
            }

        } else {
            $this->reset(['user_2_id']);
        }

        $this->validate([
            'sequence'          => ['required', 'integer', 'min:1', 'max:2'],
            'user_2_id'         => ['nullable', 'exists:users,id'],
            'machine_id'        => ['required', 'integer', 'exists:ins_stc_machines,id'],
            'position'          => ['required', 'in:upper,lower'],
            'speed'             => ['required', 'numeric', 'min:0.1', 'max:99'],
            'set_temps'         => ['required', 'array', 'size:8'],
            'set_temps.*'       => ['required', 'numeric', 'min:1', 'max:99'],
            'device_code'       => ['required', 'exists:ins_stc_devices,code'],
        ]);
        $this->view = 'upload';
    }

    public function save()
    {
        // make sure gone through validation
        if($this->view == 'review') {
            $d_sum = new InsStcDsum;
            Gate::authorize('manage', $d_sum);

            $device = InsStcDevice::where('code', $this->device_code)->first();
            $d_sum->fill([
                'ins_stc_device_id'     => $device->id,
                'ins_stc_machine_id'    => $this->machine_id,
                'user_1_id'             => Auth::user()->id,
                'user_2_id'             => $this->user_2_id,
                'start_time'            => $this->start_time,
                'end_time'              => $this->end_time,
                'preheat_temp'          => $this->preheat_temp,
                'z_1_temp'              => $this->z_1_temp,
                'z_2_temp'              => $this->z_2_temp,
                'z_3_temp'              => $this->z_3_temp,
                'z_4_temp'              => $this->z_4_temp,
                'speed'                 => $this->speed,
                'sequence'              => $this->sequence,
                'position'              => $this->position,
                'set_temps'             => json_encode($this->set_temps),
            ]);
            $d_sum->save();

            foreach ($this->logs as $log) {
                InsStcDLog::create([
                    'ins_stc_d_sum_id' => $d_sum->id,
                    'taken_at' => $log['taken_at'],
                    'temp' => $log['temp']
                ]);
            }

            $this->js('notyfSuccess("'. __('Disimpan') .'")'); 
            $this->customReset();
        }

    }

    public function updatedFile()
    {
        $this->validate([
            'file' => 'file|mimes:csv|max:1024'
        ]);
        $this->extractData();
    }

    #[Renderless]
    public function updatedUserq()
    {
        $this->dispatch('userq-updated', $this->userq);
    }

    private function extractData()
    {
        $rows = array_map('str_getcsv', file($this->file->getPathname()));
        $skipRows = 3;
        $tempColumn = 3;

        for ($i = 0; $i < $skipRows; $i++) {
            array_shift($rows);
        }

        $logs = [];

        foreach ($rows as $row) {
            if (isset($row[0]) && isset($row[$tempColumn]) && 
                $row[0] !== '' && $row[$tempColumn] !== '') {
                $timestamp = strtotime($row[0]);
                if ($timestamp !== false) {  // Ensure valid date/time
                    $logs[] = [
                        'taken_at' => $row[0],
                        'temp' => $row[$tempColumn],
                        'timestamp' => $timestamp // Adding timestamp for sorting
                    ];
                }
            }
        }

        usort($logs, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        $logsCount = min(count($logs), array_sum($this->xzones));

        // Evaluate logs count
        if ($logsCount < array_sum($this->xzones) - self::COUNT_TOLERANCE) {
            $this->logs_count_eval = 'too_few';
        } elseif ($logsCount > array_sum($this->xzones) + self::COUNT_TOLERANCE) {
            $this->logs_count_eval = 'too_many';
        } else {
            $this->logs_count_eval = 'optimal';
        }
        $this->logs_count_eval_human = InsStcDSum::logsCountEvalHuman($this->logs_count_eval);

        $logs = array_slice($logs, 0, $logsCount);

        if (empty($logs)) {
            $this->js('notyfError("'. __('Tak ada data yang sah ditemukan') .'")');

        } else {
            $slicedPh = InsStc::sliceZoneData($logs, $this->xzones, 'preheat');
            $slicedZ1 = InsStc::sliceZoneData($logs, $this->xzones, 'zone_1');
            $slicedZ2 = InsStc::sliceZoneData($logs, $this->xzones, 'zone_2');
            $slicedZ3 = InsStc::sliceZoneData($logs, $this->xzones, 'zone_3');
            $slicedZ4 = InsStc::sliceZoneData($logs, $this->xzones, 'zone_4');

            $validator = Validator::make(
                [
                    'start_time'    => $logs[0]['taken_at'],
                    'end_time'      => $logs[array_key_last($logs)]['taken_at'],
                    'p_h'           => $slicedPh,
                    'z_1'           => $slicedZ1,
                    'z_2'           => $slicedZ2,
                    'z_3'           => $slicedZ3,
                    'z_4'           => $slicedZ4,
                ],
                [
                    'start_time'        => 'required|date',
                    'end_time'          => 'required|date|after:start_time',
                    'p_h.*.taken_at'    => 'required|date',
                    'z_1.*.taken_at'    => 'required|date',
                    'z_2.*.taken_at'    => 'required|date',
                    'z_3.*.taken_at'    => 'required|date',
                    'z_4.*.taken_at'    => 'required|date',
                    'p_h.*.temp'        => 'required|numeric|min:1|max:99',
                    'z_1.*.temp'        => 'required|numeric|min:1|max:99',
                    'z_2.*.temp'        => 'required|numeric|min:1|max:99',
                    'z_3.*.temp'        => 'required|numeric|min:1|max:99',
                    'z_4.*.temp'        => 'required|numeric|min:1|max:99',
                ]
            );

            if ($validator->fails()) {
                $error = $validator->errors()->first();
                $this->js('notyfError("'.$error.'")');
                $this->reset(['file']);

            } else {
                $this->logs         = $logs;
                $validatedData      = $validator->validated();
                $this->start_time   = $validatedData['start_time'];
                $this->end_time     = $validatedData['end_time'];
                $this->preheat_temp = InsStc::medianTemp($validatedData['p_h']);
                $this->z_1_temp     = InsStc::medianTemp($validatedData['z_1']);
                $this->z_2_temp     = InsStc::medianTemp($validatedData['z_2']);
                $this->z_3_temp     = InsStc::medianTemp($validatedData['z_3']);
                $this->z_4_temp     = InsStc::medianTemp($validatedData['z_4']);
                $this->duration     = InsStc::duration($validatedData['start_time'], $validatedData['end_time']);

                $this->view = 'review';
            }
        }
    }

    public function customReset()
    {
        $this->reset([
            'file', 
            'logs', 

            'sequence',
            'userq',
            'user_2_id',
            'machine_id', 
            'position',
            'speed', 
            'set_temps_raw',
            'set_temps',
            'device_code', 
            
            'start_time', 
            'end_time', 
            'preheat_temp', 
            'z_1_temp', 
            'z_2_temp', 
            'z_3_temp', 
            'z_4_temp', 

            'view', 
            'logs_count_eval', 
            'logs_count_eval_human', 
            'duration',
        ]);
    }

    public function downloadCSV()
    {
        $filePath = public_path('ins-stc-sample.csv');

        if (!file_exists($filePath)) {
            $this->js('alert("' . __('File CSV tidak ditemukan') . '")');
            return;
        }

        return response()->streamDownload(function () use ($filePath) {
            echo file_get_contents($filePath);
        }, 'ins-stc-sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

};

?>

<x-slot name="title">{{ __('IP Stabilization Control') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-stc></x-nav-insights-stc>
</x-slot>

<div id="content" class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @if (!Auth::user())
        <div class="flex flex-col items-center gap-y-6 px-6 py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl">
                <i class="fa fa-exclamation-circle"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">
                {{ __('Masuk terlebih dahulu untuk melakukan pembukuan hasil ukur') }}
            </div>
            <div>
                <a href="{{ route('login', ['redirect' => url()->current()]) }}" wire:navigate
                    class="flex items-center px-6 py-3 mb-3 text-white bg-caldy-600 rounded-md sm:mb-0 hover:bg-caldy-700 sm:w-auto">
                    {{ __('Masuk') }}
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-1" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
            </div>
        </div>
    @else
    <h1 class="grow text-2xl text-neutral-900 dark:text-neutral-100 px-8">{{ __('Pembukuan') }}</h1>
    @vite(['resources/js/apexcharts.js'])
    <div class="w-full my-8">
        <x-modal name="d-logs-review" maxWidth="2xl">
            <livewire:insight.stc.index-d-logs-review />
        </x-modal>
        <div x-data="{ dropping: false }" class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            @switch($view)
                @case('initial')
                    <div>
                        <div class="mb-6">
                            <div class="grid grid-cols-2 gap-x-3">
                                <div>
                                    <label for="d-log-sequence"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengukuran ke') }}</label>
                                    <x-select class="w-full" id="d-log-sequence" wire:model="sequence">
                                        <option value=""></option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                    </x-select>
                                </div>
                                <div x-data="{ open: false, userq: @entangle('userq').live }"
                                    x-on:user-selected="userq = $event.detail; open = false">
                                    <div x-on:click.away="open = false">
                                        <label for="stc-user"
                                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mitra kerja') }}</label>
                                        <x-text-input-icon x-model="userq" icon="fa fa-fw fa-user" x-on:change="open = true"
                                            x-ref="userq" x-on:focus="open = true" id="stc-user" type="text"
                                            autocomplete="off" placeholder="{{ __('Pengguna') }}" />
                                        <div class="relative" x-show="open" x-cloak>
                                            <div class="absolute top-1 left-0 w-full z-10">
                                                <livewire:layout.user-select />
                                            </div>
                                        </div>
                                    </div>
                                    <div wire:key="error-user_2_id">
        
                                    </div>
                                </div>                            
                            </div>
                            @error('sequence')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                                @error('user_2_id')
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div> 
                        <div class="mb-6">
                            <label for="d-log-machine_id"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mesin') }}</label>
                            <x-select class="w-full" id="d-log-machine_id" wire:model="machine_id">
                                <option value=""></option>
                                @foreach ($machines as $machine)
                                    <option value="{{ $machine->id }}">{{ 'Line ' . $machine->line . ' | ' . $machine->name }}</option>
                                @endforeach
                            </x-select>
                            @error('machine_id')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        <div class="mb-6">
                            <div class="grid grid-cols-2 gap-x-3">
                                <div>
                                    <label for="d-log-position"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                                    <x-select class="w-full" id="d-log-position" wire:model="position">
                                        <option value=""></option>
                                            <option value="upper">{{ __('Atas') }}</option>
                                            <option value="lower">{{ __('Bawah') }}</option>
                                    </x-select>
                                </div>
                                <div>
                                    <label for="d-log-speed"
                                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kecepatan') }}</label>
                                    <x-text-input-suffix suffix="RPM" id="d-log-speed" wire:model="speed" type="number" step=".1" autocomplete="off" />
                                </div>                               
                            </div>
                            @error('position')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                            @error('speed')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2 mb-6" />
                            @enderror
                        </div>
                        <div x-data="{
                                set_temps: @entangle('set_temps'),
                                set_temps_raw: @entangle('set_temps_raw'),
                                set_temps_count: 0,
                                updateSetTemps() {
                                    this.set_temps = this.set_temps_raw.split(',').map(temp => temp.trim()).filter(temp => temp !== '');
                                    this.set_temps_count = this.set_temps.length;
                                }
                            }" class="mb-6">
                                <div class="flex justify-between px-3 mb-2 uppercase text-xs text-neutral-500">
                                    <label for="d-log-set_temps">{{ __('Suhu diatur') }}</label>
                                    <div><span x-text="set_temps_count"></span>{{ ' ' . __('entri terbaca') }}</div>
                                </div>
                                <x-text-input 
                                    id="d-log-set_temps" 
                                    x-model="set_temps_raw" 
                                    @input="updateSetTemps"
                                    type="text" placeholder="75, 65, 55,..."
                                />
                                @error('set_temps')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                                @error('set_temps.*')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                        <div class="mb-6">
                            <label for="d-log-device_code"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode alat') }}</label>
                            <x-text-input id="d-log-device_code" wire:model="device_code" type="text" placeholder="Scan atau ketik di sini..." />
                            @error('device_code')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>   

                    </div>                                   
                    @break

                    @case('upload')
                        <div class="relative"  x-on:dragover.prevent="dropping = true">
                            <div wire:loading.class="hidden"
                                class="absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white/80 dark:bg-neutral-800/80"
                                x-cloak x-show="dropping">
                                <div
                                    class="flex justify-around items-center w-full h-full border-dashed border-2 border-neutral-500  text-neutral-500 dark:text-neutral-400 rounded-lg">
                                    <div class="text-center">
                                        <div class="text-4xl mb-3">
                                            <i class="fa fa-upload"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input wire:model="file" type="file"
                                    class="absolute inset-0 m-0 p-0 w-full h-full outline-none opacity-0" x-cloak x-ref="file"
                                    x-show="dropping" x-on:dragleave.prevent="dropping = false" x-on:drop="dropping = false" />
                            <dl class="text-neutral-900 divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700 mb-6">
                                <div class="flex flex-col pb-6">
                                    <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi pengukuran') }}</dt>
                                    <dd>
                                        <table class="table table-xs table-col-heading-fit">
                                            <tr>
                                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                    {{ __('Pengukuran ke') . ': ' }}
                                                </td>
                                                <td>
                                                    {{ $sequence }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                    {{ __('Pengukur 1') . ': ' }}
                                                </td>
                                                <td>
                                                    {{ Auth::user()->name . ' ('. Auth::user()->emp_id .')' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                    {{ __('Pengukur 2') . ': ' }}
                                                </td>
                                                <td>
                                                    {{ $user_2_name . ' ('.$user_2_emp_id .')' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                    {{ __('Kode alat ukur') . ': ' }}
                                                </td>
                                                <td>
                                                    {{ $device_code }}
                                                </td>
                                            </tr>
                                        </table>
                                    </dd>
                                </div>
                                <div class="flex flex-col py-6">
                                    <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi mesin') }}</dt>
                                    <dd>
                                        <table class="table table-xs table-col-heading-fit">
                                            <tr>
                                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                    {{ __('Line') . ': ' }}
                                                </td>
                                                <td>
                                                    {{ $machines->firstWhere('id', $this->machine_id)->line }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                    {{ __('Mesin') . ': ' }}
                                                </td>
                                                <td>
                                                    {{ $machines->firstWhere('id', $this->machine_id)->code . ' ('. $machines->firstWhere('id', $this->machine_id)->name .')' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                    {{ __('Posisi') . ': ' }}
                                                </td>
                                                <td>
                                                    {{ InsStc::positionHuman($position) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                    {{ __('Kecepatan') . ': ' }}
                                                </td>
                                                <td>
                                                    {{ $speed . ' RPM' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                    {{ __('Suhu diatur') . ': ' }}
                                                </td>
                                                <td class="flex gap-x-3">
                                                    @foreach($set_temps as $set_temp)
                                                        <div>
                                                            {{ $set_temp }}
                                                        </div>
                                                        @if(!$loop->last)
                                                            <div class="text-neutral-300 dark:text-neutral-600">|</div>
                                                        @endif
                                                    @endforeach
                                                </td>
                                            </tr>
                                        </table>
                                    </dd>
                                    @error('file')
                                        <x-input-error messages="{{ $message }}" class="px-1 mt-2" />
                                    @enderror
                                </div>    
                            </dl>
                        </div>
                        @break

                        @case('review')
                        <dl class="text-neutral-900 divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
                            <div class="flex flex-col pb-6">
                                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi pengukuran') }}</dt>
                                <dd>
                                    <table class="table table-xs table-col-heading-fit">
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Pengukuran ke') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $sequence }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Pengukur 1') . ': ' }}
                                            </td>
                                            <td>
                                                {{ Auth::user()->name . ' ('. Auth::user()->emp_id .')' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Pengukur 2') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $user_2_name . ' ('.$user_2_emp_id .')' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Kode alat ukur') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $device_code }}
                                            </td>
                                        </tr>
                                    </table>
                                </dd>
                            </div>
                            <div class="flex flex-col py-6">
                                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi mesin') }}</dt>
                                <dd>
                                    <table class="table table-xs table-col-heading-fit">
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Line') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $machines->firstWhere('id', $this->machine_id)->line }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Mesin') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $machines->firstWhere('id', $this->machine_id)->code . ' ('. $machines->firstWhere('id', $this->machine_id)->name .')' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Posisi') . ': ' }}
                                            </td>
                                            <td>
                                                {{ InsStc::positionHuman($position) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Kecepatan') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $speed . ' RPM' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Suhu diatur') . ': ' }}
                                            </td>
                                            <td class="flex gap-x-3">
                                                @foreach($set_temps as $set_temp)
                                                    <div>
                                                        {{ $set_temp }}
                                                    </div>
                                                    @if(!$loop->last)
                                                        <div class="text-neutral-300 dark:text-neutral-600">|</div>
                                                    @endif
                                                @endforeach
                                            </td>
                                        </tr>
                                    </table>
                                </dd>
                            </div>
                            <div class="flex flex-col py-6">
                                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi hasil ukur') }}</dt>
                                <dd>
                                <table class="table table-xs table-col-heading-fit">
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Waktu awal') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $start_time }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Waktu akhir') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $end_time }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Durasi') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $duration }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Jumlah data') . ': ' }}
                                        </td>
                                        <td>
                                            {{ count($logs) . ' ('. $logs_count_eval_human .')' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Suhu median') . ': ' }}
                                        </td>
                                        <td>
                                        </td>
                                    </tr>
                                </table>
                                <div class="grid grid-cols-4 mt-3 text-center">
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Preheat')}}</div>
                                        <div>{{ $preheat_temp }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona 1')}}</div>
                                        <div>{{ $z_1_temp }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona 2')}}</div>
                                        <div>{{ $z_2_temp }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona 3')}}</div>
                                        <div>{{ $z_3_temp }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400 dark:text-neutral-500">{{ __('Zona 4')}}</div>
                                        <div>{{ $z_4_temp }}</div>
                                    </div>
                                </div>

                                </dd>
                            </div>
                        </dl>
                        @break                    
            @endswitch
            <div class="flex justify-between items-center">
                <x-dropdown align="left" width="48">
                    <x-slot name="trigger">
                        <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="#" wire:click.prevent="downloadCSV">
                            {{ __('Unduh CSV contoh') }}
                        </x-dropdown-link>
                        @if($view != 'initial')
                        <hr class="border-neutral-300 dark:border-neutral-600 {{ true ? '' : 'hidden' }}" />
                        <x-dropdown-link href="#" wire:click.prevent="customReset"
                            class="{{ true ? '' : 'hidden' }}">
                            {{ __('Ulangi dari awal') }}
                        </x-dropdown-link>
                        @endif
                    </x-slot>
                </x-dropdown>
                <div class="flex gap-x-2">
                    @if($view == 'initial')
                    <x-primary-button type="button" wire:click="submitInitial">{{ __('Lanjut') }}</x-primary-button>
                    @endif
                    @if($view == 'upload')
                    <x-secondary-button type="button" wire:click="$set('view', 'initial')">{{ __('Mundur') }}</x-secondary-button>
                    <x-primary-button type="button" x-on:click="$refs.file.click()"><i
                        class="fa fa-upload mr-2"></i>{{ __('Unggah') }}</x-primary-button>
                    @endif
                    @if($view == 'review')
                    <x-secondary-button type="button" wire:click="$set('view', 'upload'), $">{{ __('Mundur') }}</x-secondary-button>
                    <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'd-logs-review'); $dispatch('d-logs-review', { logs: '{{ json_encode($logs) }}', xzones: '{{ json_encode($xzones) }}', yzones: '{{ json_encode($yzones)}}' })">{{ __('Tinjau data') }}</x-secondary-button>
                    <x-primary-button type="button" wire:click="save">{{ __('Simpan') }}</x-primary-button>
                    @endif
                </div>
            </div>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
        </div>
    </div>
    @endif
</div>
