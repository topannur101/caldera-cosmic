<?php

use Livewire\Volt\Component;

use App\Caldera;
use App\Models\InsLdcHide;
use App\Models\InsLdcGroup;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    public int $id;

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
            'grade'     => ['nullable', 'integer', 'min:1', 'max:5'],
            // 'code'      => ['required', 'alpha_num', 'min:7', 'max:10'],
            'shift'     => ['required', 'integer', 'min:1', 'max:3']
        ];
    }

    public function clean($string): string
    {
        return trim(strtoupper($string));
    }

    #[On('hide-edit')]
    public function loadHide(int $id)
    {
        $hide = InsLdcHide::find($id);
        if ($hide) {
            $this->id       = $hide->id;
            $this->line     = $hide->ins_ldc_group->line;
            $this->workdate = $hide->ins_ldc_group->workdate;
            $this->style    = $hide->ins_ldc_group->style;
            $this->material = $hide->ins_ldc_group->material;
            $this->area_vn  = $hide->area_vn;
            $this->area_ab  = $hide->area_ab;
            $this->area_qt  = $hide->area_qt;
            $this->grade    = $hide->grade;
            $this->code     = $hide->code;
            $this->shift    = $hide->shift;
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        $hide = InsLdcHide::find($this->id);
        $validated = $this->validate();

        if ($hide) {
            Gate::authorize('manage', $hide);

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
                $materials = Caldera::manageCollection($materials, $this->material, 50);
                Cache::put('materials', $materials);
            }        

            // $this->js('document.getElementById("ldc-index-groups").scrollLeft = 0;');

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
                ]);

            $this->js('$dispatch("close")');
            $this->js('notyfSuccess("' . __('Kulit diperbarui') . '")');
            $this->dispatch('updated');
            $this->customReset();
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset(['id', 'line', 'workdate', 'style', 'material', 'area_vn', 'area_ab', 'area_qt', 'grade', 'code', 'shift']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('notyfError("' . __('Tidak ditemukan') . '")');
        $this->dispatch('updated');
    }

    public function with(): array
    {
        $cached_styles = Cache::get('styles', collect([]));
        $cached_lines = Cache::get('lines', collect([]));
        $cached_materials = Cache::get('materials', collect([]));
        
        return [
            'cached_styles' => $cached_styles->sortBy('name')->values(),
            'cached_lines' => $cached_lines->sortBy('name')->values(),
            'cached_materials' => $cached_materials->sortBy('name')->values(),
        ];
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Edit kulit') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mb-6">
            <div class="mt-6">
                <label for="gs-hide-workdate"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('WO') }}</label>
                <x-text-input id="gs-hide-workdate" wire:model="workdate" type="date" />
                @error('workdate')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="gs-hide-style"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Style') }}</label>
                <x-text-input id="gs-hide-style" list="gs-hide-styles" wire:model="style" autocomplete="off"
                    type="text" />
                @error('style')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
                <datalist id="gs-hide-styles">
                    @foreach ($cached_styles as $cached_style)
                        <option value="{{ $cached_style['name'] }}">
                    @endforeach
                </datalist>
            </div>
            <div class="mt-6">
                <label for="gs-hide-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                <x-text-input id="gs-hide-line" list="gs-hide-lines" wire:model="line" type="text"
                    autocomplete="off" />
                @error('line')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
                <datalist id="gs-hide-lines">
                    @foreach ($cached_lines as $cached_line)
                        <option value="{{ $cached_line['name'] }}">
                    @endforeach
                </datalist>
            </div>
            <div class="mt-6">
                <label for="gs-hide-material"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Material') }}</label>
                <x-text-input id="gs-hide-material" list="gs-hide-materials" wire:model="material" type="text"
                    autocomplete="off" />
                @error('material')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
                <datalist id="gs-hide-materials">
                    @foreach ($cached_materials as $cached_material)
                        <option value="{{ $cached_material['name'] }}">
                    @endforeach
                </datalist>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <div>
                    <label for="hide-area_vn"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('VN') }}</label>
                    <x-text-input-suffix suffix="SF" id="hide-area_vn" wire:model="area_vn" type="number" step=".01" autocomplete="off" />
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
                        <option value="4"></option>
                        <option value="5"></option>
                    </datalist>
                </div>
            </div>
            <div class="col-span-2">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="hide-area_ab"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('AB') }}</label>
                        <x-text-input-suffix suffix="SF" id="hide-area_ab" wire:model="area_ab" type="number" step=".01" autocomplete="off" />
                        @error('area_ab')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div>
                        <label for="hide-area_qt"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('QT') }}</label>
                        <x-text-input-suffix suffix="SF" id="hide-area_qt" wire:model="area_qt" type="number" step=".01" autocomplete="off" x-on:keydown="if ($event.key === '+' || $event.key === '-') { $dispatch('open-spotlight', 'calculate-qt'); console.log(area_qt); area_qt_string = area_qt + $event.key }"  />
                        @error('area_qt')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div>
                        <label for="hide-code"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Barcode') }}</label>
                        <x-text-input id="hide-code" wire:model="code" x-ref="hidecode" type="text" autocomplete="off" disabled="disabled" />
                        @error('code')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                    <div>
                        <label for="hide-shift"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Shift') }}</label>
                        <x-select class="w-full" id="hide-shift" wire:model="shift">
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
        @can('manage', InsLdcHide::class)
        <div class="flex justify-end items-end mt-6">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
