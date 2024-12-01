<?php

use Livewire\Volt\Component;

use App\Models\InsStcMachine;
use App\Models\InsStcDSum;
use App\Models\InsStcDLog;
use App\Models\InsStcMLog;

new class extends Component {

    public int $machine_id = 0;
    public string $position = '';

    public array $d_sum = [];
    public array $d_logs = [];
    public array $m_log = [];

    public int $formula_id;
    public bool $use_m_log_sv = false;

    public function with(): array
    {
        return [
            'machines' => InsStcMachine::orderBy('line')->get()
        ];
    }

    public function updated($property)
    {
        if ($property == 'machine_id' || $property == 'position') {

            if ($this->machine_id && $this->position) {

                $d_sum  = InsStcDSum::where('ins_stc_machine_id', $this->machine_id)
                ->where('position', $this->position)
                ->orderBy('end_time', 'desc')
                ->first();

                if ($d_sum) {
                    $this->d_sum = $d_sum->toArray();
                    $d_logs = InsStcDLog::where('ins_stc_d_sum_id', $d_sum->id)->get();
                    if ($d_logs) {
                        $this->d_logs = $d_logs->toArray();
                    }
                } else {
                    $this->d_sum = [];
                }

                $m_log = InsStcMLog::where('ins_stc_machine_id', $this->machine_id)
                ->where('position', $this->position)
                ->latest()
                ->first();

                if ($m_log) {
                    $this->m_log = $m_log->toArray();
                } else {
                    $this->m_log = [];
                }
            }
        }
    }

};
?>

<div>
    <h1 class="grow text-2xl text-neutral-900 dark:text-neutral-100 px-8">{{ __('Penyetelan') }}</h1>
    <div wire:key="modals">
        <x-modal name="use_m_log_sv-help"> 
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Pemilihan SV') }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
                </div>
                <div class="grid gap-y-6 mt-6">
                    <div>{{ __('Ada dua rentetan nilai SV dan hanya satu rentetan SV yang  diambil untuk perhitungan SVP (SV Prediksi).') }}</div>
                    <div>
                        <div class="font-bold mb-3">{{ __('SV alat') }}</div>
                        <div>{{ __('SV didapat dari pencatatan manual yang dilakukan oleh pekerja ketika mengunggah hasil catatan alat ukur (HOBO).') }}</div>
                    </div>
                    <div>
                        <div class="font-bold mb-3">{{ __('SV mesin') }}</div>
                        <div>{{ __('SV didapat langsung dari mesin sehingga pilihan ini lebih akurat dan paling ideal.') }}</div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Paham') }}
                    </x-primary-button>
                </div>
            </div>
        </x-modal>
        <x-modal name="adj-save">
            <div class="p-6">
                Simpan saja
                Angka SV prediksi hanya akan di simpan sebagai catatan dan tidak akan di kirimkan ke HMI.

                Input form: Keterangan
            </div>
        </x-modal>
        <x-modal name="adj-send">
            <div class="p-6">
                Kirim ke HMI
                Angka SV prediksi akan di kirimkan ke HMI dan kamu harus menekan tombol Terapkan di HMI agar SV dapat berubah.

                Input form: Keterangan
            </div>
        </x-modal>
    </div>
    <div class="w-full my-8">
        <div class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div>
                <div class="grid grid-cols-2 gap-x-3">
                    <div>
                        <label for="adj-machine_id"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                        <x-select class="w-full" id="adj-machine_id" wire:model.live="machine_id">
                            <option value=""></option>
                            @foreach ($machines as $machine)
                                <option value="{{ $machine->id }}">{{ $machine->line }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div>
                        <label for="adj-position"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                        <x-select class="w-full" id="adj-position" wire:model.live="position">
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
            @if($machine_id && $position)
            <div class="grid grid-cols-1 gap-y-6 mt-6">
                <div>
                    <label class="flex gap-x-2 px-3 mb-2 uppercase text-xs text-neutral-500">
                        <div>{{ __('Pembacaan mesin') }}</div>
                        <div>•</div>
                        @if($m_log)
                        <div>{{ Carbon\Carbon::parse($m_log['created_at'])->diffForHumans() }}</div>
                        @else
                        <div class="text-red-500">{{ __('Tak ditemukan') }}</div>
                        @endif
                    </label>
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
                        <div>PV</div>
                        @for ($i = 1; $i <= 8; $i++)
                            <div>{{ $m_log['pv_r_' . $i ] ?? '-'}}</div>
                        @endfor

                        <div>SV</div>
                        @for ($i = 1; $i <= 8; $i++)
                            <div>{{ $m_log['sv_r_' . $i ] ?? '-'}}</div>
                        @endfor
                    </div>               
                </div>
                <div>
                    <label class="flex gap-x-2 px-3 mb-2 uppercase text-xs text-neutral-500">
                        <div>{{ __('Pembacaan alat') }}</div>
                        <div>•</div>
                        @if($d_sum)
                        <div>{{ Carbon\Carbon::parse($d_sum['end_time'])->diffForHumans() }}</div>
                        @else
                        <div class="text-red-500">{{ __('Tak ditemukan') }}</div>
                        @endif
                    </label>
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
                        <div>01</div>
                        <div>02</div>
                        <div>03</div>
                        <div>04</div>
                        <div>05</div>
                        <div>06</div>
                        <div>07</div>
                        <div>08</div>
                        {{-- 
                        <div>SV</div>
                        <div>01</div>
                        <div>02</div>
                        <div>03</div>
                        <div>04</div>
                        <div>05</div>
                        <div>06</div>
                        <div>07</div>
                        <div>08</div> --}}
                    </div>               
                </div>
                <div>
                    <div class="grid grid-cols-2 gap-x-3">
                        <div>
                            <label for="adj-formula_id"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Formula') }}</label>
                            <x-select class="w-full" id="adj-formula_id" wire:model="formula_id">
                                <option value=""></option>
                                <option value="4">{{ __('Versi 4') }}</option>
                            </x-select>
                            @error('formula_id')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        <div>
                            <label for="adj-use_m_log_sv"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pemilihan SV') }}</label>
                            <div class="p-2">
                                <x-toggle name="use_m_log_sv" wire:model.live="use_m_log_sv" :checked="$use_m_log_sv ? true : false" >{{ __('Gunakan SV mesin') }}<x-text-button type="button"
                                        class="ml-2" x-data="" x-on:click="$dispatch('open-modal', 'use_m_log_sv-help')"><i
                                            class="far fa-question-circle"></i></x-text-button>
                                </x-toggle>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
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
    
                        <div>SVP</div>    
                        <div>01</div>
                        <div>02</div>
                        <div>03</div>
                        <div>04</div>
                        <div>05</div>
                        <div>06</div>
                        <div>07</div>
                        <div>08</div>
                    </div>               
                </div>
                <div class="flex gap-x-2">
                    <div></div>
                    <div class="grow"></div>
                    <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'adj-save')">{{ __('Simpan saja') }}</x-secondary-button>
                    <x-primary-button type="button" x-on:click="$dispatch('open-modal', 'adj-send')">{{ __('Kirim') }}</x-primary-button>
                </div>    
            </div>
            @else
            <div class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="fa fa-tablet relative"><i
                            class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                </div>
                <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih mesin dan posisi') }}
                </div>
            </div>
            @endif                
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
        </div>
    </div>
</div>