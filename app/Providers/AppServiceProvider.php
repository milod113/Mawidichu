<?php

namespace App\Providers;

use App\Enums\RoleName;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // Le super administrateur (Sous-direction de la Numérisation) a
        // implicitement toutes les permissions.
        Gate::before(fn ($user) => $user->hasRole(RoleName::SuperAdmin->value) ? true : null);
    }
}
