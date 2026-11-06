<?php

namespace App\Modules\Accounts\Providers;

use App\Modules\Accounts\Controllers\AdminAccountController;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Services\AccountService;
use App\Modules\Accounts\Services\AccountSetupService;
use App\Modules\Shared\Models\User;
use App\Support\OpaqueUrlId;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccountService::class);
        $this->app->singleton(AccountSetupService::class);

        // Runs immediately after the compiled route cache is required — not before it.
        // A normal $this->app->booted() callback is too early: RouteServiceProvider queues
        // the cache require in another booted callback, and nested booted() runs immediately
        // while the app is already booting (wiping / deleting a valid cache).
        RouteServiceProvider::loadCachedRoutesUsing(function (): void {
            require $this->app->getCachedRoutesPath();
            $this->ensureOfficialsDataRoute();
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'accounts');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::bind('account', fn (string $token): User => User::query()->findOrFail(OpaqueUrlId::decode($token)));
        Route::bind('profile', fn (string $token): OfficialProfile => OfficialProfile::query()->findOrFail(OpaqueUrlId::decode($token)));

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/accounts'),
        ], 'accounts-assets');

        // When routes are not cached, bootstrap/app.php `then:` loads accounts.php.
        // Keep a boot-time fallback only if that registration was skipped.
        if (! $this->app->routesAreCached()) {
            $this->app->booted(function (): void {
                if (! Route::has('accounts.officials.index')) {
                    $this->loadAccountsRouteFile();
                }

                $this->ensureOfficialsDataRoute();
            });
        }
    }

    private function loadAccountsRouteFile(): void
    {
        $routesFile = is_file(__DIR__.'/../routes/accounts.php')
            ? __DIR__.'/../routes/accounts.php'
            : __DIR__.'/../Routes/accounts.php';

        if (is_file($routesFile)) {
            Route::middleware('web')->group($routesFile);
        }
    }

    private function ensureOfficialsDataRoute(): void
    {
        if (Route::has('accounts.officials.data')) {
            return;
        }

        // Drop stale compiled cache that predates this endpoint so the next request rebuilds cleanly.
        if ($this->app->routesAreCached()) {
            @unlink($this->app->getCachedRoutesPath());
        }

        Route::middleware([
            'web',
            'auth',
            'session.timeout',
            'verified',
            'single.session',
            'sk_fed.access',
            'trusted.device',
            'prevent.back',
        ])->get('/accounts/officials/data', [AdminAccountController::class, 'officialsData'])
            ->name('accounts.officials.data');
    }
}
