<?php

use Livewire\Volt\Component;
use App\Models\InsStcMachine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {

    public string $code = '';
    public string $name = '';
    public int $line;
    public string $ip_address;
    public bool $is_at_adjusted = false;
    public array $at_adjust_strength = ['upper' => [0,0,0,0,0,0,0,0], 'lower' => [0,0,0,0,0,0,0,0]];

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:20', 'unique:ins_stc_machines'],
            'name' => ['required', 'string', 'min:1', 'max:20'],
            'line' => ['required', 'integer', 'min:1', 'max:99'],
            'ip_address' => ['required', 'ipv4', 'unique:ins_stc_machines'],
            'is_at_adjusted' => ['boolean'],
            'at_adjust_strength' => ['array'],
            'at_adjust_strength.upper' => ['array', 'size:8'],
            'at_adjust_strength.upper.*' => ['numeric', 'min:0', 'max:100'],
            'at_adjust_strength.lower' => ['array', 'size:8'],
            'at_adjust_strength.lower.*' => ['numeric', 'min:0', 'max:100'],
        ];
    }

    public function save()
    {
        $machine = new InsStcMachine;
        Gate::authorize('manage', $machine);

        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        $machine->fill([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'line' => $validated['line'],
            'ip_address' => $validated['ip_address'],
            'is_at_adjusted' => $validated['is_at_adjusted'],
            'at_adjust_strength' => $validated['at_adjust_strength']
        ]);

        $machine->save();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Mesin dibuat') . '", { type: "success" })');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['code', 'name', 'line', 'ip_address', 'is_at_adjusted', 'at_adjust_strength']);
    }
};

?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Mesin baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="machine-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                <x-text-input id="machine-code" wire:model="code" type="text" />
                @error('code')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>  
            <div>
                <label for="machine-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                <x-text-input id="machine-name" wire:model="name" type="text" />
                @error('name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>  
            <div>
                <label for="machine-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                <x-text-input id="machine-line" wire:model="line" type="number" />
                @error('line')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>  
            <div>
                <label for="device-ip-address"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Alamat IP') }}</label>
                <x-text-input id="device-ip-address" wire:model="ip_address" type="text" />
                @error('ip_address')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>
        <div class="mt-6">
            <x-toggle wire:model="is_at_adjusted">{{ __('Aktifkan penyesuaian AT') }}</x-toggle>
            @error('is_at_adjusted')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6" x-show="$wire.is_at_adjusted">
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kekuatan Penyesuaian AT (%)') }}</label>
            <div class="mb-3">
                <label class="block px-3 mb-1 text-xs text-neutral-500">{{ __('Upper') }}</label>
                <div class="grid grid-cols-8 gap-1">
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.0" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.1" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.2" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.3" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.4" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.5" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.6" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.7" type="number" min="0" max="100" />
                </div>
            </div>
            <div>
                <label class="block px-3 mb-1 text-xs text-neutral-500">{{ __('Lower') }}</label>
                <div class="grid grid-cols-8 gap-1">
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.0" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.1" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.2" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.3" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.4" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.5" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.6" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.7" type="number" min="0" max="100" />
                </div>
            </div>
            @error('at_adjust_strength.upper.*')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
            @error('at_adjust_strength.lower.*')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>