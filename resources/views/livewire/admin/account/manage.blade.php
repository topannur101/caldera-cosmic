<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;

use App\Models\User;

new #[Layout('layouts.app')] 
class extends Component {

    public string $userq;
    public string $mode = '';
    public int $user_id;

    public int $password_option = 0;

    public string $user_name;
    public string $user_emp_id;

    #[Renderless]
    public function updatedUserq()
    {
        $this->dispatch('userq-updated', $this->userq);
    }

    public function resetPassword()
    {
        Gate::authorize('superuser');

        $validator = Validator::make(
            ['password_option' => $this->password_option ],
            ['password_option' => 'required|integer|lte:1|gte:1'],
            [
                'min' => __('Kata sandi wajib dipilih')
            ]
        );

        if ($validator->fails()) {

            $errors = $validator->errors();
            $error = $errors->first();
            $this->js('toast("'.$error.'", { type: "danger" })'); 

        } else {
            $user = User::find($this->user_id);
            $password = '';
            switch ($this->password_option) {
                
                // opop1212
                case 1:
                    $password = '$2y$12$0KKCawG6HLkTJP3BPUJ5xupcpSGiYdL2CV13Eku8eID48YFN2L.aC';
                    break;
            }

            if ($user && $password) {  
                $user->update([
                    'password' => $password
                ]);               

                $this->js('toast("' . __('Kata sandi diperbarui') . '", { type: "success" })');
                $this->reset(['mode', 'password_option']);
            }            
        }
    }

    public function updateUser()
    {
        Gate::authorize('superuser');
        dd($this->user_id);
        $this->reset(['user_name', 'user_edit']);
    }

    public function deactivateUser()
    {
        Gate::authorize('superuser');
        dd($this->user_id);
    }

};

?>
<x-slot name="title">{{ __('Akun') . ' — ' . __('Admin') }}</x-slot>

<x-slot name="header">
    <x-nav-admin>{{ __('Akun') }}</x-nav-admin>
</x-slot>

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 text-neutral-600 dark:text-neutral-400">
        <div x-data="{ 
                open: false, 
                userq: @entangle('userq').live,
                user_id: @entangle('user_id'),
                user_name: '',
                user_emp_id: '',
                user_photo: '',
                user_is_active: '',
                user_seen_at: '',
                mode: @entangle('mode'),
                userFill(event) {
                    this.open = false;
                    this.userq = '';

                    this.user_id = event.detail.user_id;
                    this.user_name = event.detail.user_name;
                    this.user_emp_id = event.detail.user_emp_id;
                    this.user_photo = event.detail.user_photo;
                    this.user_is_active = event.detail.user_is_active;
                    this.user_seen_at = event.detail.user_seen_at;
                },
                userReset() {
                    this.userq = '';
                    this.mode = '';

                    this.user_id = '';
                    this.user_name = '';
                    this.user_emp_id = '';
                    this.user_photo = '';
                    this.user_is_active = '';
                    this.user_seen_at = '';
                    this.$nextTick(() => {
                        setTimeout(() => this.$refs.userq.focus(), 100);
                    });
                }
            }"
            x-on:user-selected="userFill($event);"
            x-init="userReset()">
            <div class="max-w-md mx-auto">
                <div x-show="!user_id" x-on:click.away="open = false" class="px-3 sm:px-0">
                    {{-- <label for="cal-user"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengguna') }}</label> --}}
                    <x-text-input-icon x-model="userq" icon="icon-user" x-on:change="open = true"
                        x-ref="userq" x-on:focus="open = true" id="cal-user" type="text"
                        autocomplete="off" placeholder="{{ __('Pengguna') }}" />
                    <div class="relative" x-show="open" x-cloak>
                        <div class="absolute top-1 left-0 w-full z-10">
                            <livewire:layout.user-select />
                        </div>
                    </div>
                </div>
                <div x-show="!user_id" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-user relative"><i
                                class="icon-circle-help absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih pengguna') }}
                    </div>
                </div>
            </div>
            <div x-show="user_id" x-cloak :class="!mode ? '' : 'grid'" class="gap-6 grid-cols-1 lg:grid-cols-2">
                <div>
                    <div class="flex flex-col gap-y-2 items-center text-neutral-600 dark:text-neutral-400 mb-8">
                        <div class="w-24 h-24 bg-neutral-200 dark:bg-neutral-700 rounded-full shadow overflow-hidden">
                            <img x-show="user_photo" class="w-full h-full object-cover dark:brightness-80" :src="user_photo ? '/storage/users/' + user_photo : ''" />
                            <svg x-show="!user_photo" xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                        </div>
                        <div x-text="user_name" class="text-xl"></div>
                        <div x-text="user_emp_id" class="text-sm"></div>
                        <div>
                            <x-pill x-show="user_is_active" color="green">{{  __('Aktif') }}</x-pill>
                            <x-pill x-show="!user_is_active" color="red">{{  __('Nonaktif') }}</x-pill>
                        </div>
                        <div x-text="'{{ __('Terakhir dilihat') }}: ' + (user_seen_at || '{{ __('Tidak ada aktivitas') }}')" class="text-sm"></div>
                    </div>
                    <div class="grid grid-cols-1 gap-1 my-8 max-w-md mx-auto">
                        <x-card-button x-on:click.prevent="mode = 'password-reset'">
                            <div class="flex px-8">
                                <div>
                                    <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                                        <div class="m-auto"><i class="icon-key"></i></div>
                                    </div>
                                </div>
                                <div class="grow text-left py-4">
                                    <div class="text-neutral-900 dark:text-neutral-100">
                                        {{ __('Atur ulang kata sandi') }}
                                    </div>
                                </div>
                            </div>
                        </x-card-button>
                        <x-card-button x-on:click.prevent="mode = 'user-update'">
                            <div class="flex px-8">
                                <div>
                                    <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                                        <div class="m-auto"><i class="icon-user-pen"></i></div>
                                    </div>
                                </div>
                                <div class="grow text-left py-4">
                                    <div class="text-neutral-900 dark:text-neutral-100">
                                        {{ __('Edit akun') }}
                                    </div>
                                </div>
                            </div>
                        </x-card-button>
                        <x-card-button x-on:click.prevent="mode = 'user-deactivate'">
                            <div class="flex px-8">
                                <div>
                                    <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                                        <div class="m-auto"><i class="icon-user-x"></i></div>
                                    </div>
                                </div>
                                <div class="grow text-left py-4">
                                    <div class="text-neutral-900 dark:text-neutral-100">
                                        {{ __('Nonaktifkan akun') }}
                                    </div>
                                </div>
                            </div>
                        </x-card-button>
                    </div>
                    <div class="flex justify-center my-8">
                        <x-secondary-button type="button" x-on:click="userReset()">{{ __('Pilih pengguna lain') }}</x-secondary-button>
                    </div>    
                </div>
                <div x-show="mode == 'password-reset'" x-cloak class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
                    <form wire:submit="resetPassword" class="p-6">
                        <div class="flex justify-between items-start">
                            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                {{ __('Atur ulang kata sandi') }}
                            </h2>
                            <x-text-button type="button" x-on:click="mode = ''"><i
                                    class="icon-x"></i></x-text-button>
                        </div>
                        <div class="grid gap-y-6 mt-6 text-sm text-neutral-600 dark:text-neutral-400">
                            <div>
                                {{ __('Atur kata sandi pengguna ini menjadi:') }}
                            </div>
                            <div>
                                <x-radio wire:model="password_option" id="password_option-2" name="password_option" value="1">opop1212</x-radio>
                            </div>
                            <div>{{ __('Peringatan: Ketika mengklik tombol dibawah berikut, Caldera tidak akan menanyakan konfirmasi dan kata sandi akan diatur sesuai dengan kata sandi yang dipilih.') }}</div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <x-primary-button type="submit">
                                {{ __('Atur kata sandi') }}
                            </x-primary-button>
                        </div>
                    </form>
                    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="resetPassword"></x-spinner-bg>
                    <x-spinner wire:loading.class.remove="hidden" wire:target="resetPassword" class="hidden"></x-spinner>
                </div>
                <div x-show="mode == 'user-update'" x-cloak class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
                    <form wire:submit="updateUser" class="p-6">
                        <div class="flex justify-between items-start">
                            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                {{ __('Edit akun') }}
                            </h2>
                            <x-text-button type="button" x-on:click="mode = ''"><i
                                    class="icon-x"></i></x-text-button>
                        </div>
                        <div class="grid gap-y-6 mt-6 text-sm text-neutral-600 dark:text-neutral-400">
                            <div>
                                <label for="user-name"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                                <x-text-input id="user-name" wire:model="user_name" type="text" />
                                @error('line')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                            <div>
                                <label for="user-emp_id"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nomor karyawan') }}</label>
                                <x-text-input id="user-emp_id" wire:model="user_emp_id" type="text" />
                                @error('ip_address')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                @enderror
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <x-primary-button type="submit">
                                {{ __('Perbarui') }}
                            </x-primary-button>
                        </div>
                    </form>
                    <div class="w-full h-full absolute top-0 left-0 z-10 rounded-lg bg-white/90 dark:bg-neutral-800/90">
                        <div class="flex items-center justify-center z-20 h-full">
                            <div class="grid gap-3">
                                <div class="text-4xl text-center">
                                    <i class="icon-construction"></i>
                                </div>
                                <div>{{ __('Sedang dalam tahap pengembangan') }}</div>
                            </div>
                        </div>
                    </div>
                    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="updateUser"></x-spinner-bg>
                    <x-spinner wire:loading.class.remove="hidden" wire:target="updateUser" class="hidden"></x-spinner>
                </div>
                <div x-show="mode == 'user-deactivate'" x-cloak class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
                    <form wire:submit="deactivateUser" class="p-6">
                        <div class="flex justify-between items-start">
                            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                {{ __('Nonaktifkan akun') }}
                            </h2>
                            <x-text-button type="button" x-on:click="mode = ''"><i
                                    class="icon-x"></i></x-text-button>
                        </div>
                        <div class="grid gap-y-6 mt-6 text-sm text-neutral-600 dark:text-neutral-400">
                            <div>
                                {{ __('Akun yang telah dibuat tidak dapat dihapus. Namun kamu dapat menonaktifkannya saja. Akun yang dinonaktifkan tidak akan muncul diberbagai pencarian namun akan masih muncul di data yang sudah ada.') }}
                            </div>
                            <div>
                                <div>{{ __('Peringatan: Ketika mengklik tombol dibawah berikut, Caldera tidak akan menanyakan konfirmasi dan akun akan langsung dinonaktifkan.') }}</div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <x-primary-button type="submit">
                                {{ __('Nonaktifkan') }}
                            </x-primary-button>
                        </div>
                    </form>
                    <div class="w-full h-full absolute top-0 left-0 z-10 rounded-lg bg-white/90 dark:bg-neutral-800/90">
                        <div class="flex items-center justify-center z-20 h-full">
                            <div class="grid gap-3">
                                <div class="text-4xl text-center">
                                    <i class="icon-construction"></i>
                                </div>
                                <div>{{ __('Sedang dalam tahap pengembangan') }}</div>
                            </div>
                        </div>
                    </div>
                    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="deactivateUser"></x-spinner-bg>
                    <x-spinner wire:loading.class.remove="hidden" wire:target="deactivateUser" class="hidden"></x-spinner>
                </div>
            </div>
        </div> 
    </div>
</div>
