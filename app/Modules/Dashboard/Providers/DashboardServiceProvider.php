<?php

namespace App\Modules\Dashboard\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__ . '/../Views', 'dashboard');

        $this->publishes([
            __DIR__ . '/../assets' => public_path('modules/dashboard'),
        ], 'dashboard-assets');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/dashboard.php');
    }
}