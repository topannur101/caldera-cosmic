<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('superuser', function (User $user) {
            return $user->id === 1
            ? Response::allow()
            : Response::deny('Kamu tidak memiliki wewenang.');
        });

        URL::macro('livewire_current', function () {
            if (request()->route()->named('livewire.update')) {
                $previousUrl = url()->previous();

                return $previousUrl;
            } else {
                return request()->route()->getName();
            }
        });
    }
}
