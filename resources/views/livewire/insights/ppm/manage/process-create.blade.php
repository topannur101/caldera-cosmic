<?php

use Livewire\Volt\Component;
use App\Models\InsPpmProduct;
use App\Models\InsPpmComponent;
use App\Models\InsPpmComponentsProcess;
use Illuminate\Validation\Rule;

new class extends Component {
    // Product fields
    public string $product_code = "";
    public string $dev_style = "";
    
    // Component fields
    public string $part_name = "";
    
    // Process Steps
    public $processSteps = [];
    
    // Existing product/component selection
    public $existingProducts = [];
    public $existingComponents = [];
    public ?int $selectedProductId = null;
    public ?int $selectedComponentId = null;
    public bool $useExistingProduct = false;
    public bool $useExistingComponent = false;

    public function mount()
    {
        $this->loadExistingProducts();
        $this->initProcessSteps();
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

    public function loadExistingProducts()
    {
        $this->existingProducts = InsPpmProduct::orderBy('product_code')->get()->toArray();
    }

    public function loadExistingComponents()
    {
        if ($this->selectedProductId) {
            $this->existingComponents = InsPpmComponent::where('product_id', $this->selectedProductId)
                ->orderBy('part_name')
                ->get()
                ->toArray();
        } else {
            $this->existingComponents = [];
        }
    }

    public function updatedSelectedProductId($value)
    {
        $this->loadExistingComponents();
        $this->selectedComponentId = null;
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

    public function rules()
    {
        $rules = [];
        
        if ($this->useExistingProduct) {
            $rules['selectedProductId'] = ['required', 'integer'];
        } else {
            $rules['product_code'] = ['required', 'string', 'max:50', Rule::unique('ins_ppm_products', 'product_code')];
            $rules['dev_style'] = ['required', 'string', 'max:100'];
        }

        if ($this->useExistingComponent) {
            $rules['selectedComponentId'] = ['required', 'integer'];
        } else {
            $rules['part_name'] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }

    public function save()
    {
        $this->validate();

        // Get or create product
        $product = null;
        if ($this->useExistingProduct && $this->selectedProductId) {
            $product = InsPpmProduct::find($this->selectedProductId);
        } else {
            $product = InsPpmProduct::create([
                'product_code' => trim($this->product_code),
                'dev_style' => trim($this->dev_style),
            ]);
        }

        // Get or create component
        $component = null;
        if ($this->useExistingComponent && $this->selectedComponentId) {
            $component = InsPpmComponent::find($this->selectedComponentId);
        } else {
            $component = InsPpmComponent::create([
                'product_id' => $product->id,
                'part_name' => trim($this->part_name),
            ]);
        }

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
                ];
            }, $this->processSteps),
        ];

        // Create process
        InsPpmComponentsProcess::create([
            'component_id' => $component->id,
            'process_data' => $processData,
        ]);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Proses dibuat") . '", { type: "success" })');
        $this->dispatch("updated");

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset([
            'product_code', 'dev_style',
            'part_name',
            'selectedProductId', 'selectedComponentId'
        ]);
        $this->useExistingProduct = false;
        $this->useExistingComponent = false;
        $this->existingComponents = [];
        $this->initProcessSteps();
    }
};
?>

<div>
    <form wire:submit="save" class="p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-start sticky top-0 bg-white dark:bg-neutral-800 z-10 pb-4 border-b border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __("Tambah Proses Baru") }}
                </h2>
                <a href="{{ route('download.ins-ppm-process-template') }}" target="_blank" class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded hover:bg-blue-100 dark:bg-blue-900 dark:text-blue-300 dark:hover:bg-blue-800" title="{{ __('Download Template') }}">
                    <i class="icon-download mr-1"></i> {{ __('Template') }}
                </a>
            </div>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>

        <!-- Product Section -->
        <div class="mt-4 border-b border-neutral-200 dark:border-neutral-700 pb-4">
            <div class="flex items-center mb-4">
                <input type="checkbox" wire:model="useExistingProduct" id="use-existing-product" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                <label for="use-existing-product" class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ __("Pilih produk yang ada") }}</label>
            </div>

            @if ($useExistingProduct)
                <div>
                    <label for="product-select" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Pilih Produk") }}</label>
                    <select id="product-select" wire:model="selectedProductId" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">{{ __("-- Pilih Produk --") }}</option>
                        @foreach ($existingProducts as $product)
                            <option value="{{ $product['id'] }}">{{ $product['product_code'] }} - {{ $product['dev_style'] }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="product-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Product Code") }} *</label>
                        <x-text-input id="product-code" wire:model="product_code" type="text" />
                    </div>
                    <div>
                        <label for="dev-style" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Dev Style") }} *</label>
                        <x-text-input id="dev-style" wire:model="dev_style" type="text" />
                    </div>
                </div>
            @endif
        </div>

        <!-- Component Section -->
        <div class="mt-4 border-b border-neutral-200 dark:border-neutral-700 pb-4">
            <div class="flex items-center mb-4">
                <input type="checkbox" wire:model="useExistingComponent" id="use-existing-component" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" {{ !$selectedProductId ? 'disabled' : '' }}>
                <label for="use-existing-component" class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ __("Pilih komponen yang ada") }}</label>
            </div>

            @if ($useExistingComponent && $selectedProductId)
                <div>
                    <label for="component-select" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Pilih Komponen") }}</label>
                    <select id="component-select" wire:model="selectedComponentId" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">{{ __("-- Pilih Komponen --") }}</option>
                        @foreach ($existingComponents as $component)
                            <option value="{{ $component['id'] }}">{{ $component['part_name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif (!$selectedProductId && $useExistingComponent)
                <p class="text-sm text-neutral-500">{{ __("Silakan pilih produk terlebih dahulu") }}</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="part-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Part Name") }} *</label>
                        <x-text-input id="part-name" wire:model="part_name" type="text" />
                    </div>
                </div>
            @endif
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
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Step #") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.step_number" type="number" min="1" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Process Type") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.process_type" type="text" placeholder="CLEANER" />
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
                                <x-text-input wire:model="processSteps.{{ $index }}.temperature_c" type="text" placeholder="" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Wipes Count") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.wipes_count" type="number" min="1" placeholder="1" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Rounds Count") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.rounds_count" type="number" min="1" placeholder="2" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Duration") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.duration" type="text" placeholder="4m-5m" />
                            </div>
                            <div>
                                <label class="block px-2 mb-1 uppercase text-xs text-neutral-500">{{ __("Mesh Number") }}</label>
                                <x-text-input wire:model="processSteps.{{ $index }}.mesh_number" type="text" placeholder="200" />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __("Simpan") }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>