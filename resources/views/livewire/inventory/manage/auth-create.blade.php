<?php

use Livewire\Volt\Component;

use App\Models\User;
use App\Models\InvAuth;
use App\Models\InvArea;
use Livewire\Attributes\Renderless;
use Illuminate\Support\Facades\Gate;

new #[Layout("layouts.app")] class extends Component {
    public string $userq = "";
    public int $user_id = 0;
    public int $area_id = 0;
    public array $actions = [];
    public array $areas = [];

    public function rules()
    {
        return [
            "user_id" => ["required", "gt:0", "integer", "unique:ins_omv_auths"],
            "area_id" => ["required", "exists:inv_areas,id"],
            "actions" => ["array"],
            "actions.*" => ["string"],
        ];
    }

    public function mount()
    {
        $this->areas = InvArea::all()->toArray();
    }

    public function with(): array
    {
        return [
            "is_superuser" => Gate::allows("superuser"),
        ];
    }

    public function save()
    {
        Gate::authorize("superuser");

        $this->userq = trim($this->userq);
        $user = $this->userq ? User::where("emp_id", $this->userq)->first() : null;
        $this->user_id = $user->id ?? 0;
        $this->validate();

        if ($this->user_id == 1) {
            $this->js('toast("' . __("Superuser sudah memiliki wewenang penuh") . '", { type: "danger" })');
        } else {
            InvAuth::create([
                "user_id" => $this->user_id,
                "inv_area_id" => $this->area_id,
                "actions" => json_encode($this->actions),
            ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __("Wewenang dibuat") . '", { type: "success" })');
            $this->dispatch("updated");
        }
        $this->customReset();
    }

    #[Renderless]
    public function updatedUserq()
    {
        $this->dispatch("userq-updated", $this->userq);
    }

    public function customReset()
    {
        $this->reset(["userq", "user_id", "actions"]);
    }
};

?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Wewenang baru") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="grid grid-cols-1 gap-y-3 mt-3">
            <div x-data="{ open: false, userq: @entangle("userq").live }" x-on:user-selected="userq = $event.detail.user_emp_id; open = false">
                <div x-on:click.away="open = false">
                    <x-text-input-icon
                        x-model="userq"
                        icon="icon-user"
                        x-on:change="open = true"
                        x-ref="userq"
                        x-on:focus="open = true"
                        id="inv-user"
                        class="mt-3"
                        type="text"
                        autocomplete="off"
                        placeholder="{{ __('Pengguna') }}"
                    />
                    <div class="relative" x-show="open" x-cloak>
                        <div class="absolute top-1 left-0 w-full">
                            <livewire:layout.user-select wire:key="user-select" />
                        </div>
                    </div>
                </div>
                <div wire:key="error-user_id">
                    @error("user_id")
                        <x-input-error messages="{{ $message }}" class="mt-2" />
                    @enderror
                </div>
            </div>
            <div>
                <x-select wire:model="area_id" class="w-full">
                    <option value=""></option>
                    @foreach ($areas as $area)
                        <option value="{{ $area["id"] }}">{{ $area["name"] }}</option>
                    @endforeach
                </x-select>
                <div wire:key="error-area_id">
                    @error("area_id")
                        <x-input-error messages="{{ $message }}" class="mt-2" />
                    @enderror
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-y-3 mt-6">
            <div>{{ __("Barang") }}</div>
            <x-checkbox id="{{ $auth->id ?? 'new'}}-item-manage" :disabled="!$is_superuser" wire:model="actions" value="item-manage">
                {{ __("Buat, perbarui, dan unduh barang ") }}
            </x-checkbox>
        </div>
        <div class="grid grid-cols-1 gap-y-3 mt-6">
            <div>{{ __("Sirkulasi") }}</div>
            <x-checkbox id="{{ $auth->id ?? 'new'}}-circ-create" :disabled="!$is_superuser" wire:model="actions" value="circ-create">{{ __("Buat sirkulasi") }}</x-checkbox>
            <x-checkbox id="{{ $auth->id ?? 'new'}}-circ-eval" :disabled="!$is_superuser" wire:model="actions" value="circ-eval">
                {{ __("Edit dan evaluasi sirkulasi (setujui/tolak)") }}
            </x-checkbox>
        </div>
        <div class="mt-6 flex justify-end items-end">
            <x-primary-button type="submit">
                {{ __("Buat") }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>
