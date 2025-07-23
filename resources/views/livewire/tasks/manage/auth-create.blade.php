<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

use App\Models\User;
use App\Models\TskAuth;
use App\Models\TskTeam;

new class extends Component {
    public string $userq = '';
    public int $user_id = 0;
    public int $tsk_team_id = 0;
    public array $perms = [];

    public function rules()
    {
        return [
            'user_id' => ['required', 'gt:0', 'integer'],
            'tsk_team_id' => ['required', 'gt:0', 'integer', 'exists:tsk_teams,id'],
            'perms' => ['array'],
            'perms.*' => ['string', Rule::in(array_keys(TskAuth::getAvailablePermissions()))]
        ];
    }

    public function with(): array
    {
        return [
            'is_superuser' => Gate::allows('superuser'),
            'teams' => TskTeam::where('is_active', true)->get(),
            'available_permissions' => TskAuth::getAvailablePermissions(),
        ];
    }

    public function save()
    {
        // Authorize using superuser gate like OMV
        Gate::authorize('superuser');

        $this->userq = trim($this->userq);
        $user = $this->userq ? User::where('emp_id', $this->userq)->first() : null;
        $this->user_id = $user->id ?? 0;
        
        $this->validate();

        // Prevent creating auth for superuser like OMV pattern
        if ($this->user_id == 1) {
            $this->js('toast("' . __('Superuser sudah memiliki wewenang penuh') . '", { type: "danger" })');
        } else {
            // Check if user already has auth for this team
            $existingAuth = TskAuth::where('user_id', $this->user_id)
                                  ->where('tsk_team_id', $this->tsk_team_id)
                                  ->first();
            
            if ($existingAuth) {
                $this->js('toast("' . __('Pengguna sudah memiliki wewenang di tim ini') . '", { type: "danger" })');
            } else {
                TskAuth::create([
                    'user_id' => $this->user_id,
                    'tsk_team_id' => $this->tsk_team_id,
                    'perms' => $this->perms,
                    'is_active' => true,
                ]);

                $this->js('$dispatch("close")');
                $this->js('toast("' . __('Wewenang dibuat') . '", { type: "success" })');
                $this->dispatch('updated');
            }
        }
        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['userq', 'user_id', 'tsk_team_id', 'perms']);
    }
};

?>

<div class="p-6">
    <div class="mb-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Wewenang baru') }}</h2>
            <x-text-button type="button" x-on:click="$dispatch('close')">
                <i class="icon-x"></i>
            </x-text-button>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <!-- User Selection -->
        <div wire:key="user-select" x-data="{ open: false, userq: @entangle('userq').live }"
            x-on:user-selected="userq = $event.detail.user_emp_id; open = false">
            <x-input-label for="userq" :value="__('ID Pengguna')" />
            <div x-on:click.away="open = false">
                <x-text-input-icon x-model="userq" icon="icon-user" x-on:change="open = true"
                    x-ref="userq" x-on:focus="open = true" id="userq" class="mt-1 block w-full" type="text"
                    autocomplete="off" placeholder="{{ __('emp001') }}" />
                <div class="relative" x-show="open" x-cloak>
                    <div class="absolute top-1 left-0 w-full">
                        <livewire:layout.user-select />
                    </div>
                </div>
            </div>
            <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
            <p class="mt-1 text-sm text-neutral-500">{{ __('Masukkan employee ID pengguna') }}</p>
        </div>

        <!-- Team Selection -->
        <div>
            <x-input-label for="tsk_team_id" :value="__('Tim')" />
            <select wire:model="tsk_team_id" id="tsk_team_id" class="block mt-1 w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm" required>
                <option value="">{{ __('Pilih Tim') }}</option>
                @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }} ({{ $team->short_name }})</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('tsk_team_id')" class="mt-2" />
        </div>

        <!-- Permissions -->
        <div>
            <x-input-label :value="__('Wewenang')" />
            <div class="mt-2 space-y-2">
                @foreach($available_permissions as $perm => $label)
                <label class="flex items-center">
                    <input wire:model="perms" type="checkbox" value="{{ $perm }}" class="rounded border-neutral-300 text-caldy-600 shadow-sm focus:ring-caldy-500 dark:border-neutral-600 dark:bg-neutral-900 dark:focus:ring-caldy-600 dark:focus:ring-offset-neutral-800">
                    <span class="ml-2 text-sm text-neutral-600 dark:text-neutral-400">{{ $label }}</span>
                </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('perms')" class="mt-2" />
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-end pt-6 border-t border-neutral-200 dark:border-neutral-700">
            <x-primary-button type="submit">
                {{ __('Buat') }}
            </x-primary-button>
        </div>
    </form>

    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>