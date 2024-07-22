<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Pref;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function mount()
    {
        $this->js('$wire.form.bgm = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";');
    }

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $accountPref = Pref::where('user_id', Auth::user()->id)->where('name', 'account')->first();
        $accountPref ? $data = json_decode($accountPref->data, true) : $data = '';

        $pref_lang   = isset($data['lang'])      ? $data['lang']     : 'id';
        $pref_bg     = isset($data['bg'])        ? $data['bg']       : 'auto';
        $pref_accent = isset($data['accent'])    ? $data['accent']   : 'purple';
        $pref_mblur  = $data['mblur'] ?? false;

        $bg = $pref_bg == 'auto' ? ($this->form->bgm == 'dark' ? 'dark' : null) : ($pref_bg == 'dark' ? 'dark' : null);

        session(['bg'       => $bg]);
        session(['mblur'    => $pref_mblur]);
        session(['lang'     => $pref_lang]);
        session(['accent'   => $pref_accent]);

        // navigate: false to reload page
        $this->redirectIntended(default: route('home', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login">
        <!-- Theme input -->
        <input type="hidden" wire:model="form.bgm" id="bgm" name="bgm" />

        <!-- Email Address -->
        {{-- <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div> --}}

        <!-- Email Address -->
        <div>
            <x-input-label for="emp_id" :value="__('Nomor karyawan')" />
            <x-text-input wire:model="form.emp_id" id="emp_id" class="block mt-1 w-full" type="text" name="emp_id" required autofocus autocomplete="emp_id" />
            <x-input-error :messages="$errors->get('form.emp_id')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Kata sandi')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        {{-- <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded dark:bg-neutral-900 border-neutral-300 dark:border-neutral-700 text-caldy-600 shadow-sm focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:focus:ring-offset-neutral-800" name="remember">
                <span class="ms-2 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Ingat aku') }}</span>
            </label>
        </div> --}}

        <div class="flex items-center justify-between mt-4">
            <div>
                <a class="underline text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-caldy-500 dark:focus:ring-offset-neutral-800" href="{{ route('register') }}">
                    {{ __('Daftar') }}</a>
                @if (Route::has('password.request'))
                    <span class="mx-1 text-neutral-600 dark:text-neutral-400">•</span>
                    <a class="underline text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-caldy-500 dark:focus:ring-offset-neutral-800" href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Lupa kata sandi') }}</a>
                @endif

            </div>
            {{-- @if (Route::has('password.request'))
                <a class="underline text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-caldy-500 dark:focus:ring-offset-neutral-800" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Lupa sandimu?') }}
                </a>
            @endif --}}

            <x-primary-button type="submit" class="ms-3">
                {{ __('Masuk') }}
            </x-primary-button>
        </div>
    </form>
</div>
