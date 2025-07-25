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
    public bool $is_active = true;
    public array $field_configs = [];
    public bool $is_loading = false;

    // Available fields for configuration
    public array $available_fields = [
        'mcs' => ['label' => 'MCS', 'required' => false],
        'color' => ['label' => 'Warna', 'required' => false],
        'model' => ['label' => 'Model', 'required' => false],
        'code_alt' => ['label' => 'Kode alt', 'required' => false],
        'eval' => ['label' => 'Evaluation/Status', 'required' => false],
        's_max' => ['label' => 'S Max (Bounds)', 'required' => false],
        's_min' => ['label' => 'S Min (Bounds)', 'required' => false],
        'tc10' => ['label' => 'TC10 (Bounds)', 'required' => false],
        'tc50' => ['label' => 'TC50 (Bounds)', 'required' => false],
        'tc90' => ['label' => 'TC90 (Bounds)', 'required' => false],
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

    public function initializeFieldConfigs()
    {
        foreach ($this->available_fields as $field => $config) {
            $this->field_configs[$field] = [
                'enabled' => $this->field_configs[$field]['enabled'] ?? false,
                'config_type' => $this->field_configs[$field]['config_type'] ?? ($this->type === 'excel' ? 'static' : 'pattern'),
                // Static config
                'address' => $this->field_configs[$field]['address'] ?? '',
                // Dynamic config  
                'row_search' => $this->field_configs[$field]['row_search'] ?? '',
                'column_search' => $this->field_configs[$field]['column_search'] ?? '',
                'row_offset' => $this->field_configs[$field]['row_offset'] ?? 0,
                'column_offset' => $this->field_configs[$field]['column_offset'] ?? 0,
                // Pattern config (for txt)
                'preset' => $this->field_configs[$field]['preset'] ?? '',
                'pattern' => $this->field_configs[$field]['pattern'] ?? '',
            ];
        }
    }

    public function rules()
    {
        $rules = [
            'number' => ['required', 'integer', 'min:1', 'max:99', Rule::unique('ins_rdc_machines', 'number')->ignore($this->id ?? null)],
            'name' => ['required', 'string', 'min:1', 'max:20'],
            'type' => ['required', 'in:excel,txt'],
            'is_active' => ['required', 'boolean'],
        ];

        $allFields = [];
        foreach ($this->available_fields as $field => $config) {
            $allFields[$field] = $config;
        }

        foreach ($allFields as $field => $config) {
            if (isset($this->field_configs[$field]) && $this->field_configs[$field]['enabled']) {
                $fieldConfig = $this->field_configs[$field];
                
                if ($this->type === 'excel') {
                    switch ($fieldConfig['config_type']) {
                        case 'static':
                            $rules["field_configs.{$field}.address"] = ['required', 'string', 'regex:/^[A-Z]+[1-9]\d*$/'];
                            break;
                        case 'dynamic':
                            $rules["field_configs.{$field}.row_search"] = ['required', 'string', 'regex:/^[a-zA-Z0-9]+$/'];
                            $rules["field_configs.{$field}.column_search"] = ['required', 'string', 'regex:/^[a-zA-Z0-9]+$/'];
                            $rules["field_configs.{$field}.row_offset"] = ['integer'];
                            $rules["field_configs.{$field}.column_offset"] = ['integer'];
                            break;
                    }
                } else {
                    // TXT type
                    $rules["field_configs.{$field}.pattern"] = ['required', 'string', 'min:1'];
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
            $this->type = $machine->type ?? 'excel';
            $this->is_active = $machine->is_active ?? true;
            
            $this->initializeFieldConfigs();
            
            // Load existing configuration
            $cells = $machine->cells ?? [];
            
            foreach ($cells as $cell) {
                if (!isset($cell['field'])) {
                    continue;
                }

                $field = $cell['field'];
                if (!isset($this->field_configs[$field])) {
                    continue;
                }

                $this->field_configs[$field]['enabled'] = true;

                // Handle different configuration types
                if (isset($cell['type'])) {
                    // New hybrid format
                    $this->field_configs[$field]['config_type'] = $cell['type'];
                    
                    switch ($cell['type']) {
                        case 'static':
                            $this->field_configs[$field]['address'] = $cell['address'] ?? '';
                            break;
                        case 'dynamic':
                            $this->field_configs[$field]['row_search'] = $cell['row_search'] ?? '';
                            $this->field_configs[$field]['column_search'] = $cell['column_search'] ?? '';
                            $this->field_configs[$field]['row_offset'] = $cell['row_offset'] ?? 0;
                            $this->field_configs[$field]['column_offset'] = $cell['column_offset'] ?? 0;
                            break;
                        case 'pattern':
                            $this->field_configs[$field]['pattern'] = $cell['pattern'] ?? '';
                            // Try to match with presets
                            foreach ($this->txt_presets as $preset_key => $preset) {
                                if ($preset['pattern'] === $cell['pattern']) {
                                    $this->field_configs[$field]['preset'] = $preset_key;
                                    break;
                                }
                            }
                            break;
                    }
                } else {
                    // Legacy format - auto-detect
                    if (isset($cell['address'])) {
                        $this->field_configs[$field]['config_type'] = 'static';
                        $this->field_configs[$field]['address'] = $cell['address'];
                    } elseif (isset($cell['pattern'])) {
                        $this->field_configs[$field]['config_type'] = 'pattern';
                        $this->field_configs[$field]['pattern'] = $cell['pattern'];
                    }
                }
            }
        
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function updatedFieldConfigs($value, $key)
    {
        // Handle preset selection for pattern configs
        if (str_ends_with($key, '.preset')) {
            $field = explode('.', $key)[0];
            $preset = $this->field_configs[$field]['preset'];
            
            if ($preset && isset($this->txt_presets[$preset])) {
                $this->field_configs[$field]['pattern'] = $this->txt_presets[$preset]['pattern'];
            }
        }
    }

    public function save()
    {
        if (!Gate::allows('manage', InsRdcMachine::class)) {
            $this->js('toast("' . __('Tidak memiliki izin untuk mengedit konfigurasi') . '", { type: "danger" })');
            return;
        }

        $this->is_loading = true;

        $machine = InsRdcMachine::find($this->id);
        if (!$machine) {
            $this->handleNotFound();
            return;
        }

        $this->name = strtoupper(trim($this->name));
        
        try {
            $this->validate();

            // Build configuration array
            $config = [];
            
            foreach ($this->field_configs as $field => $fieldConfig) {
                if (!$fieldConfig['enabled']) {
                    continue;
                }

                $configItem = ['field' => $field];

                if ($this->type === 'excel') {
                    switch ($fieldConfig['config_type']) {
                        case 'static':
                            $configItem['type'] = 'static';
                            $configItem['address'] = strtoupper(trim($fieldConfig['address']));
                            break;
                        case 'dynamic':
                            $configItem['type'] = 'dynamic';
                            $configItem['row_search'] = strtolower(trim($fieldConfig['row_search']));
                            $configItem['column_search'] = strtolower(trim($fieldConfig['column_search']));
                            $configItem['row_offset'] = (int)$fieldConfig['row_offset'];
                            $configItem['column_offset'] = (int)$fieldConfig['column_offset'];
                            break;
                    }
                } else {
                    // TXT type
                    $configItem['type'] = 'pattern';
                    $configItem['pattern'] = trim($fieldConfig['pattern']);
                }

                if (!empty($configItem)) {
                    $config[] = $configItem;
                }
            }

            // Validate the configuration
            $validationErrors = $machine->validateHybridConfig($config);
            if (!empty($validationErrors)) {
                $this->js('toast("' . $validationErrors[0] . '", { type: "danger" })');
                return;
            }

            $machine->update([
                'number' => $this->number,
                'name' => $this->name,
                'type' => $this->type,
                'is_active' => $this->is_active,
                'cells' => $config,
            ]);

            $this->js('toast("' . __('Mesin berhasil diperbarui') . '", { type: "success" })');
            $this->js('window.dispatchEvent(escKey)');
            $this->dispatch('updated');

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->js('toast("' . collect($e->errors())->flatten()->first() . '", { type: "danger" })');
        } catch (\Exception $e) {
            $this->js('toast("' . __('Terjadi kesalahan saat memperbarui mesin') . '", { type: "danger" })');
        } finally {
            $this->is_loading = false;
        }
    }

    public function handleNotFound()
    {
        $this->js('window.dispatchEvent(escKey)');
        $this->js('toast("' . __('Mesin tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }
};
?>

<div class="relative overflow-y-auto">
    <!-- Header -->
    <div class="flex justify-between items-center p-6">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Edit mesin') }}
        </h2>
        <div>
            <div wire:loading wire:target="save">
                <x-primary-button type="button" disabled>
                    {{ __('Simpan') }}
                </x-primary-button>
            </div>
            <div wire:loading.remove wire:target="save">
                <x-primary-button type="button" wire:click="save">
                    {{ __('Simpan') }}
                </x-primary-button>
            </div>
        </div>
    </div>

    <!-- Error Display -->
    @if ($errors->any())
        <div class="px-6">
            <x-input-error :messages="$errors->first()" />
        </div>
    @endif

    <!-- Form Content -->
    <div class="grid grid-cols-1 gap-y-6 px-6 pb-6">
        
        <!-- Umum Section -->
        <div>
            <x-pill class="uppercase mb-4">{{ __('Umum') }}</x-pill>
            
            <div class="grid grid-cols-1 gap-y-4">
            <div class="grid grid-cols-2 gap-x-4">
                <div>
                    <label for="machine-number" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nomor') }}</label>
                    <x-text-input id="machine-number" wire:model="number" type="number" class="w-full" />
                </div>  

                <div>
                    <label for="machine-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                    <x-text-input id="machine-name" wire:model="name" type="text" class="w-full" />
                </div>
            </div>

                <div>
                    <label for="machine-type" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tipe File') }}</label>
                    <x-select id="machine-type" wire:model.live="type" class="w-full">
                        <option value="excel">Excel (.xls, .xlsx)</option>
                        <option value="txt">Text (.txt)</option>
                    </x-select>
                </div>

                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Status') }}</label>
                    <div class="px-3">
                        <x-toggle wire:model="is_active" id="machine-is-active">
                            <span x-show="$wire.is_active">{{ __('Aktif') }}</span>
                            <span x-show="!$wire.is_active">{{ __('Nonaktif') }}</span>
                        </x-toggle>
                    </div>
                </div>
            </div>
        </div>

        <!-- Konfigurasi Section -->
        <div>
            <x-pill class="uppercase mb-4">{{ __('Konfigurasi') }}</x-pill>
            
            <div class="space-y-4">
                @foreach($available_fields as $field => $config)
                    <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-4">
                        
                        <!-- Field Header -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <label class="font-medium">{{ $config['label'] }}</label>                                
                            </div>
                            <x-toggle 
                                wire:model.live="field_configs.{{ $field }}.enabled"
                                name="field_{{ $field }}_enabled"
                            >{{ __('Aktif') }}</x-toggle>
                        </div>

                        <!-- Configuration Options -->
                        @if(isset($field_configs[$field]) && $field_configs[$field]['enabled'])
                        <div class="space-y-4">
                            @if($type === 'excel')
                                <!-- Excel Configuration Type Selection -->
                                <div class="flex gap-4">
                                    <x-radio 
                                        id="{{ 'excel_address' . $loop->index . $field }}"
                                        wire:model.live="field_configs.{{ $field }}.config_type" 
                                        value="static" 
                                        name="config_type_{{ $field }}"
                                    >{{ __('Address') }}</x-radio>
                                    <x-radio 
                                        id="{{ 'excel_intersection' . $loop->index . $field }}"  
                                        wire:model.live="field_configs.{{ $field }}.config_type" 
                                        value="dynamic" 
                                        name="config_type_{{ $field }}"
                                    >{{ __('Intersection') }}</x-radio>
                                </div>

                                <!-- Static Configuration -->
                                @if(isset($field_configs[$field]) && $field_configs[$field]['config_type'] === 'static')
                                    <div>
                                        <label class="block text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Excel Address') }}</label>
                                        <x-text-input 
                                            type="text" 
                                            wire:model="field_configs.{{ $field }}.address"
                                            class="uppercase w-full"
                                        />
                                    </div>
                                @endif

                                <!-- Dynamic Configuration -->
                                @if(isset($field_configs[$field]) && $field_configs[$field]['config_type'] === 'dynamic')
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Row Search') }}</label>
                                            <x-text-input 
                                                type="text" 
                                                wire:model="field_configs.{{ $field }}.row_search"
                                                class="w-full"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Column Search') }}</label>
                                            <x-text-input 
                                                type="text" 
                                                wire:model="field_configs.{{ $field }}.column_search"
                                                class="w-full"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Row Offset') }}</label>
                                            <x-text-input 
                                                type="number" 
                                                wire:model="field_configs.{{ $field }}.row_offset"
                                                class="w-full"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Column Offset') }}</label>
                                            <x-text-input 
                                                type="number" 
                                                wire:model="field_configs.{{ $field }}.column_offset"
                                                class="w-full"
                                            />
                                        </div>
                                    </div>
                                @endif

                            @else
                                <!-- TXT Pattern Configuration -->
                                <div>
                                    <label class="block text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Preset Pattern') }}</label>
                                    <x-select wire:model.live="field_configs.{{ $field }}.preset" class="w-full">
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
                                        wire:model="field_configs.{{ $field }}.pattern"
                                        class="w-full font-mono text-sm"
                                    />
                                </div>
                            @endif
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="save"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target="save" class="hidden"></x-spinner>
</div>