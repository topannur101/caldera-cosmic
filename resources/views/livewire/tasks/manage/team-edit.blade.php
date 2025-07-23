<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

use App\Models\TskTeam;

new class extends Component {
    public int $id;
    public string $name;
    public string $short_name;
    public string $desc;
    public bool $is_active;

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:10', Rule::unique('tsk_teams', 'short_name')->ignore($this->id ?? null)],
            'desc' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    #[On('team-edit')]
    public function loadTeam(int $id)
    {
        $team = TskTeam::find($id);
        if ($team) {
            $this->id = $team->id;
            $this->name = $team->name;
            $this->short_name = $team->short_name;
            $this->desc = $team->desc ?? '';
            $this->is_active = $team->is_active;
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        Gate::authorize('superuser');
        $this->validate();

        $team = TskTeam::find($this->id);
        if ($team) {
            $team->update([
                'name' => $this->name,
                'short_name' => $this->short_name,
                'desc' => $this->desc,
                'is_active' => $this->is_active,
            ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Tim diperbarui') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }

    public function delete()
    {
        Gate::authorize('superuser');

        $team = TskTeam::find($this->id);
        if ($team) {
            // Check if team has projects - prevent deletion
            if ($team->tsk_projects()->count() > 0) {
                $this->js('toast("' . __('Tim tidak dapat dihapus karena memiliki proyek') . '", { type: "danger" })');
                return;
            }

            // Delete team auths first
            $team->tsk_auths()->delete();
            
            // Delete team
            $team->delete();

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Tim dihapus') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
        }
        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['id', 'name', 'short_name', 'desc', 'is_active']);
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
                {{ __('Tim ') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mb-6">
            <div class="mt-6">
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('ID') }}</label>
                <div class="px-3">{{ $id ?? '?' }}</div>
            </div>
            <div class="mt-6">
                <label for="team-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama Tim') }}</label>
                <x-text-input id="team-name" wire:model="name" :disabled="Gate::denies('superuser')" type="text" />
                @error('name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="team-short-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama Singkat') }}</label>
                <x-text-input id="team-short-name" wire:model="short_name" :disabled="Gate::denies('superuser')" type="text" maxlength="10" />
                @error('short_name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <label for="team-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Deskripsi') }}</label>
                <textarea id="team-desc" wire:model="desc" :disabled="{{ Gate::denies('superuser') ? 'true' : 'false' }}" rows="3" class="block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm"></textarea>
                @error('desc')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div class="mt-6">
                <x-checkbox id="edit-is-active" wire:model="is_active" :disabled="Gate::denies('superuser')">{{ __('Tim Aktif') }}</x-checkbox>
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