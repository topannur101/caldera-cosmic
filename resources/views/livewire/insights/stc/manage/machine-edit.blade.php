<?php

use Livewire\Volt\Component;

use App\Models\InsStcMachine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

new class extends Component {
    
    public int $id;

    public string $code;
    public string $name = '';
    public int $line;
    public string $ip_address;
    public bool $is_at_adjusted = false;
    public array $at_adjust_strength = [];

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:20', Rule::unique('ins_stc_machines', 'code')->ignore($this->id ?? null)],
            'name' => ['required', 'string', 'min:1', 'max:20'], 
            'line' => ['required', 'integer', 'min:1', 'max:99'],   
            'ip_address' => ['required', 'ipv4', Rule::unique('ins_stc_machines', 'ip_address')->ignore($this->id ?? null)],
            'is_at_adjusted' => ['boolean'],
            'at_adjust_strength' => ['array'],
            'at_adjust_strength.upper' => ['array', 'size:8'],
            'at_adjust_strength.upper.*' => ['numeric', 'min:0', 'max:100'],
            'at_adjust_strength.lower' => ['array', 'size:8'],
            'at_adjust_strength.lower.*' => ['numeric', 'min:0', 'max:100'],
        ];
    }

    #[On('machine-edit')]
    public function loadMachine(int $id)
    {
        $machine = InsStcMachine::find($id);
        if ($machine) {
            $this->id       = $machine->id;
            $this->code     = $machine->code;
            $this->name     = $machine->name;
            $this->line     = $machine->line;
            $this->ip_address   = $machine->ip_address;
            $this->is_at_adjusted = $machine->is_at_adjusted;
            $this->at_adjust_strength = $machine->at_adjust_strength ?? ['upper' => [0,0,0,0,0,0,0,0], 'lower' => [0,0,0,0,0,0,0,0]];
        
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        $machine = InsStcMachine::find($this->id);
        $this->code = strtoupper(trim($this->code));
        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        if($machine) {
            Gate::authorize('manage', $machine);

            $machine->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'line' => $validated['line'],
                'ip_address' => $validated['ip_address'],
                'is_at_adjusted' => $validated['is_at_adjusted'],
                'at_adjust_strength' => $validated['at_adjust_strength']
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
        $this->reset(['code', 'name', 'line', 'ip_address', 'is_at_adjusted', 'at_adjust_strength']);
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
                {{ __('Mesin ') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="machine-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                <x-text-input id="machine-code" wire:model="code" type="text" :disabled="Gate::denies('manage', InsStcMachine::class)" />
                @error('code')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>  
            <div>
                <label for="machine-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                <x-text-input id="machine-name" wire:model="name" type="text" :disabled="Gate::denies('manage', InsStcMachine::class)" />
                @error('name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>    
            <div>
                <label for="machine-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                <x-text-input id="machine-line" wire:model="line" type="number" :disabled="Gate::denies('manage', InsStcMachine::class)" />
                @error('line')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>  
            <div>
                <label for="machine-ip-address"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Alamat IP') }}</label>
                <x-text-input id="machine-ip-address" wire:model="ip_address" :disabled="Gate::denies('manage', InsStcMachine::class)" type="text" />
                @error('ip_address')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>
        <div class="mt-6">
            <x-toggle wire:model="is_at_adjusted" :disabled="Gate::denies('manage', InsStcMachine::class)">{{ __('Aktifkan penyesuaian AT') }}</x-toggle>
            @error('is_at_adjusted')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        <div class="mt-6" x-show="$wire.is_at_adjusted">
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kekuatan Penyesuaian AT (%)') }}</label>
            <div class="mb-3">
                <label class="block px-3 mb-1 text-xs text-neutral-500 uppercase">{{ __('Upper') }}</label>
                <div class="grid grid-cols-8 gap-1">
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.0" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.1" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.2" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.3" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.4" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.5" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.6" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.upper.7" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                </div>
            </div>
            <div>
                <label class="block px-3 mb-1 text-xs text-neutral-500 uppercase">{{ __('Lower') }}</label>
                <div class="grid grid-cols-8 gap-1">
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.0" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.1" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.2" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.3" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.4" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.5" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.6" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                    <x-text-input-t class="text-center" placeholder="0" wire:model="at_adjust_strength.lower.7" :disabled="Gate::denies('manage', InsStcMachine::class)" type="number" min="0" max="100" />
                </div>
            </div>
            @error('at_adjust_strength.upper.*')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
            @error('at_adjust_strength.lower.*')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>
        @can('manage', InsStcMachine::class)
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
