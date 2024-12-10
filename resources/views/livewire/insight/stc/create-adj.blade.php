<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\InsStc;
use App\InsStcPush;
use App\Models\InsStcMachine;
use App\Models\InsStcDSum;
use App\Models\InsStcDLog;
use App\Models\InsStcMLog;
use App\Models\InsStcAdj;

new class extends Component {

    public int $machine_id = 0;
    public string $position = '';

    public array $m_log = [];

    public array $d_sum = [];
    public array $d_logs = [];
    public array $hb_values = [];

    public array $sv_values = [];
    public array $svp_values = [];

    public int $formula_id = 412;
    public bool $use_m_log_sv = false;

    public string $remarks = '';

    public function rules()
    {
        return [
            'machine_id'            => ['required', 'integer', 'exists:ins_stc_machines,id'],
            'position'              => ['required', 'in:upper,lower'],
            'use_m_log_sv'          => ['required', 'boolean'],
            'd_sum.id'              => ['required', 'integer', 'exists:ins_stc_d_sums,id'],
            'm_log.id'              => ['required_if:use_m_log_sv,true', 'integer', 'exists:ins_stc_m_logs,id'],
            'formula_id'            => ['required', 'integer'],
            'svp_values.*.absolute' => ['required', 'integer', 'min:20', 'max:90'],
            'remarks'               => ['nullable', 'string']
        ];
    }

    private function saveAdj()
    {
        $this->validate();
        InsStcAdj::create([
            'user_id'               => Auth::user()->id,
            'ins_stc_machine_id'    => $this->machine_id,
            'position'              => $this->position,
            'use_m_log_sv'          => $this->use_m_log_sv,
            'ins_stc_d_sum_id'      => $this->d_sum['id'],
            'ins_stc_m_log_id'      => $this->m_log ? $this->m_log['id'] : null,
            'formula_id'            => $this->formula_id,
            'sv_p_1'                => $this->svp_values[0]['absolute'],
            'sv_p_2'                => $this->svp_values[1]['absolute'],
            'sv_p_3'                => $this->svp_values[2]['absolute'],
            'sv_p_4'                => $this->svp_values[3]['absolute'],
            'sv_p_5'                => $this->svp_values[4]['absolute'],
            'sv_p_6'                => $this->svp_values[5]['absolute'],
            'sv_p_7'                => $this->svp_values[6]['absolute'],
            'sv_p_8'                => $this->svp_values[7]['absolute'],
            'remarks'               => $this->remarks,
        ]);
        $this->customReset();
    }

    #[On('d_sum-created')]
    public function dSumCreated($dSum)
    {
        $this->machine_id   = $dSum['ins_stc_machine_id'];
        $this->position     = $dSum['position'];
    }

    public function with(): array
    {
        $this->m_log = [];
        $this->d_sum = [];
        $this->d_logs = [];
        $this->hb_values = [];
        $this->sv_values = [];
        $this->svp_values = [];

        if ($this->machine_id && $this->position) {

            $m_log = InsStcMLog::where('ins_stc_machine_id', $this->machine_id)
                ->where('position', $this->position)
                ->latest()
                ->first();

            if ($m_log) {
                $this->m_log = $m_log->toArray();
            }

            $d_sum = InsStcDSum::where('ins_stc_machine_id', $this->machine_id)
                ->where('position', $this->position)
                ->orderBy('created_at', 'desc')
                ->first();
            $d_logs = InsStcDLog::where('ins_stc_d_sum_id', $d_sum->id ?? 0)->get();

            if ($d_sum && $d_logs) {
                $this->d_sum = $d_sum->toArray();
                $this->d_logs = $d_logs->toArray();

                $medians = InsStc::getMediansfromDLogs($this->d_logs);
                for ($i = 1; $i <= 8; $i++) {
                    $key = "section_$i";
                    $this->hb_values[] = $medians[$key];
                }
            }

            if ($this->m_log && $this->use_m_log_sv) {
                for ($i = 1; $i <= 8; $i++) {
                    $key = "sv_r_$i";
                    $this->sv_values[] = $m_log[$key];
                }
            } else if ($this->d_sum && !$this->use_m_log_sv) {
                $this->sv_values = json_decode($this->d_sum['sv_temps'], true);
            }

            if ($this->formula_id && $this->hb_values && $this->sv_values) {
                $this->svp_values = InsStc::calculateSVP($this->hb_values, $this->sv_values, $this->formula_id);

            } else {
                $this->svp_values = [];
            }

        }

        return [
            'machines' => InsStcMachine::orderBy('line')->get(),
        ];
    }

    public function customReset()
    {
        $this->reset(['machine_id', 'position']);
    }

    public function send()
    {
        $machine   = InsStcMachine::find($this->machine_id);
        $insStcPush = new InsStcPush();

        try {
            $response = $insStcPush->send('section_svp', $machine->ip_address, $this->position, [
                $this->svp_values[0]['absolute'],
                $this->svp_values[1]['absolute'],
                $this->svp_values[2]['absolute'],
                $this->svp_values[3]['absolute'],
                $this->svp_values[4]['absolute'],
                $this->svp_values[5]['absolute'],
                $this->svp_values[6]['absolute'],
                $this->svp_values[7]['absolute']
            ]);

            $this->saveAdj();
            $this->js('notyfSuccess("' . __('SVP terkirim ke HMI') . '")');

        } catch (\InvalidArgumentException $e) {
            // Handle validation errors
            $this->js('notyfError("Invalid data: ' . $e->getMessage() . '")');
        } catch (Exception $e) {
            // Handle connection or other errors
            $this->js('notyfError("' . $e->getMessage() . '")');
        }
        
    }


    
    public function exception($e, $stopPropagation) {

        if($e instanceof Illuminate\Validation\ValidationException) {
            $this->js('$dispatch("open-modal", "adj-error")');
            $stopPropagation();
        } else {
            $this->js('notyfError("' . $e->getMessage() . '")');
            $stopPropagation();
        }
    }

};
?>

<div>
    <h1 class="grow text-2xl text-neutral-900 dark:text-neutral-100 px-8">{{ __('Penyetelan') }}</h1>
    <div wire:key="modals">
        <x-modal name="d_sum-show" maxWidth="xl">
            <livewire:insight.stc.summary.d-sum-show />
        </x-modal>
        <x-modal name="use_m_log_sv-help">
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Referensi SV') }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')"><i
                            class="fa fa-times"></i></x-text-button>
                </div>
                <div class="grid gap-y-6 mt-6 text-sm text-neutral-600 dark:text-neutral-400">
                    <div>
                        {{ __('Ada dua rentetan nilai SV dan hanya satu rentetan SV yang diambil untuk perhitungan SVP (SV Prediksi).') }}
                    </div>
                    <div>
                        <span class="font-bold">{{ __('SV manual') . ': ' }}</span>{{ __('SV didapat dari pencatatan manual yang dilakukan oleh pekerja ketika mengunggah hasil catatan alat ukur (HOBO).') }}
                    </div>
                    <div>
                        <span class="font-bold">{{ __('SV mesin') . ': ' }}</span>{{ __('SV didapat langsung dari mesin sehingga pilihan ini lebih akurat dan paling ideal.') }}
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Paham') }}
                    </x-primary-button>
                </div>
            </div>
        </x-modal>
        <x-modal name="adj-error">
            <div class="text-center pt-6">
                <i class="fa fa-exclamation-triangle text-4xl "></i>
                <h2 class="mt-3 text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Data tidak sah') }}
                </h2>
            </div>
            <div class="p-6 text-sm text-neutral-600 dark:text-neutral-400">
                @if ($errors->any())
                    <ul class="mt-3 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-6 flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Oke') }}
                    </x-primary-button>
                </div>
            </div>
        </x-modal>
        <x-modal name="adj-send">
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Kirim ke HMI') }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')"><i
                            class="fa fa-times"></i></x-text-button>
                </div>
                <div class="py-8">
                    <div class="text-center">
                        <i class="fa fa-tablet relative text-5xl text-neutral-300 dark:text-neutral-700"><i
                                class="fa fa-arrow-right absolute bottom-0 -left-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                        <div class="px-6 mt-6 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Angka SV prediksi (SVP) akan dikirimkan ke HMI.') }}
                        </div>
                    </div>
                </div>
                <div class="grid gap-y-6 text-sm text-neutral-600 dark:text-neutral-40">
                    <div>
                        <label for="adj-remarks"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Keterangan') }}</label>
                        <x-text-input id="adj-remarks" wire:model="remarks" type="text" />
                        @error('remarks')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')" wire:click="send">
                        {{ __('Kirim SVP ke HMI') }}
                    </x-primary-button>
                </div>
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
                            <option value="0"></option>
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
            @if ($machine_id && $position)
                <div class="grid grid-cols-1 gap-y-6 mt-6">
                    <div>
                        <label class="flex gap-x-2 px-3 mb-4 uppercase text-xs text-neutral-500">
                            <div>{{ __('Pembacaan alat') }}</div>
                            <div>•</div>
                            @if ($d_sum)
                            <x-text-button type="button" x-on:click="$dispatch('open-modal', 'd_sum-show'); $dispatch('d_sum-show', { id: '{{ $d_sum['id'] ?? 0 }}'})">
                            <div class="flex gap-x-2 uppercase">
                                <div>
                                   {{ Carbon\Carbon::parse($d_sum['created_at'])->diffForHumans() }} 
                                </div>                                
                                <i class="fa fa-arrow-up-right-from-square"></i>
                            </div>
                            </x-text-button>
                            @else
                                <div class="text-red-500"><i class="fa fa-exclamation-circle mr-2"></i>{{ __('Tak ditemukan') }}</div>
                            @endif
                        </label>
                        <div class="grid grid-cols-9 text-center gap-x-3 mb-1 text-xs uppercase font-normal leading-none text-neutral-500">
                            <div>S</div>
                            <div>1</div>
                            <div>2</div>
                            <div>3</div>
                            <div>4</div>
                            <div>5</div>
                            <div>6</div>
                            <div>7</div>
                            <div>8</div>
                        </div>
                        <div class="grid grid-cols-9 text-center gap-x-3">
                            <div>HB</div>
                            <div>{{ $hb_values[0] ?? '-' }}</div>
                            <div>{{ $hb_values[1] ?? '-' }}</div>
                            <div>{{ $hb_values[2] ?? '-' }}</div>
                            <div>{{ $hb_values[3] ?? '-' }}</div>
                            <div>{{ $hb_values[4] ?? '-' }}</div>
                            <div>{{ $hb_values[5] ?? '-' }}</div>
                            <div>{{ $hb_values[6] ?? '-' }}</div>
                            <div>{{ $hb_values[7] ?? '-' }}</div>

                            @if (!$use_m_log_sv)
                            <div>SV</div>
                            <div>{{ $sv_values[0] ?? '-' }}</div>
                            <div>{{ $sv_values[1] ?? '-' }}</div>
                            <div>{{ $sv_values[2] ?? '-' }}</div>
                            <div>{{ $sv_values[3] ?? '-' }}</div>
                            <div>{{ $sv_values[4] ?? '-' }}</div>
                            <div>{{ $sv_values[5] ?? '-' }}</div>
                            <div>{{ $sv_values[6] ?? '-' }}</div>
                            <div>{{ $sv_values[7] ?? '-' }}</div>
                            @endif

                        </div>
                    </div>
                    @if ($use_m_log_sv)
                    <div>
                        <label class="flex gap-x-2 px-3 mb-4 uppercase text-xs text-neutral-500">
                            <div>{{ __('Pembacaan mesin') }}</div>
                            <div>•</div>
                            @if ($m_log)
                                <div>{{ Carbon\Carbon::parse($m_log['created_at'])->diffForHumans() }}</div>
                            @else
                                <div class="text-red-500"><i class="fa fa-exclamation-circle mr-2"></i>{{ __('Tak ditemukan') }}</div>
                            @endif
                        </label>                        
                        <div class="grid grid-cols-9 text-center gap-x-3 mb-1 text-xs uppercase font-normal leading-none text-neutral-500">
                            <div>S</div>
                            <div>1</div>
                            <div>2</div>
                            <div>3</div>
                            <div>4</div>
                            <div>5</div>
                            <div>6</div>
                            <div>7</div>
                            <div>8</div>
                        </div>
                        <div class="grid grid-cols-9 text-center gap-x-3">
                            <div>SV</div>
                            @if ($use_m_log_sv)
                            <div>{{ $sv_values[0] ?? '-' }}</div>
                            <div>{{ $sv_values[1] ?? '-' }}</div>
                            <div>{{ $sv_values[2] ?? '-' }}</div>
                            <div>{{ $sv_values[3] ?? '-' }}</div>
                            <div>{{ $sv_values[4] ?? '-' }}</div>
                            <div>{{ $sv_values[5] ?? '-' }}</div>
                            <div>{{ $sv_values[6] ?? '-' }}</div>
                            <div>{{ $sv_values[7] ?? '-' }}</div>
                            @endif
                        </div>                        
                    </div>
                    @endif
                    <div>
                        <div class="grid grid-cols-2 gap-x-3">
                            <div>
                                <label for="adj-formula_id"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Formula') }}</label>
                                <x-select class="w-full" id="adj-formula_id" wire:model.live="formula_id">
                                    <option value="0"></option>
                                    <option value="411">{{ __('v4.1.1 - Diff aggresive') }}</option>
                                    <option value="412">{{ __('v4.1.2 - Diff delicate') }}</option>
                                    <option value="421">{{ __('v4.2.1 - Ratio') }}</option>
                                </x-select>
                                @error('formula_id')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                            <div>
                                <label for="adj-use_m_log_sv"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Referensi SV') }}</label>
                                <div class="p-2">
                                    <x-toggle name="use_m_log_sv" wire:model.live="use_m_log_sv"
                                        :checked="$use_m_log_sv ? true : false">{{ __('Gunakan SV mesin') }}<x-text-button type="button"
                                            class="ml-2" x-data=""
                                            x-on:click="$dispatch('open-modal', 'use_m_log_sv-help')"><i
                                                class="far fa-question-circle"></i></x-text-button>
                                    </x-toggle>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="grid grid-cols-9 text-center gap-x-3 mb-1 text-xs uppercase font-normal leading-none text-neutral-500">
                            <div>S</div>
                            <div>1</div>
                            <div>2</div>
                            <div>3</div>
                            <div>4</div>
                            <div>5</div>
                            <div>6</div>
                            <div>7</div>
                            <div>8</div>
                        </div>
                        <div class="grid grid-cols-9 text-center gap-x-3">
                            <div>SVP</div>
                            <div>{{ $this->svp_values[0]['absolute'] ?? '-' }}</div>
                            <div>{{ $this->svp_values[1]['absolute'] ?? '-' }}</div>
                            <div>{{ $this->svp_values[2]['absolute'] ?? '-' }}</div>
                            <div>{{ $this->svp_values[3]['absolute'] ?? '-' }}</div>
                            <div>{{ $this->svp_values[4]['absolute'] ?? '-' }}</div>
                            <div>{{ $this->svp_values[5]['absolute'] ?? '-' }}</div>
                            <div>{{ $this->svp_values[6]['absolute'] ?? '-' }}</div>
                            <div>{{ $this->svp_values[7]['absolute'] ?? '-' }}</div>
                        </div>
                        <div class="grid grid-cols-9 text-center gap-x-3 text-xs text-neutral-500">
                            <div>+/-</div>
                            <div>{{ $this->svp_values[0]['relative'] ?? '' }}</div>
                            <div>{{ $this->svp_values[1]['relative'] ?? '' }}</div>
                            <div>{{ $this->svp_values[2]['relative'] ?? '' }}</div>
                            <div>{{ $this->svp_values[3]['relative'] ?? '' }}</div>
                            <div>{{ $this->svp_values[4]['relative'] ?? '' }}</div>
                            <div>{{ $this->svp_values[5]['relative'] ?? '' }}</div>
                            <div>{{ $this->svp_values[6]['relative'] ?? '' }}</div>
                            <div>{{ $this->svp_values[7]['relative'] ?? '' }}</div>
                        </div>
                    </div>
                    <div class="flex gap-x-2">
                        <div>
                            @if ($errors->any())
                                <x-text-button type="button" x-on:click="$dispatch('open-modal', 'adj-error')">
                                    <div class="flex gap-x-2 items-center text-sm text-red-500">
                                        <i class="fa fa-exclamation-circle"></i>
                                        <div>{{ __('Data tidak sah') }}</div>
                                    </div>
                                </x-text-button>
                            @endif
                        </div>
                        <div class="grow"></div>
                        <x-primary-button type="button" :disabled="!$svp_values"
                            x-on:click="$dispatch('open-modal', 'adj-send')">{{ __('Kirim') }}</x-primary-button>
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
