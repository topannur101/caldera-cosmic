<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

new #[Layout("layouts.limited")] class extends Component {
    public bool $is_success = true;
    public string $password = "";
    public string $password_confirmation = "";

    public function mount()
    {
        if (Auth::user()->password == '$2y$12$0KKCawG6HLkTJP3BPUJ5xupcpSGiYdL2CV13Eku8eID48YFN2L.aC') {
            $this->is_success = false;
        }
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate(
                [
                    "password" => ["required", "string", Password::defaults(), "confirmed", "not_in:opop1212"],
                ],
                [
                    "password.not_in" => __("Kata sandi tidak boleh opop1212"),
                ],
            );
        } catch (ValidationException $e) {
            $this->reset("password", "password_confirmation");

            throw $e;
        }

        if (Auth::user()->password == '$2y$12$0KKCawG6HLkTJP3BPUJ5xupcpSGiYdL2CV13Eku8eID48YFN2L.aC') {
            Auth::user()->update([
                "password" => Hash::make($validated["password"]),
            ]);

            Auth::guard("web")->logout();
            Session::invalidate();
            Session::regenerateToken();

            $this->is_success = true;
        } else {
            $this->redirect(route("home"));
        }
    }
}; ?>

<section>
    @if ($is_success)
        <header>
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                <i class="icon-circle-check mr-2 text-green-500"></i>
                {{ __("Berhasil diperbarui") }}
            </h2>

            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __("Silakan masuk kembali dan gunakan kata sandi yang baru. ") }}
            </p>
        </header>
        <div class="mt-6 space-y-6 flex justify-end">
            <x-link-secondary-button :href="route('login')" wire:navigate>
                <i class="icon-login mr-2"></i>
                {{ __("Masuk") }}
            </x-link-secondary-button>
        </div>
    @else
        <header>
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Sebelum melanjutkan...") }}
            </h2>

            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __("Kata sandimu baru saja diatur-ulang oleh superuser. Harap segera ganti dengan yang baru.") }}
            </p>
        </header>

        <form wire:submit="updatePassword" class="mt-6 space-y-6">
            <div>
                <x-input-label for="update_password_password" :value="__('Kata sandi baru')" />
                <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" :value="__('Konfirmasi kata sandi')" />
                <x-text-input
                    wire:model="password_confirmation"
                    id="update_password_password_confirmation"
                    name="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end gap-4">
                <x-primary-button type="submit">{{ __("Simpan") }}</x-primary-button>
            </div>
        </form>
    @endif
</section>
