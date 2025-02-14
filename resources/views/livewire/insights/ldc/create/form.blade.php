<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\Models\InsLdcGroup;
use App\Models\InsLdcHide;
use App\Models\InsLdcMachine;
use Carbon\Carbon;
use App\Caldera;
use Illuminate\Support\Facades\Cache;

new class extends Component {

    public $is_editing = 0;

    public $group_id;
    public $material;

    public $area_vn;
    public $area_ab;
    public $area_qt;

    public $grade;
    public $quota_id;
    public $code;
    public $shift;

    public $ins_ldc_machines;

    public function mount()
    {
        $this->ins_ldc_machines = InsLdcMachine::orderBy('code')->get();
    }

    public function rules()
    {
        $codes = $this->ins_ldc_machines->pluck('code')->implode(',');
        return [
            'group_id'  => ['required', 'exists:ins_ldc_groups,id'],
            'area_vn'   => ['required', 'numeric', 'gte:0', 'lt:90'],
            'area_ab'   => ['required', 'numeric', 'gte:0', 'lt:90'],
            'area_qt'   => ['required', 'numeric', 'gte:0', 'lt:90'],
            'grade'     => ['nullable', 'integer', 'min:1', 'max:5'],
            'quota_id'  => ['nullable', 'exists:ins_ldc_quotas,id'],
            'code'      => ['required', 'alpha_num', 'min:7', 'max:10', "starts_with:$codes"],
            'shift'     => ['required', 'integer', 'min:1', 'max:3']
        ];
    }

    public function clean($string): string
    {
        return trim(strtoupper($string));
    }

    #[On('set-hide')]
    public function setHide($is_editing, $group_id, $material, $area_vn, $area_ab, $area_qt, $grade, $quota_id, $code)
    {
        $this->is_editing = $is_editing;

        $this->group_id = $group_id;
        $this->material = $material;
        $this->area_vn  = $area_vn;
        $this->area_ab  = $area_ab;
        $this->area_qt  = $area_qt;
        $this->grade    = $grade;
        $this->quota_id = $quota_id;
        $this->code     = $code;

        $this->resetValidation();
    }

    public function save()
    {
        if( !Auth::user() ) {
            $this->js('toast("' . __('Kamu belum masuk') . '", { type: "danger" })');
        } else {

            $this->code     = $this->clean($this->code);
            $this->code     = preg_replace('/[^a-zA-Z0-9]/', '', $this->code);

            $validated = $this->validate();

            $group = InsLdcGroup::find($this->group_id);

            $styles = Cache::get('styles', collect([
                        ['name' => $group->style, 'updated_at' => now() ]
                    ]));
            $styles = Caldera::manageCollection($styles, $group->style);
            Cache::put('styles', $styles);

            $lines = Cache::get('lines', collect([
                        ['name' => $group->line, 'updated_at' => now() ]
                    ]));
            $lines = Caldera::manageCollection($lines, $group->line);
            Cache::put('lines', $lines);

            $materials = Cache::get('materials', collect([
                    ['name' => $group->material, 'updated_at' => now() ]
                    ]));
            $materials = Caldera::manageCollection($materials, $group->material, 50);
            Cache::put('materials', $materials);     

            // $this->js('document.getElementById("ldc-index-groups").scrollLeft = 0;');

            $hide = InsLdcHide::updateOrCreate(
                [ 
                    'code' => $this->code 
                ], 
                [
                    'ins_ldc_group_id' => $group->id,
                    'ins_ldc_quota_id' => $this->quota_id ? $this->quota_id : null,
                    'area_vn'       => $this->area_vn,
                    'area_ab'       => $this->area_ab,
                    'area_qt'       => $this->area_qt,
                    'grade'         => $this->grade ? $this->grade : null,
                    'shift'         => $this->shift,
                    'user_id'       => Auth::user()->id
                ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Kulit disimpan') . '", { type: "success" })');
            $this->dispatch('hide-saved');
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset(['is_editing', 'group_id', 'area_vn', 'area_ab', 'area_qt', 'grade', 'quota_id', 'code']);
    }

    public function delete()
    {
        if( !Auth::user() ) {
            $this->js('toast("' . __('Kamu belum masuk') . '", { type: "danger" })');
        } else {

            if($this->code) {
                $hide = InsLdcHide::where('code', $this->code);
                if ($hide) {
                    $hide->delete();
                    $this->js('toast("' . __('Kulit dihapus') . '", { type: "success" })');
                    $this->dispatch('hide-saved');
                    $this->customReset();
                }
            }
        }
    }

};

?>

<div x-data="{ 
    group_id: $wire.entangle('group_id'),
    material: $wire.entangle('material'),
    area_vn: $wire.entangle('area_vn'), 
    area_ab: $wire.entangle('area_ab'),
    area_qt: $wire.entangle('area_qt'),
    area_qt_string: '',
    code: $wire.entangle('code'),
    quota_id: $wire.entangle('quota_id'),
    websocket: null,
    initWebSocket() {
        this.websocket = new WebSocket('ws://127.0.0.1:32999/ws');
        this.websocket.onopen = () => {
            console.log('WebSocket connected');
            toast('{{ __('Terhubung dengan ldc-worker') }}', { type: 'success' });
        };
        
        this.websocket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            console.log('WebSocket received:', data);
            
            // Extract and set the values from the data array
            if (data.data) {
                // Set the code (index 2)
                this.code = data.data[2];
                
                // Set area_ab (index 33)
                this.area_ab = data.data[33];
                
                // Optional: Focus on the next logical input
                this.$nextTick(() => {
                    // Assuming you want to focus on area_vn after populating
                    document.getElementById('hide-area_vn')?.focus();
                });
            }
        };
        
        this.websocket.onclose = () => {
            console.log('WebSocket disconnected. Attempting to reconnect...');
            toast('{{ __('Terputus dengan ldc-worker, mencoba ulang koneksi...') }}', { type: 'success' });
            setTimeout(() => this.initWebSocket(), 3000);
        };
    },
    get diff() {
        let area_vn = parseFloat(this.area_vn)
        let area_ab = parseFloat(this.area_ab)
        return ((area_vn > 0 && area_ab > 0) ? ((area_vn - area_ab) / area_vn * 100) : 0)
    },
    get defect() {
        let area_vn = parseFloat(this.area_vn)
        let area_qt = parseFloat(this.area_qt)
        return((area_vn > 0 && area_qt > 0) ? ((area_vn - area_qt) / area_vn * 100) : 0)
    },
    get area_qt_eval() {
        try {
            let result = eval(this.area_qt_string.replace(/[^\d\.\+\-\*\/\(\)]/g, ''));
            return !isNaN(result) ? result.toFixed(2) : '0.00';
        } catch {
            return '0.00';
        }
    },
    setCursorToEnd() { 
        this.$refs.hidecode.focus(); 
        this.$refs.hidecode.setSelectionRange(this.code.length, this.code.length); 
    },
}" x-init="initWebSocket()" x-on:set-form-group.window="group_id = $event.detail.group_id; material = $event.detail.material" class="px-6 py-8 flex gap-x-6">
    <form id="ldc-index-form-element" wire:submit="save">
        <div class="grid grid-cols-1 gap-6">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label for="hide-area_vn"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('VN') }}</label>
                    <x-text-input-suffix suffix="SF" id="hide-area_vn" x-model="area_vn" type="number" step=".01" autocomplete="off" />
                </div>
                <div>
                    <div class="flex">
                        <label for="hide-area_ab"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('AB')  }}</label>
                        <div class="text-neutral-500 text-xs pr-3">|</div>
                        <div class="text-neutral-500 text-xs"><span class="uppercase">{{ __('Selisih') .': ' }}</span><span x-text="diff.toFixed(1) + '%'"></span></div>
                    </div>
                    <x-text-input-suffix suffix="SF" id="hide-area_ab" x-model="area_ab" type="number" step=".01" autocomplete="off" />
                </div>
                <div>
                    <div class="flex">
                        <label for="hide-area_qt"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('QT') }}</label>
                        <div class="text-neutral-500 text-xs pr-3">|</div>
                        <div class="text-neutral-500 text-xs"><span class="uppercase">{{ __('Defect') . ': ' }}</span><span x-text="defect.toFixed(1) + '%'"></span></div>
                    </div>
                    <x-text-input-suffix suffix="SF" id="hide-area_qt" x-model="area_qt" type="number" step=".01" autocomplete="off" x-on:keydown="if ($event.key === '+' || $event.key === '-') { $dispatch('open-spotlight', 'calculate-qt'); console.log(area_qt); area_qt_string = area_qt + $event.key }" />
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label for="hide-grade"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Grade') }}</label>
                    <x-text-input id="hide-grade" wire:model="grade" type="number" list="hide-grades" step="1" />
                    <datalist id="hide-grades">
                        <option value="1"></option>
                        <option value="2"></option>
                        <option value="3"></option>
                        <option value="4"></option>
                        <option value="5"></option>
                    </datalist>
                </div>
                <div>
                    <label for="hide-quota_id"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Jatah') }}</label>
                        <div class="btn-group w-full">
                            <x-secondary-button type="button" class="grow py-3">{{ __('Tetapkan...')}}</x-secondary-button>
                            <div class="flex items-center text-xs -ml-px px-4 py-2 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-500 rounded-md">
                                <div class="text-neutral-500">
                                    <i class="fa fa-fw fa-regular fa-circle-question"></i>
                                </div>
                            </div>
                        </div>
                    {{-- <x-text-input id="hide-quota_id" wire:model="quota_id" type="number" list="hide-quota_ids" step="1" placeholder="{{ __('Lewati') }}" disabled /> --}}
                </div>
                <div>
                    <label for="hide-code"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Barcode') }}</label>
                    <x-text-input id="hide-code" x-model="code" x-ref="hidecode" type="text" autocomplete="off" :disabled="$is_editing ? 'disabled' : false" />
                    <div class="flex w-full justify-between items-center text-neutral-500 px-3 mt-2 text-xs">
                        @foreach($ins_ldc_machines as $ins_ldc_machine)
                            <x-text-button x-on:click="code = '{{ $ins_ldc_machine->code }}'; $nextTick(() => setCursorToEnd())" type="button">{{ $ins_ldc_machine->code }}</x-text-button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @if ($errors->any())
            <div class="mb-3">
                <x-input-error :messages="$errors->first()" />
            </div>
        @endif
        <div class="flex justify-between items-end">
            <div class="flex gap-3">
                <div>
                    <label for="hide-shift"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Shift') }}</label>
                    <x-select class="w-full" id="hide-shift" wire:model="shift">
                        <option value=""></option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </x-select>
                </div>
                <div>
                    <label for="hide-material"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Material') }}</label>
                    <div x-text="material ? material : '{{ __('Belum ada grup yang dipilih') }}'" class="px-3 py-2 text-sm uppercase"></div>
                </div>
            </div>
            <div class="flex gap-x-6">
                <x-text-button type="button" class="uppercase text-xs text-red-500 {{ $is_editing ? '' : 'hidden' }}" wire:click="delete"
                    wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}">
                    {{ __('Hapus') }}
                </x-text-button>
                <div>
                    <x-primary-button type="submit">{{ __('Simpan') }}</x-primary-button>
                </div>                
            </div>
        </div>
    </form>
    <x-spotlight name="calculate-qt" focusable maxWidth="sm">
        <div class="w-full flex flex-col gap-y-10 pb-10">
            <header>
                <h2 class="text-xl text-center font-medium">
                    {{ __('Operasi matematika untuk QT')}}
                </h2>
            </header>
            <div x-text="area_qt_eval" class="text-center font-bold text-7xl">
                0.00
            </div>
            <x-text-input-line type="text" x-model="area_qt_string" x-on:keyup.enter="area_qt = area_qt_eval; window.dispatchEvent(escKey); $refs.hidecode.focus()"></x-text-input-line>
        </div>
    </x-spotlight>
    <div class="w-60 grid grid-cols-1 grid-rows-2 gap-6 text-center border border-neutral-200 dark:border-neutral-700 rounded-lg p-6">
        <div>
            <div class="text-sm uppercase">{{ __('Selisih') }}</div>
            <div x-cloak x-show="diff < 6 && area_vn > 0 && area_ab > 0" class="text-green-500"><i class="fa fa-check-circle me-2"></i><span class="text-xl">{{ __('Di bawah 6%') }}</span></div>
            <div x-cloak x-show="diff > 6 && area_vn > 0 && area_ab > 0" class="text-red-500"><i class="fa fa-exclamation-circle me-2"></i><span class="text-xl">{{ __('Di atas 6%') }}</span></div>
            <div x-show="!area_vn || !area_ab"><span class="text-xl">{{ __('Menunggu...') }}</span></div>
        </div>
        <div>
            <div class="text-sm uppercase">{{ __('Defect')}}</div>
            <div x-cloak x-show="defect >= 0 && area_vn > 0 && area_qt > 0"><span class="text-xl">{{ __('OK') }}</span></div>
            <div x-cloak x-show="defect < 0 && area_vn > 0 && area_qt > 0" class="text-red-500"><i class="fa fa-exclamation-circle me-2"></i><span class="text-xl">{{ __('Abnormal') }}</span></div>
            <div x-show="!area_vn || !area_qt"><span class="text-xl">{{ __('Menunggu...') }}</span></div>
        </div>
        <div class="text-xs text-neutral-500 text-center">
            <div>{{ Carbon::now()->locale(app()->getLocale())->isoFormat('dddd, D MMM YYYY') }}</div>
            <div>{{ Carbon::now()->locale(app()->getLocale())->isoFormat('HH:mm') }}</div>
        </div>
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>

@script
<script>
  const form = document.getElementById('ldc-index-form-element');
  
  if (form) {
    const inputs = form.querySelectorAll('input');
    const hideCodeInput = form.querySelector('#hide-code');
    const hideAreaVnInput = form.querySelector('#hide-area_vn');
    const hideGradeInput = form.querySelector('#hide-grade');
    const hideAreaAbInput = form.querySelector('#hide-area_ab');
    const hideAreaQtInput = form.querySelector('#hide-area_qt');
    const hideMachineInput = form.querySelector('#hide-machine');
    const submitButton = form.querySelector('button[type="submit"]');

    inputs.forEach((input, index) => {
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          const nextInput = inputs[index + 1];
          
          // Check if the current input is "hide-code"
          if (input === hideCodeInput) {
            if (submitButton) {
                submitButton.focus();
                $wire.save();
            }
          } else if (input === hideAreaVnInput) {
            hideGradeInput.focus();
          } else if (input === hideGradeInput) {
            hideAreaAbInput.focus();
          } else if (input === hideAreaQtInput) {
            hideCodeInput.focus();
          } else if (nextInput) {
            nextInput.focus();
          }
        } 
      });
    });
  }
</script>
@endscript