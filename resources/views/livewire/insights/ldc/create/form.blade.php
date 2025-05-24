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
    public $code_last_received;
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

            $this->code = $this->clean($this->code);
            $this->code = preg_replace('/[^a-zA-Z0-9]/', '', $this->code);

            $this->validate();

            $this->code_last_received = $this->code;

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

<div class="px-6 py-8 flex gap-x-6" 
        x-data="{ 
            group_id: $wire.entangle('group_id'),
            material: $wire.entangle('material'),
            area_vn: $wire.entangle('area_vn'), 
            area_ab: $wire.entangle('area_ab'),
            area_qt: $wire.entangle('area_qt'),
            area_qt_string: '',
            code: $wire.entangle('code'),
            code_last_received: $wire.entangle('code_last_received'),
            quota_id: $wire.entangle('quota_id'),
            websocket: null,    
            connectionStatus: 'Disconnected',
            get connectionStatusClass() {
                return {
                    'Connected': 'text-green-600',
                    'Connecting': 'text-yellow-600',
                    'Disconnected': 'text-red-600',
                }[this.connectionStatus];
            },        
            get connectionMessage() {
                return {
                    'Connected': '{{ __("ldc-worker tersambung") }}',
                    'Connecting': '{{ __("Menyambungkan...") }}',
                    'Disconnected': '{{ __("ldc-worker terputus") }}',
                }[this.connectionStatus];
            },
            reconnectInterval: null,
            initWebSocket() {
                this.connectionStatus = 'Connecting';
                this.connectWebSocket();        
                this.websocket.onmessage = (event) => {
                    const data = JSON.parse(event.data);
                    if (this.code_last_received === data.code) {
                        toast('{{ __('Data dari ldc-worker diabaikan (duplikat)') }}');
                    } else {
                        toast('{{ __('Data dari ldc-worker diterima') }}');
                        this.code = data.code;
                        this.area_ab = data.area_ab;
                        this.area_qt = data.area_qt;
                        this.code_last_received = data.code;
                    }
                };
                this.websocket.onopen = () => {
                    this.connectionStatus = 'Connected';
                    console.log('Terhubung dengan ldc-worker, websocket.readyState: ' + this.websocket.readyState);
                };

                this.websocket.onclose = () => {
                    this.connectionStatus = 'Disconnected';
                    console.log('Terputus dengan ldc-worker, websocket.readyState: ' + this.websocket.readyState);
                    this.scheduleReconnect();
                };

                this.websocket.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    this.websocket.close();
                };
            },
            connectWebSocket() {
                const previousState = this.websocket?.readyState;
                this.websocket = window.AppWebSockets.getOrCreate(
                    'leather-stats',  // Identifier for this specific websocket
                    'ws://127.0.0.1:32998/ws'
                );

                if (previousState !== WebSocket.OPEN && this.websocket.readyState === WebSocket.OPEN) {
                    // Connection was restored/reestablished
                    this.connectionStatus = 'Connected';
                }
            },
            scheduleReconnect() {
                if (!this.reconnectInterval) {
                    this.reconnectInterval = setInterval(() => {
                        if (!this.websocket || this.websocket.readyState === WebSocket.CLOSED) {
                            this.initWebSocket();
                        }
                    }, 10000); // Attempt to reconnect every 5 seconds
                }
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
            }
        }" 
        x-init="initWebSocket()" 
        @wire:navigate.window="if (websocket) { websocket.close(); websocket = null; }"
        x-on:disconnect.window="if (websocket) { websocket.close(); websocket = null; }" 
        x-on:set-form-group.window="group_id = $event.detail.group_id; material = $event.detail.material">
    <form id="ldc-index-form-element" wire:submit="save">
        <div class="grid grid-cols-1 gap-6">
            <div class="flex justify-between text-xs uppercase">
                <div class="text-neutral-500 px-3">{{ Carbon::now()->locale(app()->getLocale())->isoFormat('dddd, D MMMM YYYY, HH:mm') }}</div>
                <div class="bg-neutral-200 dark:bg-neutral-900 rounded-full px-3 py-1 font-bold" :class="connectionStatusClass">
                    <i class="icon-circlemr-2"></i><span x-text="connectionMessage">{{ __('ldc-worker terputus') }}</span>
                </div>
            </div>
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
                                    <i class="icon-circle-help"></i>
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
    <div class="w-60 flex flex-col justify-around grid-rows-2 text-center border border-neutral-200 dark:border-neutral-700 rounded-lg p-6">
        <div>
            <div class="text-sm uppercase">{{ __('Selisih') }}</div>
            <div x-cloak x-show="diff < 6 && area_vn > 0 && area_ab > 0" class="text-green-500"><i class="icon-circle-check me-2"></i><span class="text-xl">{{ __('Di bawah 6%') }}</span></div>
            <div x-cloak x-show="diff > 6 && area_vn > 0 && area_ab > 0" class="text-red-500"><i class="icon-circle-alert me-2"></i><span class="text-xl">{{ __('Di atas 6%') }}</span></div>
            <div x-show="!area_vn || !area_ab"><span class="text-xl">{{ __('Menunggu...') }}</span></div>
        </div>
        <div>
            <div class="text-sm uppercase">{{ __('Defect')}}</div>
            <div x-cloak x-show="defect >= 0 && area_vn > 0 && area_qt > 0"><span class="text-xl">{{ __('OK') }}</span></div>
            <div x-cloak x-show="defect < 0 && area_vn > 0 && area_qt > 0" class="text-red-500"><i class="icon-circle-alert me-2"></i><span class="text-xl">{{ __('Abnormal') }}</span></div>
            <div x-show="!area_vn || !area_qt"><span class="text-xl">{{ __('Menunggu...') }}</span></div>
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