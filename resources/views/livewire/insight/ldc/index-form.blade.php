<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Illuminate\Validation\Rule;

use App\Models\InsLdcGroup;
use App\Models\InsLdcHide;
use Carbon\Carbon;
use App\Caldera;
use Illuminate\Support\Facades\Cache;

new class extends Component {

    public $is_editing = 0;

    public $line;
    public $workdate;
    public $style;
    public $material;

    public $area_vn;
    public $area_ab;
    public $area_qt;

    public $grade;
    public $code;
    public $shift;

    public function rules()
    {
        return [
            'line'      => ['required', 'string', 'min:2', 'max:3', 'regex:/^[a-zA-Z]+[0-9]+$/'],
            'workdate'  => ['required', 'date'],
            'style'     => ['required', 'string', 'min:9', 'max:11'],
            'material'  => ['nullable', 'string', 'max:140'],
            'area_vn'   => ['required', 'numeric', 'gte:0', 'lt:90'],
            'area_ab'   => ['required', 'numeric', 'gte:0', 'lt:90'],
            'area_qt'   => ['required', 'numeric', 'gte:0', 'lt:90'],
            'grade'     => ['nullable', 'integer', 'min:1', 'max:3'],
            'code'      => ['required', 'alpha_num', 'min:7', 'max:10'],
            'shift'     => ['required', 'integer', 'min:1', 'max:3']
        ];
    }

    public function clean($string): string
    {
        return trim(strtoupper($string));
    }

    // #[Renderless]
    // #[On('set-group')]
    // public function setGroup($line, $workdate, $style, $material)
    // {
    //     $this->line     = $line;
    //     $this->workdate = $workdate;
    //     $this->style    = $style;
    //     $this->material = $material;
    // }

    #[On('set-hide')]
    public function setHide($is_editing, $line, $workdate, $style, $material, $area_vn, $area_ab, $area_qt, $grade, $code)
    {
        $this->is_editing = $is_editing;

        $this->line     = $line;
        $this->workdate = $workdate;
        $this->style    = $style;
        $this->material = $material;
        $this->area_vn  = $area_vn;
        $this->area_ab  = $area_ab;
        $this->area_qt  = $area_qt;
        $this->grade    = $grade;
        $this->code     = $code;

        $this->resetValidation();
    }

    public function save()
    {
        $this->line     = $this->clean($this->line);
        $this->style    = $this->clean($this->style);
        $this->material = $this->clean($this->material);
        $this->code     = $this->clean($this->code);

        $this->code     = preg_replace('/[^a-zA-Z0-9]/', '', $this->code);

        if (!$this->line || !$this->workdate || !$this->style) {
            $this->js('notyfError("' . __('Info grup tidak sah') . '")');
        }

        $validated = $this->validate();

        $group = InsLdcGroup::firstOrCreate([
            'line'      => $this->line,
            'workdate'  => $this->workdate,
            'style'     => $this->style,
            'material'  => $this->material,
        ]);
        $group->updated_at = now();
        $group->save();

        $styles = Cache::get('styles', collect([
                    ['name' => $this->style, 'updated_at' => now() ]
                ]));
        $styles = Caldera::manageCollection($styles, $this->style);
        Cache::put('styles', $styles);

        $lines = Cache::get('lines', collect([
                    ['name' => $this->line, 'updated_at' => now() ]
                ]));
        $lines = Caldera::manageCollection($lines, $this->line);
        Cache::put('lines', $lines);

        if($this->material) {
            $materials = Cache::get('materials', collect([
                    ['name' => $this->material, 'updated_at' => now() ]
                    ]));
            $materials = Caldera::manageCollection($materials, $this->material);
            Cache::put('materials', $materials);
        }        

        $this->js('document.getElementById("ldc-index-groups").scrollLeft = 0;');

        $hide = InsLdcHide::updateOrCreate(
            [ 
                'code' => $this->code 
            ], 
            [
                'ins_ldc_group_id' => $group->id,
                'area_vn'       => $this->area_vn,
                'area_ab'       => $this->area_ab,
                'area_qt'       => $this->area_qt,
                'grade'         => $this->grade ? $this->grade : null,
                'shift'         => $this->shift,
                'user_id'       => Auth::user()->id
            ]);

        $this->js('$dispatch("close")');
        $this->js('notyfSuccess("' . __('Kulit disimpan') . '")');
        $this->dispatch('hide-saved');
        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['is_editing', 'line', 'workdate', 'style', 'material', 'area_vn', 'area_ab', 'area_qt', 'grade', 'code']);
    }

    public function delete()
    {
        if($this->code) {
            $hide = InsLdcHide::where('code', $this->code);
            if ($hide) {
                $hide->delete();
                $this->js('notyfSuccess("' . __('Kulit dihapus') . '")');
                $this->dispatch('hide-saved');
                $this->customReset();
            }
        }
    }

};

?>

<div x-data="{ 
    line: $wire.entangle('line'), 
    workdate: $wire.entangle('workdate'), 
    style: $wire.entangle('style'), 
    material: $wire.entangle('material'), 
    area_vn: $wire.entangle('area_vn'), 
    area_ab: $wire.entangle('area_ab'),
    area_qt: $wire.entangle('area_qt'),
    area_qt_string: '',
    code: $wire.entangle('code'),
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
}" x-on:set-group.window="line = $event.detail.line; workdate = $event.detail.workdate; style = $event.detail.style; material = $event.detail.material" class="px-6 py-8 flex gap-x-6">
    <form id="ldc-index-form-element" wire:submit="save">
        <div class="grid grid-cols-3 gap-3">
            <div>
                <div>
                    <label for="hide-area_vn"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('VN') }}</label>
                    <x-text-input-suffix suffix="SF" id="hide-area_vn" x-model="area_vn" type="number" step=".01" autocomplete="off" />
                    @error('area_vn')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="mt-3">
                    <label for="hide-grade"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Grade') }}</label>
                    <x-text-input id="hide-grade" wire:model="grade" type="number" list="hide-grades" step="1" />
                    @error('grade')
                        <x-input-error messages="{{ $message }}" class="px-3" />
                    @enderror
                    <datalist id="hide-grades">
                        <option value="1"></option>
                        <option value="2"></option>
                        <option value="3"></option>
                    </datalist>
                </div>
            </div>
            <div class="col-span-2">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <div class="flex">
                            <label for="hide-area_ab"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('AB')  }}</label>
                            <div class="text-neutral-500 text-xs pr-3">|</div>
                            <div class="text-neutral-500 text-xs"><span class="uppercase">{{ __('Selisih') .': ' }}</span><span x-text="diff.toFixed(1) + '%'"></span></div>
                        </div>
                        <x-text-input-suffix suffix="SF" id="hide-area_ab" x-model="area_ab" type="number" step=".01" autocomplete="off" />
                        @error('area_ab')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div>
                        <div class="flex">
                            <label for="hide-area_qt"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('QT') }}</label>
                            <div class="text-neutral-500 text-xs pr-3">|</div>
                            <div class="text-neutral-500 text-xs"><span class="uppercase">{{ __('Defect') . ': ' }}</span><span x-text="defect.toFixed(1) + '%'"></span></div>
                        </div>
                        <x-text-input-suffix suffix="SF" id="hide-area_qt" x-model="area_qt" type="number" step=".01" autocomplete="off" x-on:keydown="if ($event.key === '+' || $event.key === '-') { $dispatch('open-spotlight', 'calculate-qt'); console.log(area_qt); area_qt_string = area_qt + $event.key }"  />
                        @error('area_qt')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div>
                        <label for="hide-code"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Barcode') }}</label>
                        <x-text-input id="hide-code" x-model="code" x-ref="hidecode" type="text" autocomplete="off" :disabled="$is_editing ? 'disabled' : false" />
                        <div class="flex w-full justify-between items-center text-neutral-500 px-3 mt-2 text-xs">
                            <x-text-button @click="code = 'XA'; $nextTick(() => setCursorToEnd())" type="button">XA</x-text-button>
                            <x-text-button @click="code = 'XB'; $nextTick(() => setCursorToEnd())" type="button">XB</x-text-button>
                            <x-text-button @click="code = 'XC'; $nextTick(() => setCursorToEnd())" type="button">XC</x-text-button>
                            <x-text-button @click="code = 'XD'; $nextTick(() => setCursorToEnd())" type="button">XD</x-text-button>
                        </div>
                        @error('code')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div>
                        <label for="hide-shift"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Shift') }}</label>
                        <x-select id="hide-shift" wire:model="shift">
                            <option value=""></option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </x-select>
                        @error('shift')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="flex justify-between mt-6">
            <div>
                <div class="text-sm">{{ __('Material') .': ' }}<span x-text="material ? material : 'Tak ditentukan'"></span></div>
                <div class="text-xs text-neutral-500">{{ __('Waktu server') .': ' . Carbon::now()->locale('id')->isoFormat('dddd, D MMMM YYYY, HH:mm'); }}</div>
            </div>
            <div class="flex gap-x-3">
                <x-text-button type="button" class="uppercase text-xs text-red-500 {{ $is_editing ? '' : 'hidden' }}" wire:click="delete"
                wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}">
                {{ __('Hapus') }}
            </x-text-button>
                <x-primary-button type="submit">{{ __('Simpan') }}</x-primary-button>
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
            <x-text-input-transparent type="text" x-model="area_qt_string" x-on:keyup.enter="area_qt = area_qt_eval; window.dispatchEvent(escKey); $refs.hidecode.focus()"></x-text-input-transparent>
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
          } else if (nextInput) {
            nextInput.focus();
          }
        } 
      });
    });
  }
</script>
@endscript