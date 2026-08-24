<?php

namespace App\Modules\Profile\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ProfileServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__.'/../Views', 'profile');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/profile'),
        ], 'profile-assets');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/profile.php');
    }
}