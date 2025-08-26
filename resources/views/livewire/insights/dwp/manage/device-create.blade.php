<?php

use Livewire\Volt\Component;
use App\Models\InsDwpDevice;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public string $name = "";
    public string $ip_address = "";
    public array $lines = [["line" => "", "addr_counter" => "", "addr_reset" => ""]];
    public bool $is_active = true;

    public function rules()
    {
        return [
            "name" => ["required", "string", "max:255"],
            "ip_address" => ["required", "ip", "unique:ins_dwp_devices,ip_address"],
            "lines" => ["required", "array", "min:1", "max:10"],
            "lines.*.line" => ["required", "string", "max:50"],
            "lines.*.addr_counter" => ["required", "integer", "min:0", "max:65535"],
            "lines.*.addr_reset" => ["required", "integer", "min:0", "max:65535"],
            "is_active" => ["boolean"],
        ];
    }

    public function save()
    {
        $device = new InsDwpDevice();
        Gate::authorize("manage", $device);

        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        // Process lines to ensure proper format
        $lines = array_map(function ($line) {
            return [
                "line" => strtoupper(trim($line["line"])),
                "addr_counter" => (int) $line["addr_counter"],
                "addr_reset" => (int) $line["addr_reset"],
            ];
        }, $validated["lines"]);

        $device->fill([
            "name" => $validated["name"],
            "ip_address" => $validated["ip_address"],
            "config" => $lines,
            "is_active" => $validated["is_active"],
        ]);

        $device->save();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __("Perangkat dibuat") . '", { type: "success" })');
        $this->dispatch("updated");

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(["name", "ip_address", "lines", "is_active"]);
        $this->lines = [["line" => "", "addr_counter" => "", "addr_reset" => ""]];
    }

    public function addLine()
    {
        if (count($this->lines) < 10) {
            $this->lines[] = ["line" => "", "addr_counter" => "", "addr_reset" => ""];
        }
    }

    public function removeLine($index)
    {
        if (count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
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
};
?>

<div
    x-data="{
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
    }"
>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Perangkat baru") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        
        <div class="flex mt-6 gap-x-3 items-end">
            <div>
                <label for="device-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama") }}</label>
                <x-text-input id="device-name" wire:model="name" type="text" />
                @error("name")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="device-ip" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("IP Address") }}</label>
                <x-text-input id="device-ip" wire:model="ip_address" type="text" />
                @error("ip_address")
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="py-1">
                <x-toggle id="area_multiple_toggle" wire:model="is_active" ::checked="is_active" :disabled="Gate::denies('manage', InsDwpDevice::class)">
                    {{ __('Aktif') }}
                </x-toggle>
            </div>
        </div>

        <div class="my-6">
            <div class="grid grid-cols-1 text-neutral-500">
                <div class="flex gap-x-3 items-center">
                    <i class="icon-grip-horizontal opacity-0"></i>
                    <div class="grow grid grid-cols-3 gap-x-3 text-center">
                        <div class="text-xs uppercase">{{ __('Line') }}</div>
                        <div class="text-xs uppercase">{{ __('Counter') }}</div>
                        <div class="text-xs uppercase">{{ __('Reset') }}</div>
                    </div>
                    <i class="icon-x opacity-0"></i>
                </div>                
            </div>
            @foreach ($lines as $index => $line)
                <div
                    class="mt-2"
                    x-on:dragstart="startDrag({{ $index }})"
                    x-on:dragend="endDrag"
                    x-on:dragover.prevent="onDragOver({{ $index }})"
                    x-on:drop.prevent="onDrop({{ $index }})"
                    :class="{ 'opacity-50': draggingIndex === {{ $index }}, 'opacity-30': dragoverIndex === {{ $index }} }"
                >
                    <div class="grid grid-cols-1 gap-y-2">
                        <div class="flex gap-x-3 items-center">
                            <i class="icon-grip-horizontal cursor-move" draggable="true"></i>
                            <x-text-input type="text" wire:model="lines.{{ $index }}.line" />
                            <x-text-input type="number" wire:model="lines.{{ $index }}.addr_counter" placeholder="0" />
                            <x-text-input type="number" wire:model="lines.{{ $index }}.addr_reset" placeholder="0" />
                            <x-text-button type="button" wire:click="removeLine({{ $index }})"><i class="icon-x"></i></x-text-button>
                        </div>
                    </div>
                    <div class="px-3">
                        @error("lines.{$index}.line")
                            <x-input-error messages="{{ $message }}" class="mt-2" />
                        @enderror

                        @error("lines.{$index}.addr_counter")
                            <x-input-error messages="{{ $message }}" class="mt-2" />
                        @enderror

                        @error("lines.{$index}.addr_reset")
                            <x-input-error messages="{{ $message }}" class="mt-2" />
                        @enderror
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex justify-between">
            <x-secondary-button :disabled="count($lines) >= 10" type="button" wire:click="addLine">{{ __("Tambah line") }}</x-secondary-button>
            <x-primary-button type="submit">
                {{ __("Simpan") }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>