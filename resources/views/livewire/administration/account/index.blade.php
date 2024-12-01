<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;

use App\Models\User;

new #[Layout('layouts.app')] 
class extends Component {

    public string $userq;

    #[Renderless]
    public function updatedUserq()
    {
        $this->dispatch('userq-updated', $this->userq);
    }

};

?>
<x-slot name="title">{{ __('Akun') . ' — ' . __('Administrasi') }}</x-slot>

<x-slot name="header">
    <x-nav-administration></x-nav-administration>
</x-slot>

<div class="py-12">
    <div class="max-w-md mx-auto sm:px-6 lg:px-8 text-neutral-600 dark:text-neutral-400">
        <div wire:key="modals">
            <x-modal name="password-reset" maxWidth="sm" focusable>
                <div class="p-6">
                    password-reset
                    {{-- <livewire:account.update-password /> --}}
                </div>
            </x-modal>
            <x-modal name="user-edit" maxWidth="sm" focusable>
                <div class="p-6">
                    user-edit
                    {{-- <livewire:account.update-password /> --}}
                </div>
            </x-modal>
            <x-modal name="user-deactivate" maxWidth="sm" focusable>
                <div class="p-6">
                    user-deactivate
                    {{-- <livewire:account.update-password /> --}}
                </div>
            </x-modal>
        </div>
        <div x-data="{ 
                open: false, 
                userq: @entangle('userq').live,
                user_id: '',
                user_name: '',
                user_emp_id: '',
                user_photo: '',
                user_is_active: '',
                user_seen_at: '',
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
                    this.userq = ''

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
            <div x-show="!user_id" x-on:click.away="open = false" class="px-3 sm:px-0">
                {{-- <label for="cal-user"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengguna') }}</label> --}}
                <x-text-input-icon x-model="userq" icon="fa fa-fw fa-user" x-on:change="open = true"
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
                    <i class="fa fa-user relative"><i
                            class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                </div>
                <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih pengguna') }}
                </div>
            </div>
            <div x-show="user_id" x-cloak>
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
                <div class="grid grid-cols-1 gap-1 my-8">
                    <x-card-button x-on:click.prevent="$dispatch('open-modal', 'password-reset')">
                        <div class="flex px-8">
                            <div>
                                <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                                    <div class="m-auto"><i class="fa fa-fw fa-key"></i></div>
                                </div>
                            </div>
                            <div class="grow text-left py-4">
                                <div class="text-neutral-900 dark:text-neutral-100">
                                    {{ __('Atur ulang kata sandi') }}
                                </div>
                            </div>
                        </div>
                    </x-card-button>
                    <x-card-button x-on:click.prevent="$dispatch('open-modal', 'user-edit')">
                        <div class="flex px-8">
                            <div>
                                <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                                    <div class="m-auto"><i class="fa fa-fw fa-user-edit"></i></div>
                                </div>
                            </div>
                            <div class="grow text-left py-4">
                                <div class="text-neutral-900 dark:text-neutral-100">
                                    {{ __('Edit informasi akun') }}
                                </div>
                            </div>
                        </div>
                    </x-card-button>
                    <x-card-button x-on:click.prevent="$dispatch('open-modal', 'user-deactivate')">
                        <div class="flex px-8">
                            <div>
                                <div class="flex pr-5 h-full text-neutral-600 dark:text-neutral-400">
                                    <div class="m-auto"><i class="fa fa-fw fa-user-times"></i></div>
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
        </div> 
    </div>
</div>
