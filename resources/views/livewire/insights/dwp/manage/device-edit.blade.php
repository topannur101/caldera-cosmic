<?php

use Livewire\Volt\Component;
use App\Models\InsDwpDevice;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    public ?InsDwpDevice $device = null;
    public string $name = "";
    public string $ip_address = "";
    public array $lines = [];
    public bool $is_active = true;

    public function rules()
    {
        return [
            "name" => ["required", "string", "max:255"],
            "ip_address" => ["required", "ip", Rule::unique('ins_dwp_devices', 'ip_address')->ignore($this->device?->id)],
            "lines" => ["required", "array", "min:1", "max:10"],
            "lines.*.line" => ["required", "string", "max:50"],
            
            // DWP Alarm validations
            "lines.*.dwp_alarm.addr_dd_1" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.dwp_alarm.addr_dd_2" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.dwp_alarm.addr_dd_3" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.dwp_alarm.addr_dd_4" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.dwp_alarm.addr_dd_5" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.dwp_alarm.addr_reset" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.dwp_alarm.addr_control" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.dwp_alarm.addr_counter" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.dwp_alarm.addr_data_storage" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.dwp_alarm.addr_long_duration" => ["required", "integer", "min:0", "max:65535"],
            
            // List machine validations
            "lines.*.list_mechine" => ["required", "array", "min:1", "max:20"],
            "lines.*.list_mechine.*.name" => ["required", "string", "max:50"],
            "lines.*.list_mechine.*.addr_th_l" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.list_mechine.*.addr_th_r" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.list_mechine.*.addr_side_l" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.list_mechine.*.addr_side_r" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.list_mechine.*.addr_std_th_max" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.list_mechine.*.addr_std_th_min" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.list_mechine.*.addr_std_side_max" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.list_mechine.*.addr_std_side_min" => ["required", "integer", "min:0", "max:65535"],
            
            "is_active" => ["boolean"],
        ];
    }

    #[On("device-edit")]
    public function loadDevice($id)
    {
        $this->device = InsDwpDevice::findOrFail($id);
        
        $this->name = $this->device->name;
        $this->ip_address = $this->device->ip_address;
        $this->lines = $this->device->config ?? $this->getDefaultLineStructure();
        $this->is_active = $this->device->is_active;
        $this->resetValidation();
    }

    private function getDefaultLineStructure()
    {
        return [[
            "line" => "",
            "dwp_alarm" => [
                "addr_dd_1" => "",
                "addr_dd_2" => "",
                "addr_dd_3" => "",
                "addr_dd_4" => "",
                "addr_dd_5" => "",
                "addr_reset" => "",
                "addr_control" => "",
                "addr_counter" => "",
                "addr_data_storage" => "",
                "addr_long_duration" => "",
            ],
            "list_mechine" => [[
                "name" => "",
                "addr_th_l" => "",
                "addr_th_r" => "",
                "addr_side_l" => "",
                "addr_side_r" => "",
                "addr_std_th_max" => "",
                "addr_std_th_min" => "",
                "addr_std_side_max" => "",
                "addr_std_side_min" => "",
            ]]
        ]];
    }

    public function save()
    {
        if (!$this->device) {
            return;
        }

        Gate::authorize("manage", $this->device);

        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        // Process lines to ensure proper format
        $lines = array_map(function ($line) {
            return [
                "line" => strtoupper(trim($line["line"])),
                "dwp_alarm" => [
                    "addr_dd_1" => (int) $line["dwp_alarm"]["addr_dd_1"],
                    "addr_dd_2" => (int) $line["dwp_alarm"]["addr_dd_2"],
                    "addr_dd_3" => (int) $line["dwp_alarm"]["addr_dd_3"],
                    "addr_dd_4" => (int) $line["dwp_alarm"]["addr_dd_4"],
                    "addr_dd_5" => (int) $line["dwp_alarm"]["addr_dd_5"],
                    "addr_reset" => (int) $line["dwp_alarm"]["addr_reset"],
                    "addr_control" => (int) $line["dwp_alarm"]["addr_control"],
                    "addr_counter" => (int) $line["dwp_alarm"]["addr_counter"],
                    "addr_data_storage" => (int) $line["dwp_alarm"]["addr_data_storage"],
                    "addr_long_duration" => (int) $line["dwp_alarm"]["addr_long_duration"],
                ],
                "list_mechine" => array_map(function ($machine) {
                    return [
                        "name" => trim($machine["name"]),
                        "addr_th_l" => (int) $machine["addr_th_l"],
                        "addr_th_r" => (int) $machine["addr_th_r"],
                        "addr_side_l" => (int) $machine["addr_side_l"],
                        "addr_side_r" => (int) $machine["addr_side_r"],
                        "addr_std_th_max" => (int) $machine["addr_std_th_max"],
                        "addr_std_th_min" => (int) $machine["addr_std_th_min"],
                        "addr_std_side_max" => (int) $machine["addr_std_side_max"],
                        "addr_std_side_min" => (int) $machine["addr_std_side_min"],
                    ];
                }, $line["list_mechine"])
            ];
        }, $validated["lines"]);

        $this->device->update([
            "name" => $validated["name"],
            "ip_address" => $validated["ip_address"],
            "config" => $lines,
            "is_active" => $validated["is_active"],
        ]);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Perangkat diperbarui") . '", { type: "success" })');
        $this->dispatch("updated");

        $this->customReset();
    }

    public function customReset()
    {
        $this->device = null;
        $this->reset(["name", "ip_address", "lines", "is_active"]);
        $this->lines = $this->getDefaultLineStructure();
    }

    public function addLine()
    {
        if (count($this->lines) < 10) {
            $this->lines[] = $this->getDefaultLineStructure()[0];
        }
    }

    public function removeLine($index)
    {
        if (count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function addMachine($lineIndex)
    {
        if (count($this->lines[$lineIndex]["list_mechine"]) < 20) {
            $this->lines[$lineIndex]["list_mechine"][] = [
                "name" => "",
                "addr_th_l" => "",
                "addr_th_r" => "",
                "addr_side_l" => "",
                "addr_side_r" => "",
                "addr_std_th_max" => "",
                "addr_std_th_min" => "",
                "addr_std_side_max" => "",
                "addr_std_side_min" => "",
            ];
        }
    }

    public function removeMachine($lineIndex, $machineIndex)
    {
        if (count($this->lines[$lineIndex]["list_mechine"]) > 1) {
            unset($this->lines[$lineIndex]["list_mechine"][$machineIndex]);
            $this->lines[$lineIndex]["list_mechine"] = array_values($this->lines[$lineIndex]["list_mechine"]);
        }
    }

    public function moveLine($fromIndex, $toIndex)
    {
        if ($fromIndex !== $toIndex && $fromIndex >= 0 && $toIndex >= 0 && $fromIndex < count($this->lines) && $toIndex < count($this->lines)) {
            $line = $this->lines[$fromIndex];
            array_splice($this->lines, $fromIndex, 1);
            array_splice($this->lines, $toIndex, 0, [$line]);
        }
    }

    public function delete()
    {
        if (!$this->device) {
            return;
        }

        Gate::authorize("manage", $this->device);

        $this->device->delete();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Perangkat dihapus") . '", { type: "success" })');
        $this->dispatch("updated");

        $this->customReset();
    }
};
?>

<div x-data="{
        draggingIndex: null,
        dragoverIndex: null,
        isDragging: false,
        startDrag(index) {
            this.draggingIndex = index
            this.isDragging = true
        },
        endDrag() {
            this.draggingIndex = null
            this.dragoverIndex = null
            this.isDragging = false
        },
        onDragOver(index) {
            if (this.draggingIndex !== null && this.draggingIndex !== index) {
                this.dragoverIndex = index
            }
        },
        onDrop(index) {
            if (this.draggingIndex !== null) {
                $wire.moveLine(this.draggingIndex, index)
                this.endDrag()
            }
        },
    }">
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Edit perangkat") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        
        <div class="flex mt-6 gap-x-3 items-end">
            <div>
                <label for="device-name-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama") }}</label>
                <x-text-input id="device-name-edit" wire:model="name" type="text" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                @error("name")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="device-ip-edit" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("IP Address") }}</label>
                <x-text-input id="device-ip-edit" wire:model="ip_address" type="text" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                @error("ip_address")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="py-1">
                <x-toggle id="area_multiple_toggle" wire:model="is_active" :disabled="Gate::denies('manage', InsDwpDevice::class)">
                    {{ __('Aktif') }}
                </x-toggle>
            </div>
        </div>

        <!-- Lines Section -->
        <div class="my-6">
            @foreach ($lines as $lineIndex => $line)
                <div class="border border-neutral-300 dark:border-neutral-700 rounded-lg p-4 mb-4"
                    x-on:dragstart="startDrag({{ $lineIndex }})"
                    x-on:dragend="endDrag"
                    x-on:dragover.prevent="onDragOver({{ $lineIndex }})"
                    x-on:drop.prevent="onDrop({{ $lineIndex }})"
                    :class="{ 'opacity-50': draggingIndex === {{ $lineIndex }}, 'opacity-30': dragoverIndex === {{ $lineIndex }} }">
                    
                    <!-- Line Header -->
                    <div class="flex gap-x-3 items-center mb-4">
                        <i class="icon-grip-horizontal cursor-move" draggable="true"></i>
                        <div class="grow">
                            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line Name") }}</label>
                            <x-text-input type="text" wire:model="lines.{{ $lineIndex }}.line" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            @error("lines.{$lineIndex}.line")
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        @can("manage", InsDwpDevice::class)
                            <x-text-button type="button" wire:click="removeLine({{ $lineIndex }})"><i class="icon-x"></i></x-text-button>
                        @endcan
                    </div>

                    <!-- DWP Alarm Section -->
                    <div class="bg-neutral-50 dark:bg-neutral-800 rounded p-3 mb-4">
                        <h3 class="font-medium text-sm mb-3 text-neutral-900 dark:text-neutral-100">{{ __("DWP Alarm Configuration") }}</h3>
                        <div class="grid grid-cols-5 gap-3">
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">DD1</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_dd_1" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">DD2</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_dd_2" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">DD3</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_dd_3" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">DD4</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_dd_4" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">DD5</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_dd_5" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                        </div>
                        <div class="grid grid-cols-5 gap-3 mt-3">
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">Reset</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_reset" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">Control</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_control" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">Counter</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_counter" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">Data Storage</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_data_storage" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                            <div>
                                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">Long Duration</label>
                                <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.dwp_alarm.addr_long_duration" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                            </div>
                        </div>
                    </div>

                    <!-- Machines Section -->
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <h3 class="font-medium text-sm text-neutral-900 dark:text-neutral-100">{{ __("Machines") }}</h3>
                            @can("manage", InsDwpDevice::class)
                                <x-secondary-button type="button" wire:click="addMachine({{ $lineIndex }})" :disabled="count($line['list_mechine']) >= 20">
                                    {{ __("Add Machine") }}
                                </x-secondary-button>
                            @endcan
                        </div>

                        @foreach ($line['list_mechine'] as $machineIndex => $machine)
                            <div class="bg-neutral-100 dark:bg-neutral-700 rounded p-3">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="grow">
                                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Machine Name") }}</label>
                                        <x-text-input type="text" wire:model="lines.{{ $lineIndex }}.list_mechine.{{ $machineIndex }}.name" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                                    </div>
                                    @can("manage", InsDwpDevice::class)
                                        <x-text-button type="button" wire:click="removeMachine({{ $lineIndex }}, {{ $machineIndex }})">
                                            <i class="icon-x"></i>
                                        </x-text-button>
                                    @endcan
                                </div>
                                
                                <div class="grid grid-cols-4 gap-3">
                                    <div>
                                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">T/H Left</label>
                                        <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.list_mechine.{{ $machineIndex }}.addr_th_l" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                                    </div>
                                    <div>
                                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">T/H Right</label>
                                        <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.list_mechine.{{ $machineIndex }}.addr_th_r" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                                    </div>
                                    <div>
                                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">Side Left</label>
                                        <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.list_mechine.{{ $machineIndex }}.addr_side_l" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                                    </div>
                                    <div>
                                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">Side Right</label>
                                        <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.list_mechine.{{ $machineIndex }}.addr_side_r" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-4 gap-3 mt-3">
                                    <div>
                                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">STD T/H Max</label>
                                        <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.list_mechine.{{ $machineIndex }}.addr_std_th_max" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                                    </div>
                                    <div>
                                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">STD T/H Min</label>
                                        <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.list_mechine.{{ $machineIndex }}.addr_std_th_min" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                                    </div>
                                    <div>
                                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">STD Side Max</label>
                                        <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.list_mechine.{{ $machineIndex }}.addr_std_side_max" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                                    </div>
                                    <div>
                                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">STD Side Min</label>
                                        <x-text-input type="number" wire:model="lines.{{ $lineIndex }}.list_mechine.{{ $machineIndex }}.addr_std_side_min" placeholder="0" :disabled="Gate::denies('manage', InsDwpDevice::class)" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @can("manage", InsDwpDevice::class)
            <div class="mt-6 flex justify-between">
                <x-secondary-button :disabled="count($lines) >= 10" type="button" wire:click="addLine">
                    {{ __("Tambah line") }}
                </x-secondary-button>
                <x-primary-button type="submit">
                    {{ __("Update") }}
                </x-primary-button>
            </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>