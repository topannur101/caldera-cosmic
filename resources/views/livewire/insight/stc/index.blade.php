<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Validator;
use App\Models\InsStcMachine;
use App\Models\InsStcLog;

new #[Layout('layouts.app')] 
class extends Component {

    use WithFileUploads;

    public $file;

    public int $machine_id;
    public string $device_code = '';
    public string $start_time;
    public string $end_time;
    public float $preheat_temp;
    public float $z_1_temp;
    public float $z_2_temp;
    public float $z_3_temp;
    public float $z_4_temp;
    public int $speed;

    public array $logs = [['taken_at' => '', 'temp' => '']];

    public string $view = 'initial';

    public const PREHEAT_COUNT = 5;
    public const ZONE_1_COUNT = 12;
    public const ZONE_2_COUNT = 12;
    public const ZONE_3_COUNT = 12;
    public const ZONE_4_COUNT = 12;

    public const EXPECTED_DATA_COUNT = self::PREHEAT_COUNT + self::ZONE_1_COUNT + 
                                       self::ZONE_2_COUNT + self::ZONE_3_COUNT + 
                                       self::ZONE_4_COUNT;

    public const DATA_COUNT_TOLERANCE = 5;

    public const DATA_COUNT_EVAL = [
        'OPTIMAL' => 'optimal',
        'TOO_FEW' => 'too_few',
        'TOO_MANY' => 'too_many',
    ];

    public function rules()
    {
        return [
            'start_time'        => ['required', 'date', 'before_or_equal:end_time'],
            'end_time'          => ['required', 'date', 'after_or_equal:start_time'],
            'preheat_temp'      => ['required', 'numeric', 'min:0', 'max:99'],
            'z_1_temp'          => ['required', 'numeric', 'min:0', 'max:99'],
            'z_2_temp'          => ['required', 'numeric', 'min:0', 'max:99'],
            'z_3_temp'          => ['required', 'numeric', 'min:0', 'max:99'],
            'z_4_temp'          => ['required', 'numeric', 'min:0', 'max:99'],
            'speed'             => ['required', 'integer', 'min:1', 'max:99'],
            'logs'              => ['required', 'array', 'min:1', 'max:99'],
            'logs.*.taken_at'   => ['required', 'date', 'between:start_time,end_time'],
            'logs.*.temp'       => ['required', 'numeric', 'min:0', 'max:99'],
        ];
    }

    public function with(): array
    {
        return [
            'machines' => InsStcMachine::all()
        ];
    }

    public function submitInitial()
    {
        $this->device_code = strtoupper(trim($this->device_code));
        $this->validate([
            'machine_id'        => ['required', 'integer', 'exists:ins_stc_machines,id'],
            'device_code'       => ['required', 'exists:ins_stc_devices,code'],
        ]);
        $this->view = 'upload';
    }

    public function updatedFile()
    {
        $this->validate([
            'file' => 'file|mimes:csv|max:1024'
        ]);

        $this->extractData();
        $this->view = 'review';
    }

    private function extractData()
    {
        try {
            $csv = array_map('str_getcsv', file($this->file->getPathname()));
            array_shift($csv); // Remove header row

            // Sort by timestamp (first column)
            usort($csv, function($a, $b) {
                return strtotime($a[0]) - strtotime($b[0]);
            });

            $dataCount = min(count($csv), self::EXPECTED_DATA_COUNT);

            // Evaluate data count
            if ($dataCount < self::EXPECTED_DATA_COUNT - self::DATA_COUNT_TOLERANCE) {
                $this->dataCountEval = self::DATA_COUNT_EVAL['TOO_FEW'];
            } elseif ($dataCount > self::EXPECTED_DATA_COUNT + self::DATA_COUNT_TOLERANCE) {
                $this->dataCountEval = self::DATA_COUNT_EVAL['TOO_MANY'];
            } else {
                $this->dataCountEval = self::DATA_COUNT_EVAL['OPTIMAL'];
            }

            $data = array_slice($csv, 0, $dataCount);

            $this->start_time = $data[0][0];
            $this->end_time = end($data)[0];

            $this->preheat_temp = $this->calculateMedian(array_slice($data, 0, self::PREHEAT_COUNT));
            $this->z_1_temp = $this->calculateMedian(array_slice($data, self::PREHEAT_COUNT, self::ZONE_1_COUNT));
            $this->z_2_temp = $this->calculateMedian(array_slice($data, self::PREHEAT_COUNT + self::ZONE_1_COUNT, self::ZONE_2_COUNT));
            $this->z_3_temp = $this->calculateMedian(array_slice($data, self::PREHEAT_COUNT + self::ZONE_1_COUNT + self::ZONE_2_COUNT, self::ZONE_3_COUNT));
            $this->z_4_temp = $this->calculateMedian(array_slice($data, self::PREHEAT_COUNT + self::ZONE_1_COUNT + self::ZONE_2_COUNT + self::ZONE_3_COUNT, self::ZONE_4_COUNT));

            $this->speed = 0;

            $this->logs = array_map(function($row) {
                return [
                    'taken_at' => $row[0],
                    'temp' => round((float)$row[1], 1)
                ];
            }, $data);
            
        } catch (\Exception $e) {
            $this->js('notyfError("' . __('Terjadi galat ketika memproses berkas. Periksa console') . '")'); 
            $this->js('console.log("'. $e->getMessage() .'")');
            $this->view = 'upload';
            $this->reset(['file']);
        }
    }

    private function calculateMedian($data)
    {
        if (empty($data)) {
            return 0;
        }

        $temperatures = array_column($data, 1);
        sort($temperatures);
        $count = count($temperatures);
        $middleIndex = floor(($count - 1) / 2);

        if ($count % 2 == 0) {
            $median = ($temperatures[$middleIndex] + $temperatures[$middleIndex + 1]) / 2;
        } else {
            $median = $temperatures[$middleIndex];
        }

        return round($median, 1);
    }


};

?>

<x-slot name="title">{{ __('IP Stabilization Control') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-stc></x-nav-insights-stc>
</x-slot>

<div id="content" class="py-12 max-w-lg mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
        <h1 class="grow text-2xl text-neutral-900 dark:text-neutral-100 px-8">
            {{ __('Pembukuan') }}</h1>

    <div class="w-full my-8">
        <div x-data="{ dropping: false }" class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            @switch($view)
                @case('initial')
                    <div>
                        <div class="mb-6">
                            <label for="stc-machine_id"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mesin') }}</label>
                            <x-select class="w-full" id="stc-machine_id" wire:model="machine_id">
                                <option value=""></option>
                                @foreach ($machines as $machine)
                                    <option value="{{ $machine->id }}">{{ $machine->line . '. ' . $machine->code }}</option>
                                @endforeach
                            </x-select>
                            @error('machine_id')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        <div class="mb-6">
                            <label for="stc-device_code"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode alat') }}</label>
                            <x-text-input id="stc-device_code" wire:model="device_code" type="text"
                                :disabled="Gate::denies('manage', InsStcLog::class)" />
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
                            <div class="flex flex-col pb-6 mb-6">
                                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi instrumen') }}</dt>
                                <dd>
                                    <table class="table table-xs table-col-heading-fit">
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Mesin') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $machines->firstWhere('id', $this->machine_id) ? ($machines->firstWhere('id', $this->machine_id)->line . '. ' . $machines->firstWhere('id', $this->machine_id)->code ) : '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Kode alat') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $device_code }}
                                            </td>
                                        </tr>
                                    </table>
                                    @error('file')
                                        <x-input-error messages="{{ $message }}" class="px-1 mt-2" />
                                    @enderror
                                </dd>
                            </div>
                        </div>
                        @break

                        @case('review')
                        <dl class="text-neutral-900 divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
                            <div class="flex flex-col pb-6">
                                <dt class="mb-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi instrumen') }}</dt>
                                <dd>
                                    <table class="table table-xs table-col-heading-fit">
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Mesin') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $machines->firstWhere('id', $this->machine_id) ? ($machines->firstWhere('id', $this->machine_id)->line . '. ' . $machines->firstWhere('id', $this->machine_id)->code ) : '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                                {{ __('Kode alat') . ': ' }}
                                            </td>
                                            <td>
                                                {{ $device_code }}
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
                                            {{ __('Suhu awal') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $preheat_temp }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Zona 1') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $z_1_temp }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Zona 2') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $z_2_temp }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Zona 3') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $z_3_temp }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-neutral-500 dark:text-neutral-400 text-sm">
                                            {{ __('Zona 4') . ': ' }}
                                        </td>
                                        <td>
                                            {{ $z_4_temp }}
                                        </td>
                                    </tr>
                                </table>
                                <div class="mt-3">
                                    <label for="stc-speed"
                                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kecepatan') }}</label>
                                    <x-text-input-suffix suffix="RPM" id="stc-speed" x-model="speed" type="number" step="1" autocomplete="off" />
                                    @error('speed')
                                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                    @enderror
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
                        <x-dropdown-link href="#" wire:click.prevent="customReset">
                            {{ __('Unduh CSV contoh') }}
                        </x-dropdown-link>
                        @if($view != 'initial')
                        <hr class="border-neutral-300 dark:border-neutral-600 {{ true ? '' : 'hidden' }}" />
                        <x-dropdown-link href="#" wire:click.prevent="removeFromQueue"
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
                    <x-primary-button type="button" x-on:click="$refs.file.click()"><i
                        class="fa fa-upload mr-2"></i>{{ __('Unggah') }}</x-primary-button>
                    @endif
                    @if($view == 'review')
                    <x-secondary-button type="button">{{ __('Ulas data') }}</x-secondary-button>
                    <x-primary-button type="button">{{ __('Simpan') }}</x-primary-button>
                    @endif
                </div>
            </div>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
        </div>
    </div>
</div>
