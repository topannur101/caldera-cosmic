<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;

use App\Models\User;
use App\Models\TskAuth;
use App\Models\TskTeam;
use Livewire\Attributes\Renderless;
use Illuminate\Support\Facades\Gate;

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
            'perms.*' => ['string']
        ];
    }

    public function with(): array
    {
        return [
            // TODO: Add permission check
            'can_manage' => true, // TODO: Check team-manage permission
            'teams' => TskTeam::where('is_active', true)->get(),
        ];
    }

    public function save()
    {
        // TODO: Add permission check
        // Gate::authorize('team-manage');

        $this->userq = trim($this->userq);
        $user = $this->userq ? User::where('emp_id', $this->userq)->first() : null;
        $this->user_id = $user->id ?? 0;
        $this->validate();

        // Check if user already has auth for this team
        $existingAuth = TskAuth::where('user_id', $this->user_id)
                              ->where('tsk_team_id', $this->tsk_team_id)
                              ->first();
        
        if ($existingAuth) {
            $this->js('toast("' . __('Pengguna sudah memiliki wewenang di tim ini') . '", { type: "danger" })');
        } else {
            // TODO: Create auth
            // TskAuth::create([
            //     'user_id' => $this->user_id,
            //     'tsk_team_id' => $this->tsk_team_id,
            //     'perms' => json_encode($this->perms),
            //     'is_active' => true,
            // ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Wewenang dibuat') . '", { type: "success" })');
            $this->dispatch('updated');
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
    @if($can_manage)
    <div class="mb-6">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">{{ __('Buat Wewenang Baru') }}</h2>
    </div>

    <form wire:submit="save" class="space-y-6">
        <!-- User Selection -->
        <div>
            <x-input-label for="userq" :value="__('ID Pengguna')" />
            <x-text-input wire:model="userq" id="userq" class="block mt-1 w-full" type="text" required autofocus placeholder="emp001" />
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
                <label class="flex items-center">
                    <input wire:model="perms" type="checkbox" value="task-assign" class="rounded border-neutral-300 text-caldy-600 shadow-sm focus:ring-caldy-500 dark:border-neutral-600 dark:bg-neutral-900 dark:focus:ring-caldy-600 dark:focus:ring-offset-neutral-800">
                    <span class="ml-2 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tugaskan Tugas') }} - {{ __('Menugaskan tugas ke anggota tim lain') }}</span>
                </label>
                <label class="flex items-center">
                    <input wire:model="perms" type="checkbox" value="task-manage" class="rounded border-neutral-300 text-caldy-600 shadow-sm focus:ring-caldy-500 dark:border-neutral-600 dark:bg-neutral-900 dark:focus:ring-caldy-600 dark:focus:ring-offset-neutral-800">
                    <span class="ml-2 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Kelola Tugas') }} - {{ __('Membuat dan menghapus tugas di tim/proyek manapun') }}</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('perms')" class="mt-2" />
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-neutral-200 dark:border-neutral-700">
            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                {{ __('Batal') }}
            </x-secondary-button>
            <x-primary-button type="submit">
                <i class="icon-save mr-2"></i>{{ __('Simpan Wewenang') }}
            </x-primary-button>
        </div>
    </form>
    @else
    <div class="text-center py-8">
        <p class="text-neutral-500">{{ __('Anda tidak memiliki wewenang untuk mengelola tim') }}</p>
    </div>
    @endif
</div>