<?php

use App\InsStc;
use App\InsStcPush;
use App\Models\InsStcDevice;
use App\Models\InsStcDLog;
use App\Models\InsStcDSum;
use App\Models\InsStcMachine;
use Carbon\Carbon;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $machines = [];

    public array $logs = [];

    public string $device_code = '';

    public $file;

    public array $d_sum = [
        'started_at' => '',
        'ended_at' => '',
        'speed' => '',
        'sequence' => '',
        'position' => '',
        'sv_values' => [],
        'sv_used' => 'd_sum',
        'sv_used_friendly' => '',
        'formula_id' => 412,
        'is_applied' => '',
        'target_values' => [],
        'hb_values' => [],
        'svp_values' => [],
        'svp_values_rel' => ['', '', '', '', '', '', '', ''],
        'ins_stc_machine_id' => '',
        'ins_stc_device_id' => '',
    ];

    public string $latency = '';

    public string $duration = '';

    public function mount()
    {
        $this->machines = InsStcMachine::all()->toArray();
        $this->d_sum['sv_used_friendly'] = __('SV manual');
    }

    public function updated($property)
    {

        // check m_log
        $check_m_log_props = ['d_sum.ins_stc_machine_id', 'd_sum.position'];
        if (in_array($property, $check_m_log_props)) {
            $this->check_m_log_sv();
            $this->calculatePrediction();
        }

        // check file upload
        if ($property == 'file') {
            // $this->resetErrorBag();
            $this->validate([
                'file' => 'file|mimes:csv|max:1024',
            ]);

            try {
                $this->extractData();

            } catch (Exception $e) {
                $this->js('console.log("'.$e->getMessage().'")');
            }

            $this->calculatePrediction();
        }
    }

    private function check_m_log_sv()
    {
        $this->d_sum['sv_values'] = [];
        $this->d_sum['sv_used'] = 'd_sum';
        $this->d_sum['sv_friendly'] = '';

        $machine_id = $this->d_sum['ins_stc_machine_id'];
        $position = $this->d_sum['position'];

        if ($machine_id && $position) {
            $m_log = InsStcMachine::find($machine_id)?->ins_stc_m_log($position)->first();

            if ($m_log) {

                $allAboveZero = true;
                for ($i = 1; $i <= 8; $i++) {
                    $property = 'sv_r_'.$i;
                    if ($m_log->$property <= 0) {
                        $allAboveZero = false;
                        break;
                    }
                }
                if ($allAboveZero) {
                    for ($i = 1; $i <= 8; $i++) {
                        $property = 'sv_r_'.$i;
                        $this->d_sum['sv_values'][] = $m_log->$property;
                        $this->d_sum['sv_used'] = 'm_log';
                        $this->d_sum['sv_used_friendly'] = __('SV otomatis');
                    }
                }

            }
        }
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
        $logTempMinEnd = 40;

        foreach ($rows as $row) {
            if (isset($row[0]) && isset($row[$tempColumn]) && $row[0] !== '' && $row[$tempColumn] !== '') {
                $excel_ts = (float) (($row[1] - 25569) * 86400);
                $taken_at = Carbon::createFromTimestamp($excel_ts)->format('Y-m-d H:i');
                $temp = (float) $row[$tempColumn];
                $timestamp = (float) $row[1];

                $logs[] = [
                    'taken_at' => $taken_at,
                    'temp' => $temp,
                    'timestamp' => $timestamp,
                ];
            }
        }

        // Sort logs by timestamp first
        usort($logs, function ($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        // Keep first 100 logs
        $logs = array_slice($logs, 0, 100);

        // If fewer than 4 logs, return empty
        if (count($logs) < 4) {
            $this->js('notyfError("'.__('Tidak cukup data yang sah ditemukan').'")');

            return;
        }

        // Filter out low temperatures in the last half
        $halfIndex = floor(count($logs) / 2);
        $logs = array_filter($logs, function ($item) use ($halfIndex, $logTempMinEnd, $logs) {
            $index = array_search($item, $logs);

            // For the last half, apply end temperature threshold
            if ($index >= $halfIndex) {
                return $item['temp'] >= $logTempMinEnd;
            }

            // Keep all logs from first half as-is
            return true;
        });

        // Reindex the logs
        $logs = array_values($logs);

        if (empty($logs)) {
            $this->js('notyfError("'.__('Tidak ada data yang sah ditemukan').'")');
        } else {

            $temps = array_map(fn ($item) => $item['temp'], $logs);
            $medians = InsStc::getMediansBySection($temps);

            $validator = Validator::make(
                [
                    'started_at' => $logs[0]['taken_at'],
                    'ended_at' => $logs[array_key_last($logs)]['taken_at'],
                    'preheat' => $medians['preheat'],
                    'section_1' => $medians['section_1'],
                    'section_2' => $medians['section_2'],
                    'section_3' => $medians['section_3'],
                    'section_4' => $medians['section_4'],
                    'section_5' => $medians['section_5'],
                    'section_6' => $medians['section_6'],
                    'section_7' => $medians['section_7'],
                    'section_8' => $medians['section_8'],
                    'postheat' => $medians['postheat'],
                ],
                [
                    'started_at' => 'required|date',
                    'ended_at' => 'required|date|after:started_at',
                    'preheat' => 'required|numeric|min:1|max:99',
                    'section_1' => 'required|numeric|min:1|max:99',
                    'section_2' => 'required|numeric|min:1|max:99',
                    'section_3' => 'required|numeric|min:1|max:99',
                    'section_4' => 'required|numeric|min:1|max:99',
                    'section_5' => 'required|numeric|min:1|max:99',
                    'section_6' => 'required|numeric|min:1|max:99',
                    'section_7' => 'required|numeric|min:1|max:99',
                    'section_8' => 'required|numeric|min:1|max:99',
                    'postheat' => 'nullable|numeric|min:1|max:99',
                ],
            );

            if ($validator->fails()) {
                $error = $validator->errors()->first();
                $this->js('notyfError("'.$error.'")');
                $this->reset(['file']);

            } else {
                $this->logs = $logs;
                $validatedData = $validator->validated();
                $this->d_sum['started_at'] = $validatedData['started_at'];
                $this->d_sum['ended_at'] = $validatedData['ended_at'];
                $this->d_sum['hb_values'][0] = $validatedData['section_1'];
                $this->d_sum['hb_values'][1] = $validatedData['section_2'];
                $this->d_sum['hb_values'][2] = $validatedData['section_3'];
                $this->d_sum['hb_values'][3] = $validatedData['section_4'];
                $this->d_sum['hb_values'][4] = $validatedData['section_5'];
                $this->d_sum['hb_values'][5] = $validatedData['section_6'];
                $this->d_sum['hb_values'][6] = $validatedData['section_7'];
                $this->d_sum['hb_values'][7] = $validatedData['section_8'];
                $this->duration = Carbon::parse($validatedData['started_at'])->diff(Carbon::parse($validatedData['ended_at']))->format('%H:%I:%S');
                $this->latency = InsStc::duration($validatedData['ended_at'], Carbon::now(), 'short');
            }
        }
    }

    private function resetPrediction()
    {
        $this->d_sum['svp_values'] = [];
        $this->d_sum['svp_values_rel'] = ['', '', '', '', '', '', '', ''];
    }

    public function calculatePrediction()
    {
        $this->resetPrediction();
        $this->validateBeforePredict();

        $svp_values = InsStc::calculateSVP(
            $this->d_sum['hb_values'],
            $this->d_sum['sv_values'],
            $this->d_sum['formula_id']
        );
        $this->d_sum['svp_values'] = array_map(function ($item) {
            return $item['absolute'];
        }, $svp_values);
        $this->d_sum['svp_values_rel'] = array_map(function ($item) {
            return $item['relative'];
        }, $svp_values);

    }

    private function validateBeforePredict()
    {
        $this->validate([
            'd_sum.hb_values' => 'required|array|size:8',
            'd_sum.hb_values.*' => 'required|numeric|min:30|max:99',
            'd_sum.sv_values' => 'required|array|size:8',
            'd_sum.sv_values.*' => 'required|numeric|min:30|max:99',
            'd_sum.formula_id' => 'required|in:411,412,421',
        ]);
    }

    private function validateAfterPredict()
    {
        $this->validate([
            'd_sum.svp_values' => 'required|array|size:8',
            'd_sum.svp_values.*' => 'required|numeric|min:30|max:99',
        ]);
    }

    public function send()
    {
        $this->validate([
            'd_sum.sequence' => ['required', 'integer', 'min:1', 'max:2'],
            'd_sum.speed' => ['required', 'numeric', 'min:0.1', 'max:0.9'],
            'd_sum.ins_stc_machine_id' => ['required', 'integer', 'exists:ins_stc_machines,id'],
            'd_sum.position' => ['required', 'in:upper,lower'],
            'device_code' => ['required', 'exists:ins_stc_devices,code'],
        ]);

        $this->validateBeforePredict();
        $this->validateAfterPredict();

        $is_applied = $this->push();

        $this->save($is_applied);

        if ($is_applied) {
            $this->js('notyfSuccess("' . __('Nilai HB beserta SVP disimpan dan dikirim ke HMI') . '")');

         } else {
            $this->js('notyfSuccess("' . __('Nilai HB beserta SVP disimpan namun tidak dikirim ke HMI. Periksa console.') . '")');
         }

        $this->reset([
            'machines',
            'logs',
            'device_code',
            'file',
            'd_sum',
            'latency',
            'duration',
        ]);
    }

    private function save(bool $is_applied)
    {
        $d_sum = new InsStcDsum;
        Gate::authorize('manage', $d_sum);

        $d_sum_before = InsStcDSum::where('created_at', '<', Carbon::now())
            ->where('ins_stc_machine_id', $this->d_sum['ins_stc_machine_id'])
            ->where('position', $this->d_sum['position'])
            ->where('created_at', '>=', Carbon::now()->subHours(6))
            ->orderBy('created_at', 'desc')
            ->first();

        $svp_before = $d_sum_before ? json_decode($d_sum_before->svp_values, true) : [];
        $sv_now = $this->d_sum['sv_values'];

        $integrity = 'none';

        if (count($sv_now) === count($svp_before) && count($sv_now) === 8) {
            $is_stable = true;

            foreach ($sv_now as $key => $value) {
                if (! isset($svp_before[$key]) || abs($value - $svp_before[$key]) > 2) {
                    $is_stable = false;
                    break;
                }
            }

            if ($is_stable) {
                $integrity = 'stable';
            } else {
                $integrity = 'modified';
            }
        }

        $device = InsStcDevice::where('code', $this->device_code)->first();

        $d_sum->fill([
            'ins_stc_device_id'     => $device->id,
            'ins_stc_machine_id'    => $this->d_sum['ins_stc_machine_id'],
            'user_id'               => Auth::user()->id,
            'started_at'            => $this->d_sum['started_at'],
            'ended_at'              => $this->d_sum['ended_at'],

            'speed'                 => $this->d_sum['speed'],
            'sequence'              => $this->d_sum['sequence'],
            'position'              => $this->d_sum['position'],
            'sv_values'             => json_encode($this->d_sum['sv_values']),
            'formula_id'            => $this->d_sum['formula_id'],
            'sv_used'               => $this->d_sum['sv_used'],
            'target_values'         => json_encode(InsStc::$target_values),
            'hb_values'             => json_encode($this->d_sum['hb_values']),
            'svp_values'            => json_encode($this->d_sum['svp_values']),
            'integrity'             => $integrity,
            'is_applied'            => $is_applied,
        ]);

        $d_sum->save();

        // is_applied dan integrity nya belum
        foreach ($this->logs as $log) {
            InsStcDLog::create([
                'ins_stc_d_sum_id' => $d_sum->id,
                'taken_at' => $log['taken_at'],
                'temp' => $log['temp'],
            ]);
        }
    }

    private function push(): bool
    {
        $machine = InsStcMachine::find($this->d_sum['ins_stc_machine_id']);
        $push = new InsStcPush;
        $zones = [
            (int) round(($this->d_sum['hb_values'][0] + $this->d_sum['hb_values'][1]) / 2, 0),
            (int) round(($this->d_sum['hb_values'][2] + $this->d_sum['hb_values'][3]) / 2, 0),
            (int) round(($this->d_sum['hb_values'][4] + $this->d_sum['hb_values'][5]) / 2, 0),
            (int) round(($this->d_sum['hb_values'][6] + $this->d_sum['hb_values'][7]) / 2, 0),
        ];

        $is_applied = false;

        try {
            // push HB section
            $push->send(
                'section_hb',
                $machine->ip_address,
                $this->d_sum['position'],
                $this->d_sum['hb_values']
            );

            // push HB zone
            // $push->send(
            //     'zone_hb',
            //     $machine->ip_address,
            //     $this->d_sum['position'],
            //     $zones
            // );

            // push SVP
            $push->send(
                'section_svp',
                $machine->ip_address,
                $this->d_sum['position'],
                $this->d_sum['svp_values']
            );
            
            // // push SVW
            $push->send(
                'apply_svw',
                $machine->ip_address,
                $this->d_sum['position'],
                [true]
            );

            $is_applied = true;

        } catch (Exception $e) {
            $this->js('console.log("'.$e->getMessage().'")');

        } finally {
            return $is_applied;
        }

    }
}

?>






<div>
   <div wire:key="modals">
      <x-modal name="reading-review" maxWidth="2xl">
         <livewire:insight.stc.create-reading-review />
      </x-modal>
   </div>
   <div class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 divide-y md:divide-x md:divide-y-0 divide-neutral-200 dark:text-white dark:divide-neutral-700">
         <div class="p-6">
            <h1 class="grow text-xl text-neutral-900 dark:text-neutral-100 mb-6">{{ __('Mesin') }}</h1>
            <div class="grid grid-cols-2 gap-x-3 mb-6">
               <div>
                  <label for="d-log-sequence"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Urutan') }}</label>
                  <x-select class="w-full" id="d-log-sequence" wire:model="d_sum.sequence">
                        <option value=""></option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                  </x-select>
               </div>
               <div>
                  <label for="d-log-speed"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kecepatan') }}</label>
                  <x-text-input-suffix suffix="RPM" id="d-log-speed" wire:model="d_sum.speed" type="number"
                        step=".01" autocomplete="off" />
               </div>
            </div>
            <div class="grid grid-cols-2 gap-x-3 mb-6">
               <div>
                  <label for="d-log-machine_id"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                  <x-select class="w-full" id="d-log-machine_id" wire:model.live="d_sum.ins_stc_machine_id">
                        <option value="0"></option>
                        @foreach ($machines as $machine)
                           <option value="{{ $machine['id'] }}">{{ $machine['line'] }}</option>
                        @endforeach
                  </x-select>
               </div>
               <div>
                  <label for="d-log-position"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                  <x-select class="w-full" id="d-log-position" wire:model.live="d_sum.position">
                        <option value=""></option>
                        <option value="upper">{{ '△ ' . __('Atas') }}</option>
                        <option value="lower">{{ '▽ ' . __('Bawah') }}</option>
                  </x-select>
               </div>
            </div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">
               <span>{{ __('SV') }}</span>
               @if($d_sum['sv_used'] == 'm_log')
                  <i class="fa fa-lock ms-2"></i>
               @endif
            </label>
            @if($d_sum['sv_used'] == 'm_log')
               <div class="grid grid-cols-8">
                  <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.0" />
                  <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.1" />
                  <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.2" />
                  <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.3" />
                  <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.4" />
                  <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.5" />
                  <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.6" />
                  <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.7" />
               </div>
            @else
               <div class="grid grid-cols-8">
                  <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.0" />
                  <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.1" />
                  <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.2" />
                  <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.3" />
                  <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.4" />
                  <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.5" />
                  <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.6" />
                  <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.7" />
               </div>
            @endif
         </div>
         <div class="p-6">
            <div class="flex justify-between">
               <h1 class="grow text-xl text-neutral-900 dark:text-neutral-100 mb-6">{{ __('Alat ukur') }}</h1>
               <div>
                  <input wire:model="file" type="file" class="hidden" x-ref="file" />
                  <x-secondary-button type="button" x-on:click="$refs.file.click()">{{ __('Unggah') }}</x-secondary-button>
               </div>
            </div>   
            <div class="mb-6">
               <label for="d-log-device_code"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
               <x-text-input id="d-log-device_code" wire:model="device_code" type="text"
                  placeholder="Scan atau ketik..." />
            </div>
            <div class="grid grid-cols-2 gap-x-3 mb-6">
               <div>
                  <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Latensi') }}</label>
                  <x-text-input-t placeholder="{{ __('Menunggu...') }}" disabled wire:model="latency"></x-text-input-t>
               </div>
               <div>
                  <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Durasi') }}</label>
                  <x-text-input-t placeholder="{{ __('Menunggu...') }}" disabled wire:model="duration"></x-text-input-t>
               </div>
            </div>
            <label class="flex justify-between px-3 mb-2 uppercase text-xs text-neutral-500">
               <div>{{ __('HB') }}</div>
               @if($logs)
               <x-text-button
                  x-on:click.prevent="$dispatch('open-modal', 'reading-review'); $dispatch('reading-review', { logs: '{{ json_encode($logs) }}', sv_temps: '{{ json_encode($d_sum['sv_values']) }}' })"
                  class="uppercase text-xs text-neutral-500" 
                  type="button"><i class="fa fa-eye mr-1"></i>{{ __('Tinjau') }}</x-text-button>
               @endif
            </label>
            <div class="grid grid-cols-8">
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.0" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.1" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.2" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.3" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.4" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.5" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.6" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.7" />
            </div>
         </div>
         <div class="p-6">
            <div class="flex justify-between">
               <h1 class="grow text-xl text-neutral-900 dark:text-neutral-100 mb-6">{{ __('Prediksi') }}</h1>
               <div>
                  <x-secondary-button type="button" wire:click="calculatePrediction">{{ __('Hitung') }}</x-secondary-button>
               </div>
            </div> 
            <div class="mb-6">
               <label for="adj-formula_id"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Formula') }}</label>
               <x-select class="w-full" id="adj-formula_id" wire:model.live="d_sum.formula_id" disabled>
                  <option value="0"></option>
                  <option value="411">{{ __('v4.1.1 - Diff aggresive') }}</option>
                  <option value="412">{{ __('v4.1.2 - Diff delicate') }}</option>
                  <option value="421">{{ __('v4.2.1 - Ratio') }}</option>
               </x-select>
            </div>
            <div class="mb-6">
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Referensi SV') }}</label>
               <x-text-input-t wire:model="d_sum.sv_used_friendly" placeholder="{{ __('Menunggu...') }}" disabled></x-text-input-t>
            </div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('SVP') }}</label>
            <div class="grid grid-cols-8">
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.0" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.1" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.2" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.3" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.4" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.5" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.6" />
               <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.7" />
            </div>
            <div class="grid grid-cols-8 text-neutral-500 text-xs text-center">
               <div>{{ $d_sum['svp_values_rel'][0] }}</div>
               <div>{{ $d_sum['svp_values_rel'][1] }}</div>
               <div>{{ $d_sum['svp_values_rel'][2] }}</div>
               <div>{{ $d_sum['svp_values_rel'][3] }}</div>
               <div>{{ $d_sum['svp_values_rel'][4] }}</div>
               <div>{{ $d_sum['svp_values_rel'][5] }}</div>
               <div>{{ $d_sum['svp_values_rel'][6] }}</div>
               <div>{{ $d_sum['svp_values_rel'][7] }}</div>
            </div>
         </div>
      </div>
      <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
      <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
   </div>
   <div class="flex justify-between px-6">
      <div class="flex gap-x-3">
      @if ($errors->any())
         <i class="fa fa-exclamation-circle text-red-500"></i>
         <x-input-error :messages="$errors->first()" />
      @endif
      </div>      
      <x-primary-button type="button" wire:click="send">{{ __('Kirim') }}</x-primary-button>
   </div>
</div>
