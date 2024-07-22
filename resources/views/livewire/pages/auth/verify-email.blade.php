<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
        {{ __('Terima kasih sudah mendaftar! Sebelum mulai, apakah alamat email kamu dengan mengklik tautan yang baru saja dikirim cocok? Jika tidak menerima emailnya, bisa dikirimkan ulang.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            {{ __('Sebuah tautan verifikasi baru telah dikirim ke alamat email yang kamu berikan saat pendaftaran.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <x-primary-button type="submit" wire:click="sendVerification">
            {{ __('Kirim Ulang Email Verifikasi') }}
        </x-primary-button>

        <button wire:click="logout" type="submit" class="underline text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-caldy-500 dark:focus:ring-offset-neutral-800">
            {{ __('Keluar') }}
        </button>
    </div>
</div>
