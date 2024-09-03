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

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:20', Rule::unique('ins_stc_machines', 'code')->ignore($this->id ?? null)],
            'name' => ['required', 'string', 'min:1', 'max:20'], 
            'line' => ['required', 'integer', 'min:1', 'max:99'],        
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
                'line' => $validated['line']
            ]);

            $this->js('$dispatch("close")');
            $this->js('notyfSuccess("' . __('Mesin diperbarui') . '")');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset(['code', 'name', 'line']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('notyfError("' . __('Tidak ditemukan') . '")');
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
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="machine-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nomor') }}</label>
            <x-text-input id="machine-code" wire:model="code" type="text" :disabled="Gate::denies('manage', InsStcMachine::class)" />
            @error('code')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  
        <div class="mt-6">
            <label for="machine-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="machine-name" wire:model="name" type="text" :disabled="Gate::denies('manage', InsStcMachine::class)" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>    
        <div class="mt-6">
            <label for="machine-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
            <x-text-input id="machine-line" wire:model="line" type="number" :disabled="Gate::denies('manage', InsStcMachine::class)" />
            @error('line')
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
