<?php

use Livewire\Volt\Component;
use App\Models\InsPpmProduct;
use App\Models\InsPpmComponent;
use App\Models\InsPpmComponentsProcess;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;

new class extends Component {
    public int $productId = 0;
    public int $componentId = 0;
    
    // Product fields
    public string $product_code = "";
    public string $dev_style = "";
    public string $color_way = "";
    public string $production_date = "";
    
    // Component fields
    public string $part_name = "";
    public string $material_number = "";
    
    // Process Steps
    public $processSteps = [];

    public function rules()
    {
        return [
            'product_code' => ['required', 'string', 'max:50', Rule::unique('ins_ppm_products', 'product_code')->ignore($this->productId)],
            'dev_style' => ['required', 'string', 'max:100'],
            'color_way' => ['required', 'string', 'max:100'],
            'production_date' => ['required', 'date'],
            'part_name' => ['required', 'string', 'max:100'],
            'material_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    #[On("process-edit")]
    public function loadComponent(int $productId, int $componentId)
    {
        $this->productId = $productId;
        $this->componentId = $componentId;

        $product = InsPpmProduct::find($productId);
        $component = InsPpmComponent::find($componentId);

        if ($product && $component) {
            // Load product data
            $this->product_code = $product->product_code;
            $this->dev_style = $product->dev_style;
            $this->color_way = $product->color_way;
            $this->production_date = $product->production_date?->format('Y-m-d') ?? '';

            // Load component data
            $this->part_name = $component->part_name;
            $this->material_number = $component->material_number ?? '';

            // Load process steps
            $processes = $component->processes;
            if ($processes->isNotEmpty()) {
                $process = $processes->first();
                $processData = $process->process_data ?? [];
                $steps = $processData['process_steps'] ?? [];
                
                $this->processSteps = array_map(function($step, $index) {
                    return [
                        'id' => $step['id'] ?? uniqid('step_'),
                        'step_number' => $step['step_number'] ?? ($index + 1),
                        'process_type' => $step['process_type'] ?? '',
                        'operation' => $step['operation'] ?? '',
                        'color_code' => $step['color_code'] ?? '',
                        'chemical' => $step['chemical'] ?? '',
                        'hardener_code' => $step['hardener_code'] ?? '',
                        'temperature_c' => $step['temperature_c'] ?? '',
                        'wipes_count' => isset($step['wipes_count']) ? (string)$step['wipes_count'] : '',
                        'rounds_count' => isset($step['rounds_count']) ? (string)$step['rounds_count'] : '',
                        'duration' => $step['duration'] ?? '',
                        'mesh_number' => $step['mesh_number'] ?? '',
                        'method' => $step['method'] ?? '',
                    ];
                }, $steps, array_keys($steps));
            } 
            
            if (empty($this->processSteps)) {
                $this->initProcessSteps();
            }

            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function initProcessSteps()
    {
        $this->processSteps = [
            [
                'id' => uniqid('step_'),
                'step_number' => 1,
                'process_type' => '',
                'operation' => '',
                'color_code' => '',
                'chemical' => '',
                'hardener_code' => '',
                'temperature_c' => '',
                'wipes_count' => '',
                'rounds_count' => '',
                'duration' => '',
                'mesh_number' => '',
                'method' => '',
            ]
        ];
    }

    public function addProcessStep()
    {
        $this->processSteps[] = [
            'id' => uniqid('step_'),
            'step_number' => count($this->processSteps) + 1,
            'process_type' => '',
            'operation' => '',
            'color_code' => '',
            'chemical' => '',
            'hardener_code' => '',
            'temperature_c' => '',
            'wipes_count' => '',
            'rounds_count' => '',
            'duration' => '',
            'mesh_number' => '',
            'method' => '',
        ];
    }

    public function removeProcessStep($index)
    {
        if (count($this->processSteps) > 1) {
            array_splice($this->processSteps, $index, 1);
            foreach ($this->processSteps as $i => $step) {
                $this->processSteps[$i]['step_number'] = $i + 1;
            }
        }
    }

    public function save()
    {
        $product = InsPpmProduct::find($this->productId);
        $component = InsPpmComponent::find($this->componentId);

        if ($product && $component) {
            $validated = $this->validate();

            // Update product
            $product->update([
                'product_code' => trim($validated['product_code']),
                'dev_style' => trim($validated['dev_style']),
                'color_way' => trim($validated['color_way']),
                'production_date' => $validated['production_date'],
            ]);

            // Update component
            $component->update([
                'part_name' => trim($validated['part_name']),
                'material_number' => trim($validated['material_number'] ?? ''),
            ]);

            // Build process steps JSON
            $processData = [
                'process_steps' => array_map(function($step) {
                    return [
                        'step_number' => (int)$step['step_number'],
                        'process_type' => trim($step['process_type'] ?? ''),
                        'operation' => trim($step['operation'] ?? ''),
                        'color_code' => trim($step['color_code'] ?? ''),
                        'chemical' => trim($step['chemical'] ?? ''),
                        'hardener_code' => trim($step['hardener_code'] ?? ''),
                        'temperature_c' => trim($step['temperature_c'] ?? ''),
                        'wipes_count' => !empty($step['wipes_count']) ? (int)$step['wipes_count'] : null,
                        'rounds_count' => !empty($step['rounds_count']) ? (int)$step['rounds_count'] : null,
                        'duration' => trim($step['duration'] ?? ''),
                        'mesh_number' => trim($step['mesh_number'] ?? ''),
                        'method' => trim($step['method'] ?? ''),
                    ];
                }, $this->processSteps),
            ];

            // Update or create process
            $process = $component->processes()->first();
            if ($process) {
                $process->update(['process_data' => $processData]);
            } else {
                InsPpmComponentsProcess::create([
                    'component_id' => $component->id,
                    'process_data' => $processData,
                ]);
            }

            $this->js('$dispatch("close")');
            $this->js('toast("' . __("Data diperbarui") . '", { type: "success" })');
            $this->dispatch("updated");
        } else {
            $this->handleNotFound();
        }
    }

    public function delete()
    {
        $component = InsPpmComponent::find($this->componentId);

        if ($component) {
            $component->processes()->delete();
            $component->delete();

            $this->js('$dispatch("close")');
            $this->js('toast("' . __("Komponen dihapus") . '", { type: "success" })');
            $this->dispatch("updated");
        } else {
            $this->handleNotFound();
        }
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Tidak ditemukan") . '", { type: "danger" })');
        $this->dispatch("updated");
    }
};
?>

<div>
    <form wire:submit="save" class="p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-start sticky top-0 bg-white dark:bg-neutral-800 z-10 pb-4 border-b border-neutral-200 dark:border-neutral-700">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Edit Proses") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>

        <!-- Product Section -->
        <div class="mt-4 border-b border-neutral-200 dark:border-neutral-700 pb-4">
            <h3 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-3">{{ __("Data Produk") }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="product-code-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Product Code") }} *</label>
                    <x-text-input id="product-code-edit" wire:model="product_code" type="text" />
                </div>
                <div>
                    <label for="dev-style-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Dev Style") }} *</label>
                    <x-text-input id="dev-style-edit" wire:model="dev_style" type="text" />
                </div>
                <div>
                    <label for="color-way-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Color Way") }} *</label>
                    <x-text-input id="color-way-edit" wire:model="color_way" type="text" />
                </div>
                <div>
                    <label for="production-date-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Production Date") }} *</label>
                    <x-text-input id="production-date-edit" wire:model="production_date" type="date" />
                </div>
            </div>
        </div>

        <!-- Component Section -->
        <div class="mt-4 border-b border-neutral-200 dark:border-neutral-700 pb-4">
            <h3 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-3">{{ __("Data Komponen") }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="part-name-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Part Name") }} *</label>
                    <x-text-input id="part-name-edit" wire:model="part_name" type="text" />
                </div>
                <div>
                    <label for="material-number-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Material Number") }}</label>
                    <x-text-input id="material-number-edit" wire:model="material_number" type="text" />
                </div>
            </div>
        </div>

        <!-- Process Steps Section -->
        <div class="mt-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Langkah-Langkah Proses") }}</h3>
                <x-secondary-button type="button" wire:click="addProcessStep">
                    <i class="icon-plus"></i> {{ __("Tambah Langkah") }}
                </x-secondary-button>
            </div>
            
            <div class="space-y-4">
                @foreach($processSteps as $index => $step)
                    <div class="bg-neutral-50 dark:bg-neutral-900 p-4 rounded-lg border border-neutral-200 dark:border-neutral-700">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm font-bold text-neutral-700 dark:text-neutral-300">{{ __("Langkah") }} {{ $index + 1 }}</span>
                            @if(count($processSteps) > 1)
                                <x-text-button type="button" wire:click="removeProcessStep({{ $index }})" class="text-red-500">
                                    <i class="icon-trash"></i>
                                </x-text-button>
                            @endif
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Step #") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.step_number" type="number" min="1" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Process Type") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.process_type" type="text" placeholder="clear" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Operation") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.operation" type="text" placeholder="CLEANING COLOR/CODE" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Color Code") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.color_code" type="text" placeholder="CLEAR" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Chemical") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.chemical" type="text" placeholder="NO. 29 [SB]" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Hardener Code") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.hardener_code" type="text" placeholder="N/A" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Temperature (°C)") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.temperature_c" type="text" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Wipes Count") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.wipes_count" type="number" min="1" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Rounds Count") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.rounds_count" type="number" min="1" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Duration") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.duration" type="text" placeholder="4m-5m" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Mesh Number") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.mesh_number" type="text" placeholder="200" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Method") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.method" type="text" placeholder="MANUAL" />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6 flex justify-between">
            <x-danger-button type="button" wire:click="delete" wire:confirm="{{ __('Hapus komponen ini?') }}">
                {{ __("Hapus") }}
            </x-danger-button>
            <x-primary-button type="submit">
                {{ __("Simpan Perubahan") }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>