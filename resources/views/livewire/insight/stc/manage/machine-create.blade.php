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

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:20', 'unique:ins_stc_machines'],
            'name' => ['required', 'string', 'min:1', 'max:20'],
            'line' => ['required', 'integer', 'min:1', 'max:99'],
            'ip_address' => ['required', 'ipv4', 'unique:ins_stc_machines']
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
            'ip_address' => $validated['ip_address']
        ]);

        $machine->save();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Mesin dibuat') . '", { type: "success" })');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['code', 'name', 'line', 'ip_address']);
    }
};

?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Mesin baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="machine-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
            <x-text-input id="machine-code" wire:model="code" type="text" />
            @error('code')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  
        <div class="mt-6">
            <label for="machine-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="machine-name" wire:model="name" type="text" />
            @error('name')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  
        <div class="mt-6">
            <label for="machine-line" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
            <x-text-input id="machine-line" wire:model="line" type="number" />
            @error('line')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  
        <div class="mt-6">
            <label for="device-ip-address"
                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Alamat IP') }}</label>
            <x-text-input id="device-ip-address" wire:model="ip_address" type="text" />
            @error('ip_address')
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