<?php

namespace App\Modules\Accounts\Providers;

use App\Modules\Accounts\Services\AccountService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccountService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'accounts');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/accounts'),
        ], 'accounts-assets');

        Route::middleware('web')
            ->group(__DIR__.'/../Routes/accounts.php');
    }
}
