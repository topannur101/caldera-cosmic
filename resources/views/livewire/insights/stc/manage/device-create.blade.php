<?php

use Livewire\Volt\Component;
use App\Models\InsStcDevice;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

new class extends Component {

    public string $code = '';
    public string $name = '';

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:20', 'unique:ins_stc_devices'],
            'name' => ['required', 'string', 'min:1', 'max:20'],
        ];
    }

    public function save()
    {
        $device = new InsStcDevice;
        Gate::authorize('manage', $device);

        $this->name = strtoupper(trim($this->name));
        $validated = $this->validate();

        $device->fill([
            'code' => $validated['code'],
            'name' => $validated['name'],
        ]);

        $device->save();

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Alat ukur dibuat') . '", { type: "success" })');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['code', 'name']);
    }
};
?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Alat ukur baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mt-6">
            <label for="device-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
            <x-text-input id="device-code" wire:model="code" type="text" />
            @error('code')
                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
        </div>  
        <div class="mt-6">
            <label for="device-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
            <x-text-input id="device-name" wire:model="name" type="text" />
            @error('name')
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