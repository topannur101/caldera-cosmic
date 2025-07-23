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
    public int $id;
    public string $user_name;
    public string $user_emp_id;
    public string $user_photo;
    public string $team_name;
    public string $team_short_name;
    public array $perms;

    public function rules()
    {
        return [
            'perms' => ['array'],
            'perms.*' => ['string'],
        ];
    }

    #[On('auth-edit')]
    public function loadAuth(int $id)
    {
        // TODO: Load actual auth data
        // $auth = TskAuth::with(['user', 'tsk_team'])->find($id);
        // if ($auth) {
        //     $this->id = $auth->id;
        //     $this->user_name = $auth->user->name;
        //     $this->user_emp_id = $auth->user->emp_id;
        //     $this->user_photo = $auth->user->photo ?? '';
        //     $this->team_name = $auth->tsk_team->name;
        //     $this->team_short_name = $auth->tsk_team->short_name;
        //     $this->perms = json_decode($auth->perms ?? '[]', true);
        //     $this->resetValidation();
        // } else {
        //     $this->handleNotFound();
        // }

        // Placeholder data
        $this->id = $id;
        $this->user_name = 'Demo User';
        $this->user_emp_id = 'emp001';
        $this->user_photo = '';
        $this->team_name = 'Digitalization';
        $this->team_short_name = 'DGT';
        $this->perms = ['task-assign'];
        $this->resetValidation();
    }

    public function with(): array
    {
        return [
            // TODO: Add permission check
            'can_manage' => true, // TODO: Check team-manage permission
        ];
    }

    public function save()
    {
        // TODO: Add permission check
        // Gate::authorize('team-manage');
        $this->validate();

        // TODO: Update auth
        // $auth = TskAuth::find($this->id);
        // if ($auth) {
        //     $auth->perms = json_encode($this->perms);
        //     $auth->update();

        //     $this->js('$dispatch("close")');
        //     $this->js('toast("' . __('Wewenang diperbarui') . '", { type: "success" })');
        //     $this->dispatch('updated');
        // } else {
        //     $this->handleNotFound();
        //     $this->customReset();
        // }

        // Placeholder success
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Wewenang diperbarui') . '", { type: "success" })');
        $this->dispatch('updated');
    }

    public function delete()
    {
        // TODO: Add permission check
        // Gate::authorize('team-manage');

        // TODO: Delete auth
        // $auth = TskAuth::find($this->id);
        // if ($auth) {
        //     $auth->delete();

        //     $this->js('$dispatch("close")');
        //     $this->js('toast("' . __('Wewenang dicabut') . '", { type: "success" })');
        //     $this->dispatch('updated');
        // } else {
        //     $this->handleNotFound();
        // }

        // Placeholder success
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Wewenang dicabut') . '", { type: "success" })');
        $this->dispatch('updated');
        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['id', 'user_name', 'user_emp_id', 'user_photo', 'team_name', 'team_short_name', 'perms']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }
};

?>

<div class="p-6">
    @if($can_manage)
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Edit Wewenang') }}</h2>
            <x-text-button type="button" x-on:click="$dispatch('close')">
                <i class="icon-x"></i>
            </x-text-button>
        </div>
    </div>

    <!-- User Info -->
    <div class="mb-6 p-4 bg-neutral-50 dark:bg-neutral-800 rounded-lg">
        <div class="flex items-center">
            <div class="w-12 h-12 mr-4 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                @if ($user_photo)
                    <img class="w-full h-full object-cover dark:brightness-75"
                        src="{{ '/storage/users/' . $user_photo }}" />
                @else
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                        viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                        <path
                            d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                    </svg>
                @endif
            </div>
            <div>
                <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $user_name }}</div>
                <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ $user_emp_id }}</div>
                <div class="text-sm text-neutral-500">
                    <span class="font-medium">{{ $team_name }}</span> ({{ $team_short_name }})
                </div>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
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
        <div class="flex items-center justify-between pt-6 border-t border-neutral-200 dark:border-neutral-700">
            <x-danger-button type="button" wire:click="delete" wire:confirm="{{ __('Yakin ingin mencabut wewenang pengguna ini?') }}">
                <i class="icon-trash mr-2"></i>{{ __('Cabut Wewenang') }}
            </x-danger-button>
            
            <div class="flex space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Batal') }}
                </x-secondary-button>
                <x-primary-button type="submit">
                    <i class="icon-save mr-2"></i>{{ __('Simpan Perubahan') }}
                </x-primary-button>
            </div>
        </div>
    </form>
    @else
    <div class="text-center py-8">
        <p class="text-neutral-500">{{ __('Anda tidak memiliki wewenang untuk mengelola tim') }}</p>
    </div>
    @endif
</div>