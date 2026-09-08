<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Configure authorization gates.
     */
    protected function configureGates(): void
    {
        Gate::define('manage-system', fn (User $user): bool => $user->isTechAdmin());
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-dashboard', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-configurations', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-flight-portal', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-reports', fn (User $user): bool => $user->isAdmin());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
