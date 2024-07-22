<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] 
class extends Component 
{
    public string $photo = '';
    public string $name = '';
    public string $emp_id = '';

    public function mount(): void
    {
        $this->photo = Auth::user()->photo ?? '';
        $this->name = Auth::user()->name ?? '';
        $this->emp_id = Auth::user()->emp_id ?? '';
    }

    public function updateAccountInfo(): void
    {
        $user = Auth::user();

        $this->name = trim($this->name);
        $this->emp_id = trim($this->emp_id);

        $validated = $this->validate([
            'name'      => ['required', 'string', 'max:255'],
            'emp_id'    => ['required', 'alpha_num', 'max:10', Rule::unique('users', 'emp_id')->ignore(Auth::user()->id ?? null)],
        ]);

        if( Gate::denies('superuser') )
        {
            unset($validated['emp_id']);
        }

        $user->updatePhoto($this->photo);
        $user->fill($validated);
        $user->save();

        $this->redirect(route('account', absolute: false), navigate: true);
    }


};

?>
<x-slot name="title">{{ __('Info akun') }}</x-slot>
<x-slot name="header">
    <header class="bg-white dark:bg-neutral-800 shadow">
        <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div>
                <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                    <x-link wire:navigate href="{{ route('account') }}" class="inline-block py-6"><i
                            class="fa fa-arrow-left"></i></x-link><span class="ml-4">{{ __('Info akun') }}</span>
                </h2>
            </div>
        </div>
    </header>
</x-slot>
<div id="content" class="py-12 max-w-md mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <section>
            <form wire:submit="updateAccountInfo">
                <div class="flex justify-center" x-data="{ photo: '{{ $photo }}'}" x-on:user-photo-updated="$wire.photo = $event.detail[0]">
                <livewire:layout.user-photo-set :url="$photo ? '/storage/users/'.$photo : ''" />
                    <input type="hidden" name="photo" wire:model="photo" />
                </div>
                <div class="p-4 sm:p-6 bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
                    <div class="mb-6">
                        <x-input-label for="name" :value="__('Nama')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" wire:model="name" required  autocomplete="cal_name" />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>
                    <div class="mb-6">
                        <x-input-label for="emp_id" :value="__('Nomor karyawan')" />
                        <x-text-input id="emp_id" name="emp_id" type="text" class="mt-1 block w-full" :disabled="Gate::denies('superuser')" wire:model="emp_id" required  autocomplete="cal_emp_id" />
                        <div class="text-sm mt-2 text-neutral-500">{{ __('Hubungi penanggung jawab Caldera untuk mengubah nomor karyawanmu.') }}</div>
                        <x-input-error class="mt-2" :messages="$errors->get('emp_id')" />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button type="submit">{{ __('Perbarui') }}</x-primary-button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</div>
