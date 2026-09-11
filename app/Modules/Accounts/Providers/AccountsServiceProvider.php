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

        $routesFile = is_file(__DIR__.'/../routes/accounts.php')
            ? __DIR__.'/../routes/accounts.php'
            : __DIR__.'/../Routes/accounts.php';

        if (is_file($routesFile)) {
            Route::middleware('web')->group($routesFile);
        }

        // Hostinger / partial deploys: older routes files may omit the AJAX endpoint
        // while the manage-account view already references accounts.officials.data.
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
