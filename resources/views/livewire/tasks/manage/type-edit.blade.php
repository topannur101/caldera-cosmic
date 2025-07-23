<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

use App\Models\TskType;

new class extends Component {
    public int $id;
    public string $name;
    public bool $is_active;

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('tsk_types', 'name')->ignore($this->id ?? null)],
            'is_active' => ['boolean'],
        ];
    }

    #[On('type-edit')]
    public function loadType(int $id)
    {
        $type = TskType::find($id);
        if ($type) {
            $this->id = $type->id;
            $this->name = $type->name;
            $this->is_active = $type->is_active;
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        Gate::authorize('superuser');
        $this->validate();

        $type = TskType::find($this->id);
        if ($type) {
            $type->update([
                'name' => $this->name,
                'is_active' => $this->is_active,
            ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Tipe diperbarui') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function delete()
    {
        Gate::authorize('superuser');

        $type = TskType::find($this->id);
        if ($type) {
            if (!$type->canBeDeleted()) {
                $this->js('toast("' . __('Tipe tidak dapat dihapus karena masih digunakan') . '", { type: "danger" })');
                return;
            }

            $type->delete();

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Tipe dihapus') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
        }
        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['id', 'name', 'is_active']);
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
                {{ __('Tipe ') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mb-6">
            <div class="mt-6">
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('ID') }}</label>
                <div class="px-3">{{ $id ?? '?' }}</div>
            </div>
            <div class="mt-6">
                <label for="type-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                <x-text-input id="type-name" wire:model="name" :disabled="Gate::denies('superuser')" type="text" />
                @error('name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <x-checkbox id="edit-is-active" wire:model="is_active" :disabled="Gate::denies('superuser')">{{ __('Aktif') }}</x-checkbox>
            </div>
        </div>
        @can('superuser')
        <div class="flex justify-between items-end">
            <div>
                <x-text-button type="button" class="uppercase text-xs text-red-500" wire:click="delete" wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}">
                    {{ __('Hapus') }}
                </x-text-button>
            </div>
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
        @endcan
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>