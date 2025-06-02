<?php

use Livewire\Volt\Component;
use App\Models\InsRdcMachine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    
    public int $id;
    public int $number;
    public string $name = '';
    public string $type = 'excel';
    public array $excel_cells = [];
    public array $txt_patterns = [];

    // Available fields for configuration
    public array $available_fields = [
        'mcs' => ['label' => 'MCS', 'required' => false],
        'color' => ['label' => 'Color/Warna', 'required' => false],
        's_max' => ['label' => 'S Max', 'required' => false],
        's_min' => ['label' => 'S Min', 'required' => false],
        'tc10' => ['label' => 'TC10', 'required' => false],
        'tc50' => ['label' => 'TC50', 'required' => false],
        'tc90' => ['label' => 'TC90', 'required' => false],
        'eval' => ['label' => 'Evaluation/Status', 'required' => false],
        'code_alt' => ['label' => 'Alternative Code', 'required' => false],
        's_max_low' => ['label' => 'S Max Low Bound', 'required' => false],
        's_max_high' => ['label' => 'S Max High Bound', 'required' => false],
        's_min_low' => ['label' => 'S Min Low Bound', 'required' => false],
        's_min_high' => ['label' => 'S Min High Bound', 'required' => false],
        'tc10_low' => ['label' => 'TC10 Low Bound', 'required' => false],
        'tc10_high' => ['label' => 'TC10 High Bound', 'required' => false],
        'tc50_low' => ['label' => 'TC50 Low Bound', 'required' => false],
        'tc50_high' => ['label' => 'TC50 High Bound', 'required' => false],
        'tc90_low' => ['label' => 'TC90 Low Bound', 'required' => false],
        'tc90_high' => ['label' => 'TC90 High Bound', 'required' => false],
    ];

    // Preset patterns for TXT files
    public array $txt_presets = [
        'ml_value' => ['label' => 'ML Value (S Min)', 'pattern' => '^ML\s+(\d+\.\d+)', 'example' => 'ML 25.3'],
        'mh_value' => ['label' => 'MH Value (S Max)', 'pattern' => '^MH\s+(\d+\.\d+)', 'example' => 'MH 45.7'],
        't10_value' => ['label' => 't10 Value (TC10)', 'pattern' => '^t10\s+(\d+\.\d+)', 'example' => 't10 120.5'],
        't50_value' => ['label' => 't50 Value (TC50)', 'pattern' => '^t50\s+(\d+\.\d+)', 'example' => 't50 300.2'],
        't90_value' => ['label' => 't90 Value (TC90)', 'pattern' => '^t90\s+(\d+\.\d+)', 'example' => 't90 450.8'],
        'orderno' => ['label' => 'Order Number (Code Alt)', 'pattern' => 'Orderno\.:?\s*(\d+)', 'example' => 'Orderno.: 12345'],
        'mcs_compound' => ['label' => 'MCS from Compound Line', 'pattern' => 'OG\/RS\s+(\d{3})', 'example' => 'OG/RS 001'],
        'description' => ['label' => 'Description (Color)', 'pattern' => 'Description:\s*([^$]+)', 'example' => 'Description: BLACK'],
        'status_pass' => ['label' => 'Status Pass', 'pattern' => 'Status:\s*Pass', 'example' => 'Status: Pass'],
        'status_fail' => ['label' => 'Status Fail', 'pattern' => 'Status:\s*Fail', 'example' => 'Status: Fail'],
        'bound_range' => ['label' => 'Bound Range (Low-High)', 'pattern' => '(\d+\.\d+)-(\d+\.\d+)', 'example' => '20.5-30.8'],
        'custom' => ['label' => 'Custom Pattern', 'pattern' => '', 'example' => 'Enter your own regex pattern']
    ];

    public function initializeArrays()
    {
        // Initialize excel_cells for all available fields
        foreach ($this->available_fields as $field => $config) {
            if (!isset($this->excel_cells[$field])) {
                $this->excel_cells[$field] = '';
            }
        }

        // Initialize txt_patterns for all available fields
        foreach ($this->available_fields as $field => $config) {
            if (!isset($this->txt_patterns[$field])) {
                $this->txt_patterns[$field] = [
                    'preset' => '',
                    'pattern' => '',
                    'enabled' => false
                ];
            }
        }
    }

    public function rules()
    {
        $rules = [
            'number' => ['required', 'integer', 'min:1', 'max:99', Rule::unique('ins_rdc_machines', 'number')->ignore($this->id ?? null)],
            'name' => ['required', 'string', 'min:1', 'max:20'],
            'type' => ['required', 'in:excel,txt'],
        ];

        if ($this->type === 'excel') {
            foreach ($this->available_fields as $field => $config) {
                $rules["excel_cells.{$field}"] = ['nullable', 'string', 'regex:/^[A-Z]+[1-9]\d*$/'];
            }
        } else {
            foreach ($this->available_fields as $field => $config) {
                if (isset($this->txt_patterns[$field]) && $this->txt_patterns[$field]['enabled']) {
                    $rules["txt_patterns.{$field}.pattern"] = ['required', 'string', 'min:1'];
                }
            }
        }

        return $rules;
    }

    #[On('machine-edit')]
    public function loadMachine(int $id)
    {
        $machine = InsRdcMachine::find($id);
        if ($machine) {
            $this->id = $machine->id;
            $this->number = $machine->number;
            $this->name = $machine->name;
            $this->type = $machine->type ?? 'excel'; // Default to excel for backward compatibility
            
            $this->initializeArrays();
            
            // Parse existing configuration
            $cells = $machine->cells ?? [];
            
            if ($this->type === 'excel') {
                // Load Excel configuration
                foreach ($cells as $cell) {
                    if (isset($cell['field']) && isset($cell['address'])) {
                        $this->excel_cells[$cell['field']] = $cell['address'];
                    }
                }
            } else {
                // Load TXT configuration
                foreach ($cells as $cell) {
                    if (isset($cell['field']) && isset($cell['pattern'])) {
                        $this->txt_patterns[$cell['field']]['pattern'] = $cell['pattern'];
                        $this->txt_patterns[$cell['field']]['enabled'] = true;
                        
                        // Try to match with presets
                        foreach ($this->txt_presets as $preset_key => $preset) {
                            if ($preset['pattern'] === $cell['pattern']) {
                                $this->txt_patterns[$cell['field']]['preset'] = $preset_key;
                                break;
                            }
                        }
                    }
                }
            }
        
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function updatedTxtPatterns($value, $key)
    {
        // Handle preset selection
        if (str_ends_with($key, '.preset')) {
            $field = str_replace('.preset', '', $key);
            $preset = $this->txt_patterns[$field]['preset'];
            
            if ($preset && isset($this->txt_presets[$preset])) {
                $this->txt_patterns[$field]['pattern'] = $this->txt_presets[$preset]['pattern'];
            }
        }
    }

    public function save()
    {
        $machine = InsRdcMachine::find($this->id);

        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        if($machine) {
            Gate::authorize('manage', $machine);

            // Prepare configuration based on type
            $config = [];
            
            if ($this->type === 'excel') {
                foreach ($this->excel_cells as $field => $address) {
                    if (!empty(trim($address))) {
                        $config[] = [
                            'field' => $field,
                            'address' => strtoupper(trim($address))
                        ];
                    }
                }
            } else {
                foreach ($this->txt_patterns as $field => $pattern_config) {
                    if ($pattern_config['enabled'] && !empty(trim($pattern_config['pattern']))) {
                        $config[] = [
                            'field' => $field,
                            'pattern' => trim($pattern_config['pattern'])
                        ];
                    }
                }
            }

            $machine->update([
                'number' => $validated['number'],
                'name' => $validated['name'],
                'type' => $validated['type'],
                'cells' => json_encode($config),
            ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Mesin diperbarui') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset(['number', 'name', 'type', 'excel_cells', 'txt_patterns']);
        $this->initializeArrays();
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Edit Mesin') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>

        <div class="mt-6 flex flex-col md:flex-row gap-6">
            <!-- General Info Section -->
            <div class="md:w-1/3">
                <h3 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-4 uppercase">
                    {{ __('Informasi Umum') }}
                </h3>
                
                <div class="space-y-6">
                    <div>
                        <label for="machine-number" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nomor') }}</label>
                        <x-text-input id="machine-number" wire:model="number" type="number" :disabled="Gate::denies('manage', InsRdcMachine::class)" />
                        @error('number')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>  

                    <div>
                        <label for="machine-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                        <x-text-input id="machine-name" wire:model="name" type="text" :disabled="Gate::denies('manage', InsRdcMachine::class)" />
                        @error('name')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>

                    <div>
                        <label for="machine-type" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tipe File') }}</label>
                        <x-select id="machine-type" wire:model.live="type" class="w-full" :disabled="Gate::denies('manage', InsRdcMachine::class)">
                            <option value="excel">Excel (.xls, .xlsx)</option>
                            <option value="txt">Text (.txt)</option>
                        </x-select>
                        @error('type')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Configuration Section -->
            <div class="md:w-2/3">
                <h3 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-4 uppercase">
                    {{ $type === 'excel' ? __('Konfigurasi Alamat Sel') : __('Konfigurasi Pattern') }}
                </h3>
                
                @can('manage', InsRdcMachine::class)
                <div class="h-96 md:h-auto md:max-h-96 overflow-y-auto pr-2">
                    @if($type === 'excel')
                        <!-- Excel Configuration -->
                        <div class="space-y-4">
                            <div class="text-sm text-neutral-600 dark:text-neutral-400 px-3 mb-4">
                                {{ __('Masukkan alamat sel Excel untuk setiap field yang ingin diambil. Kosongkan jika field tidak tersedia.') }}
                            </div>
                            
                            @foreach($available_fields as $field => $config)
                                <div class="grid grid-cols-3 gap-3 items-center">
                                    <div class="text-sm font-medium">
                                        {{ $config['label'] }}
                                        @if($config['required'])
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </div>
                                    <div>
                                        <x-text-input 
                                            type="text" 
                                            wire:model="excel_cells.{{ $field }}" 
                                            placeholder="A1" 
                                            class="uppercase"
                                        />
                                    </div>
                                    <div class="text-xs text-neutral-500">
                                        {{ $field }}
                                    </div>
                                    @error("excel_cells.{$field}")
                                        <div class="col-span-3">
                                            <x-input-error messages="{{ $message }}" class="px-3" />
                                        </div>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                    @else
                        <!-- TXT Configuration -->
                        <div class="space-y-6">
                            <div class="text-sm text-neutral-600 dark:text-neutral-400 px-3 mb-4">
                                {{ __('Pilih preset pattern atau buat custom pattern untuk setiap field yang ingin diambil.') }}
                            </div>

                            @foreach($available_fields as $field => $config)
                                <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <label class="font-medium">{{ $config['label'] }}</label>
                                        <x-toggle 
                                            wire:model.live="txt_patterns.{{ $field }}.enabled"
                                            name="txt_patterns_{{ $field }}_enabled"
                                        >{{ __('Aktif') }}</x-toggle>
                                    </div>

                                    @if(isset($txt_patterns[$field]) && $txt_patterns[$field]['enabled'])
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Preset Pattern') }}</label>
                                                <x-select wire:model.live="txt_patterns.{{ $field }}.preset" class="w-full">
                                                    <option value="">{{ __('Pilih preset atau buat custom') }}</option>
                                                    @foreach($txt_presets as $preset_key => $preset)
                                                        <option value="{{ $preset_key }}">{{ $preset['label'] }}</option>
                                                    @endforeach
                                                </x-select>
                                            </div>

                                            <div>
                                                <label class="block text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Pattern (Regex)') }}</label>
                                                <x-text-input 
                                                    type="text" 
                                                    wire:model="txt_patterns.{{ $field }}.pattern"
                                                    placeholder="{{ __('Masukkan regex pattern') }}"
                                                    class="w-full font-mono text-sm"
                                                />
                                                @if(isset($txt_patterns[$field]['preset']) && $txt_patterns[$field]['preset'] && isset($txt_presets[$txt_patterns[$field]['preset']]))
                                                    <div class="text-xs text-neutral-500 mt-1">
                                                        {{ __('Contoh: ') . $txt_presets[$txt_patterns[$field]['preset']]['example'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @error("txt_patterns.{$field}.pattern")
                                        <x-input-error messages="{{ $message }}" class="mt-2" />
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @else
                <div class="h-96 md:h-auto md:max-h-96 flex items-center justify-center text-neutral-500">
                    {{ __('Tidak memiliki izin untuk mengedit konfigurasi') }}
                </div>
                @endcan
            </div>
        </div>

        @can('manage', InsRdcMachine::class)
        <div class="mt-6 flex justify-between">
            <x-secondary-button type="button" wire:click="customReset">
                {{ __('Reset') }}
            </x-secondary-button>
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>