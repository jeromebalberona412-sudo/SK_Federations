<?php

namespace App\Modules\Accounts\Providers;

use App\Modules\Accounts\Controllers\AdminAccountController;
use App\Modules\Accounts\Services\AccountService;
use App\Modules\Accounts\Services\AccountSetupService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccountService::class);
        $this->app->singleton(AccountSetupService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'accounts');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/accounts'),
        ], 'accounts-assets');

        // Register after route cache is applied. Routes added in boot() are wiped when
        // bootstrap/cache/routes-v7.php loads in the later "booted" phase.
        $this->app->booted(function (): void {
            $this->registerAccountsRoutes();
        });
    }

    private function registerAccountsRoutes(): void
    {
        if (! Route::has('accounts.officials.index')) {
            $routesFile = is_file(__DIR__.'/../routes/accounts.php')
                ? __DIR__.'/../routes/accounts.php'
                : __DIR__.'/../Routes/accounts.php';

            if (is_file($routesFile)) {
                Route::middleware('web')->group($routesFile);
            }
        }

        // Stale Hostinger route cache may include officials.index but omit officials.data.
        if (! Route::has('accounts.officials.data')) {
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
}
