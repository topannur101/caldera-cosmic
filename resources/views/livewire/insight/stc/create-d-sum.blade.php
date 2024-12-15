<?php

use Livewire\Volt\Component;
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
use App\InsStcPush;

new class extends Component {

    use WithFileUploads;

    public          $file;
    public array    $logs = [['taken_at' => '', 'temp' => '']];

    public int      $sequence;
    public string   $userq = '';
    public int      $user_2_id;
    public string   $user_2_name;
    public string   $user_2_emp_id;
    public int      $machine_id;
    public string   $position;
    public float    $speed;
    public string   $sv_temps_raw;
    public array    $sv_temps = [];
    public string   $device_code = '';

    public string   $started_at;
    public string   $ended_at;
    public float    $preheat = 0;
    public float    $section_1 = 0;
    public float    $section_2 = 0;
    public float    $section_3 = 0;
    public float    $section_4 = 0;
    public float    $section_5 = 0;
    public float    $section_6 = 0;
    public float    $section_7 = 0;
    public float    $section_8 = 0;
    public float    $postheat = 0;

    public bool $auto_adjust = true;

    public string $view = 'initial';
    public string $duration;

    public function with(): array
    {
        return [
            'machines' => InsStcMachine::orderBy('line')->get(),
        ];
    }

    public function submitInitial()
    {
        $this->device_code = strtoupper(trim($this->device_code));

        $this->userq = trim($this->userq);
        $this->reset(['user_2_id', 'user_2_name', 'user_2_emp_id']);
        if ($this->userq) {
            $user_2 = User::where('emp_id', $this->userq)->first();
            if ($user_2) {
                $this->user_2_id = $user_2->id;
                $this->user_2_name = $user_2->name;
                $this->user_2_emp_id = $user_2->emp_id;
            } else {
                $this->reset(['userq']);
            }
        }

        $this->validate([
            'sequence'      => ['required', 'integer', 'min:1', 'max:2'],
            'user_2_id'     => ['nullable', 'exists:users,id'],
            'machine_id'    => ['required', 'integer', 'exists:ins_stc_machines,id'],
            'position'      => ['required', 'in:upper,lower'],
            'speed'         => ['required', 'numeric', 'min:0.1', 'max:1'],
            'sv_temps'      => ['required', 'array', 'size:8'],
            'sv_temps.*'    => ['required', 'numeric', 'min:1', 'max:99'],
            'device_code'   => ['required', 'exists:ins_stc_devices,code'],
        ]);
        $this->view = 'upload';
    }

    public function save()
    {

        if ($this->view == 'review') {

            $d_sum = new InsStcDsum();
            Gate::authorize('manage', $d_sum);

            $device = InsStcDevice::where('code', $this->device_code)->first();

            $d_sum->fill([
                'ins_stc_device_id' => $device->id,
                'ins_stc_machine_id' => $this->machine_id,
                'user_1_id' => Auth::user()->id,
                'user_2_id' => $this->user_2_id ?? null,
                'started_at' => $this->started_at,
                'ended_at' => $this->ended_at,

                'preheat' => $this->preheat,
                'section_1' => $this->section_1,
                'section_2' => $this->section_2,
                'section_3' => $this->section_3,
                'section_4' => $this->section_4,
                'section_5' => $this->section_5,
                'section_6' => $this->section_6,
                'section_7' => $this->section_7,
                'section_8' => $this->section_8,
                'postheat' => $this->postheat,

                'speed' => $this->speed,
                'sequence' => $this->sequence,
                'position' => $this->position,
                'sv_temps' => json_encode($this->sv_temps),
            ]);

            $d_sum->save();

            foreach ($this->logs as $log) {
                InsStcDLog::create([
                    'ins_stc_d_sum_id' => $d_sum->id,
                    'taken_at' => $log['taken_at'],
                    'temp' => $log['temp'],
                ]);
            }

            $insStcPush = new InsStcPush();
            $pushStatus = [
                'is_sent' => false,
                'message' => ''
            ];

            if (strpos($d_sum->ins_stc_machine->ip_address, '127.') !== 0) {

                try {
                    // Send section data
                    $insStcPush->send('section_hb', $d_sum->ins_stc_machine->ip_address, $this->position, [
                        $d_sum->section_1,
                        $d_sum->section_2,
                        $d_sum->section_3,
                        $d_sum->section_4,
                        $d_sum->section_5,
                        $d_sum->section_6,
                        $d_sum->section_7,
                        $d_sum->section_8
                    ]);

                    // Calculate zones by averaging adjacent sections
                    $zones = [
                        // Zone 1: Average of section 1 and 2
                        ($d_sum->section_1 + $d_sum->section_2) / 2,
                        // Zone 2: Average of section 3 and 4
                        ($d_sum->section_3 + $d_sum->section_4) / 2,
                        // Zone 3: Average of section 5 and 6
                        ($d_sum->section_5 + $d_sum->section_6) / 2,
                        // Zone 4: Average of section 7 and 8
                        ($d_sum->section_7 + $d_sum->section_8) / 2
                    ];

                    // Send zone data
                    $insStcPush->send('zone_hb', $d_sum->ins_stc_machine->ip_address, $this->position, $zones);
                    $pushStatus['is_sent'] = true;

                } catch (\Exception $e) {
                    $pushStatus['is_sent'] = false;
                    $pushStatus['message'] = htmlspecialchars($e->getMessage(), ENT_QUOTES);
                }

            } else {
                $pushStatus['is_sent'] = false;
                $pushStatus['message'] = 'The IP is a loopback address. Ignored.';
            }

            $d_sum = $d_sum->toArray();
            $d_sum['auto_adjust'] = $pushStatus['is_sent'] && $this->auto_adjust;
            
            $this->dispatch('d_sum-created', $d_sum);

            if ($pushStatus['is_sent'] && !$this->auto_adjust) {
                $this->js('notyfSuccess("' . __('Nilai HB disimpan dan dikirim ke HMI') . '")');

            } else if(!$pushStatus['is_sent']) {
                $this->js('notyfSuccess("' . __('Nilai HB disimpan namun tidak dikirim ke HMI. Periksa console.') . '")');
                $this->js('console.log("' . $pushStatus['message'] . '")');
            }
            $this->customReset();
        }
    }

    public function updatedFile()
    {
        $this->validate([
            'file' => 'file|mimes:csv|max:1024',
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
        $logTempMinEnd = 40;

        foreach ($rows as $row) {
            if (isset($row[0]) && isset($row[$tempColumn]) && $row[0] !== '' && $row[$tempColumn] !== '') {
                $excel_ts   = (float) (($row[1]- 25569) * 86400);
                $taken_at   = Carbon::createFromTimestamp($excel_ts)->format('Y-m-d H:i');
                $temp       = (float) $row[$tempColumn];
                $timestamp  = (float) $row[1];

                $logs[] = [
                    'taken_at'  => $taken_at,
                    'temp'      => $temp,
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
            $this->js('notyfError("' . __('Tidak cukup data yang sah ditemukan') . '")');
            return;
        }

        // Filter out low temperatures in the last half
        $halfIndex = floor(count($logs) / 2);
        $logs = array_filter($logs, function($item) use ($halfIndex, $logTempMinEnd, $logs) {
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
            $this->js('notyfError("' . __('Tidak ada data yang sah ditemukan') . '")');
        } else {
            
            $temps = array_map(fn($item) => $item['temp'], $logs);
            $medians = InsStc::getMediansBySection($temps);

            $validator = Validator::make(
                [
                    'started_at'    => $logs[0]['taken_at'],
                    'ended_at'      => $logs[array_key_last($logs)]['taken_at'],
                    'preheat'       => $medians['preheat'],
                    'section_1'     => $medians['section_1'],
                    'section_2'     => $medians['section_2'],
                    'section_3'     => $medians['section_3'],
                    'section_4'     => $medians['section_4'],
                    'section_5'     => $medians['section_5'],
                    'section_6'     => $medians['section_6'],
                    'section_7'     => $medians['section_7'],
                    'section_8'     => $medians['section_8'],
                    'postheat'      => $medians['postheat'],
                ],
                [
                    'started_at'    => 'required|date',
                    'ended_at'      => 'required|date|after:started_at',
                    'preheat'       => 'required|numeric|min:1|max:99',
                    'section_1'     => 'required|numeric|min:1|max:99',
                    'section_2'     => 'required|numeric|min:1|max:99',
                    'section_3'     => 'required|numeric|min:1|max:99',
                    'section_4'     => 'required|numeric|min:1|max:99',
                    'section_5'     => 'required|numeric|min:1|max:99',
                    'section_6'     => 'required|numeric|min:1|max:99',
                    'section_7'     => 'required|numeric|min:1|max:99',
                    'section_8'     => 'required|numeric|min:1|max:99',
                    'postheat'      => 'nullable|numeric|min:1|max:99',
                ],
            );

            if ($validator->fails()) {
                $error = $validator->errors()->first();
                $this->js('notyfError("' . $error . '")');
                $this->reset(['file']);

            } else {
                $this->logs         = $logs;
                $validatedData      = $validator->validated();
                $this->started_at   = $validatedData['started_at'];
                $this->ended_at     = $validatedData['ended_at'];
                $this->preheat      = $validatedData['preheat'];
                $this->section_1    = $validatedData['section_1'];
                $this->section_2    = $validatedData['section_2'];
                $this->section_3    = $validatedData['section_3'];
                $this->section_4    = $validatedData['section_4'];
                $this->section_5    = $validatedData['section_5'];
                $this->section_6    = $validatedData['section_6'];
                $this->section_7    = $validatedData['section_7'];
                $this->section_8    = $validatedData['section_8'];
                $this->postheat     = $validatedData['postheat'] ?? 0;
                $this->duration     = InsStc::duration($validatedData['started_at'], $validatedData['ended_at']);

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
            'sv_temps_raw', 
            'sv_temps', 
            'device_code', 
            'started_at', 
            'ended_at', 
            'preheat', 
            'section_1', 
            'section_2', 
            'section_3', 
            'section_4', 
            'section_5', 
            'section_6', 
            'section_7', 
            'section_8', 
            'postheat', 
            'view', 
            'duration'
        ]);
    }

    public function downloadCSV()
    {
        $filePath = public_path('ins-stc-sample.csv');

        if (!file_exists($filePath)) {
            $this->js('alert("' . __('File CSV tidak ditemukan') . '")');
            return;
        }

        return response()->streamDownload(
            function () use ($filePath) {
                echo file_get_contents($filePath);
            },
            'ins-stc-sample.csv',
            [
                'Content-Type' => 'text/csv',
            ],
        );
    }
};
?>

<div>
    <h1 class="grow text-2xl text-neutral-900 dark:text-neutral-100 px-8">{{ __('Pembacaan alat') }}</h1>
    <div class="w-full my-8">
        <div wire:key="modals">
        <x-modal name="d-logs-review" maxWidth="2xl">
            <livewire:insight.stc.create-d-sum-review />
        </x-modal>
            <x-modal name="auto-adjustment-help">
                <div class="p-6">
                    <div class="flex justify-between items-start">
                        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Kirim langsung SV prediksi') }}
                        </h2>
                        <x-text-button type="button" x-on:click="$dispatch('close')"><i
                                class="fa fa-times"></i></x-text-button>
                    </div>
                    <div class="grid gap-y-6 mt-6 text-sm text-neutral-600 dark:text-neutral-400">
                        <div>
                            {{ __('SV Prediksi (SVP) akan dikirim langsung ke HMI setelah data pengukuran berhasil disimpan.') }}
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <x-primary-button type="button" x-on:click="$dispatch('close')">
                            {{ __('Paham') }}
                        </x-primary-button>
                    </div>
                </div>
            </x-modal>
        </div>
        <div x-data="{ dropping: false }" class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            @switch($view)
                @case('initial')
                    <div>
                        <div class="mb-6">
                            <div class="grid grid-cols-2 gap-x-3">
                                <div>
                                    <label for="d-log-sequence"
                                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Urutan') }}</label>
                                    <x-select class="w-full" id="d-log-sequence" wire:model="sequence">
                                        <option value=""></option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                    </x-select>
                                </div>
                                <div x-data="{ open: false, userq: @entangle('userq').live }"
                                    x-on:user-selected="userq = $event.detail.user_emp_id; open = false">
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
                            <div class="grid grid-cols-2 gap-x-3">
                                <div>
                                    <label for="d-log-machine_id"
                                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                                    <x-select class="w-full" id="d-log-machine_id" wire:model="machine_id">
                                        <option value=""></option>
                                        @foreach ($machines as $machine)
                                            <option value="{{ $machine->id }}">{{ $machine->line }}</option>
                                        @endforeach
                                    </x-select>
                                </div>
                                <div>
                                    <label for="d-log-position"
                                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                                    <x-select class="w-full" id="d-log-position" wire:model="position">
                                        <option value=""></option>
                                        <option value="upper">{{ __('Atas') }}</option>
                                        <option value="lower">{{ __('Bawah') }}</option>
                                    </x-select>
                                </div>
                            </div>
                            @error('machine_id')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                            @error('position')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        <div class="mb-6">
                            <div class="grid grid-cols-2 gap-x-3">
                                <div x-data="{
                                    sv_temps: @entangle('sv_temps'),
                                    sv_temps_raw: @entangle('sv_temps_raw'),
                                    sv_temps_count: 0,
                                    updateSVTemps() {
                                        if (this.sv_temps_raw) {
                                            this.sv_temps = this.sv_temps_raw.split(',').map(temp => temp.trim()).filter(temp => temp !== '');
                                        }
                                        this.sv_temps_count = this.sv_temps.length;
                                    }
                                }" x-init="updateSVTemps">
                                    <div class="flex justify-between px-3 mb-2 uppercase text-xs text-neutral-500">
                                        <label for="d-log-sv_temps">{{ __('SV') }}</label>
                                        <div><span x-text="sv_temps_count"></span>{{ ' ' . __('terbaca') }}</div>
                                    </div>
                                    <x-text-input id="d-log-sv_temps" x-model="sv_temps_raw" @input="updateSVTemps"
                                        type="text" placeholder="75, 65, 55,..." />
                                    @error('sv_temps')
                                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                    @enderror
                                    @error('sv_temps.*')
                                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                    @enderror
                                </div>
                                <div>
                                    <label for="d-log-speed"
                                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kecepatan') }}</label>
                                    <x-text-input-suffix suffix="RPM" id="d-log-speed" wire:model="speed" type="number"
                                        step=".01" autocomplete="off" />
                                </div>
                            </div>
                            @error('speed')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2 mb-6" />
                            @enderror
                        </div>
                        <div class="mb-6">
                            <label for="d-log-device_code"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode alat ukur') }}</label>
                            <x-text-input id="d-log-device_code" wire:model="device_code" type="text"
                                placeholder="Scan atau ketik di sini..." />
                            @error('device_code')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                    </div>
                @break

                @case('upload')
                    <div class="relative" x-on:dragover.prevent="dropping = true">
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
                                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">
                                    {{ __('Informasi pengukuran') }}</dt>
                                <dd class="grid grid-cols-2">
                                    <div class="flex gap-x-3 items-center">
                                        <div class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Urutan') . ': ' }}
                                        </div>
                                        <div>
                                            {{ $sequence }}
                                        </div>                                        
                                    </div>
                                    <div class="flex gap-x-3 items-center">
                                        <div class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Mitra kerja') . ': ' }}
                                        </div>
                                        <div>
                                            {{ $user_2_emp_id ? $user_2_name . ' (' . $user_2_emp_id . ')' : '-' }}
                                        </div>
                                    </div>
                                    <div class="flex gap-x-3 items-center">
                                        <div class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Kode alat ukur') . ': ' }}
                                        </div>
                                        <div>
                                            {{ $device_code }}
                                        </div>
                                    </div>
                                </dd>
                            </div>
                            <div class="flex flex-col py-6">
                                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">
                                    {{ __('Informasi mesin') }}</dt>
                                <dd>
                                    <table class="table table-xs table-col-heading-fit">
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Line') . '/' . __('Posisi') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $machines->firstWhere('id', $this->machine_id)->line . ' ' . InsStc::positionHuman($position) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Mesin') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $machines->firstWhere('id', $this->machine_id)->code . ' (' . $machines->firstWhere('id', $this->machine_id)->name . ')' }}
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
                                                {{ __('SV') . ': ' }}
                                            </td>
                                            <td class="flex gap-x-2">
                                                @foreach ($sv_temps as $sv_temp)
                                                    <div>
                                                        {{ $sv_temp }}
                                                    </div>
                                                    @if (!$loop->last)
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
                            <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">
                                {{ __('Informasi pengukuran') }}</dt>
                            <dd class="grid grid-cols-2">
                                <div class="flex gap-x-3 items-center">
                                    <div class="text-neutral-500 dark:text-neutral-400 text-sm">
                                        {{ __('Urutan') . ': ' }}
                                    </div>
                                    <div>
                                        {{ $sequence }}
                                    </div>                                        
                                </div>
                                <div class="flex gap-x-3 items-center">
                                    <div class="text-neutral-500 dark:text-neutral-400 text-sm">
                                        {{ __('Mitra kerja') . ': ' }}
                                    </div>
                                    <div>
                                        {{ $user_2_emp_id ? $user_2_name . ' (' . $user_2_emp_id . ')' : '-' }}
                                    </div>
                                </div>
                                <div class="flex gap-x-3 items-center">
                                    <div class="text-neutral-500 dark:text-neutral-400 text-sm">
                                        {{ __('Kode alat ukur') . ': ' }}
                                    </div>
                                    <div>
                                        {{ $device_code }}
                                    </div>
                                </div>
                            </dd>
                        </div>
                        <div class="flex flex-col py-6">
                            <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">
                                {{ __('Informasi mesin') }}</dt>
                            <dd>
                                <table class="table table-xs table-col-heading-fit">
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Line') . '/' . __('Posisi') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $machines->firstWhere('id', $this->machine_id)->line . ' ' . InsStc::positionHuman($position) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Mesin') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $machines->firstWhere('id', $this->machine_id)->code . ' (' . $machines->firstWhere('id', $this->machine_id)->name . ')' }}
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
                                            {{ __('SV') . ': ' }}
                                        </td>
                                        <td class="flex gap-x-2">
                                            @foreach ($sv_temps as $sv_temp)
                                                <div>
                                                    {{ $sv_temp }}
                                                </div>
                                                @if (!$loop->last)
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
                        <div class="flex flex-col py-6">
                            <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">
                                {{ __('Informasi hasil ukur') }}</dt>
                            <dd>
                                <table class="table table-xs table-col-heading-fit">
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Latensi unggah') . ': ' }}
                                        </td>
                                        <td>
                                            {{ InsStc::duration($this->ended_at, Carbon::now() ) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Durasi') }}
                                        </td>
                                        <td>
                                            {{ $duration . ' ' . __('dari') . ' ' . count($logs) . ' ' . __('baris data') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('HB') . ': ' }}
                                        </td>
                                        <td class="flex gap-x-3">
                                            <div>
                                                {{ $section_1 }}
                                            </div>
                                            <div class="text-neutral-300 dark:text-neutral-600">|</div>
                                            <div>
                                                {{ $section_2 }}
                                            </div>
                                            <div class="text-neutral-300 dark:text-neutral-600">|</div>
                                            <div>
                                                {{ $section_3 }}
                                            </div>
                                            <div class="text-neutral-300 dark:text-neutral-600">|</div>
                                            <div>
                                                {{ $section_4 }}
                                            </div>
                                            <div class="text-neutral-300 dark:text-neutral-600">|</div>
                                            <div>
                                                {{ $section_5 }}
                                            </div>
                                            <div class="text-neutral-300 dark:text-neutral-600">|</div>
                                            <div>
                                                {{ $section_6 }}
                                            </div>
                                            <div class="text-neutral-300 dark:text-neutral-600">|</div>
                                            <div>
                                                {{ $section_7 }}
                                            </div>
                                            <div class="text-neutral-300 dark:text-neutral-600">|</div>
                                            <div>
                                                {{ $section_8 }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </dd>
                        </div>
                        <div class="py-6">
                            <x-toggle name="d-sum-auto_adjust" wire:model.live="auto_adjust"
                            :checked="$auto_adjust ? true : false">{{ __('Kirim langsung SV prediksi') }}<x-text-button type="button"
                                class="ml-2" x-data=""
                                x-on:click="$dispatch('open-modal', 'auto-adjustment-help')"><i
                                    class="far fa-question-circle"></i></x-text-button>
                        </x-toggle>
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
                        @if ($view != 'initial')
                            <hr class="border-neutral-300 dark:border-neutral-600 {{ true ? '' : 'hidden' }}" />
                            <x-dropdown-link href="#" wire:click.prevent="customReset"
                                class="{{ true ? '' : 'hidden' }}">
                                {{ __('Ulangi dari awal') }}
                            </x-dropdown-link>
                        @endif
                    </x-slot>
                </x-dropdown>
                <div class="flex gap-x-2">
                    @if ($view == 'initial')
                        <x-primary-button type="button"
                            wire:click="submitInitial">{{ __('Lanjut') }}</x-primary-button>
                    @endif
                    @if ($view == 'upload')
                        <x-secondary-button type="button"
                            wire:click="$set('view', 'initial')">{{ __('Mundur') }}</x-secondary-button>
                        <x-primary-button type="button" x-on:click="$refs.file.click()"><i
                                class="fa fa-upload mr-2"></i>{{ __('Unggah') }}</x-primary-button>
                    @endif
                    @if ($view == 'review')
                        <x-secondary-button type="button"
                            wire:click="$set('view', 'upload'), $">{{ __('Mundur') }}</x-secondary-button>
                        <x-secondary-button type="button"
                            x-on:click.prevent="$dispatch('open-modal', 'd-logs-review'); $dispatch('d-logs-review', { logs: '{{ json_encode($logs) }}' })">{{ __('Tinjau') }}</x-secondary-button>
                        <x-primary-button type="button" wire:click="save">{{ __('Simpan') }}</x-primary-button>
                    @endif
                </div>
            </div>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
        </div>
    </div>
</div>
