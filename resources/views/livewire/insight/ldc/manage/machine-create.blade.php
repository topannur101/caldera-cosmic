<?php

use Livewire\Volt\Component;
use App\Models\InsLdcMachine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {

    public string $code = '';

    public function rules()
    {
        return [
            'code' => ['size:2', 'unique:ins_ldc_machines'],
        ];
    }

    public function save()
    {
        $machine = new InsLdcMachine;
        Gate::authorize('manage', $machine);

        $this->code = strtoupper(trim($this->code));
        $validated = $this->validate();

        $machine->fill([
            'code' => $validated['code'],
        ]);

        $machine->save();

        $this->js('$dispatch("close")');
        $this->js('notyfSuccess("' . __('Mesin dibuat') . '")');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['code']);
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
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>