<?php

namespace App\Modules\BarangayLogos\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BarangayLogosServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__.'/../Views', 'barangay_logos');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/barangay-logos'),
        ], 'barangay-logos-assets');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/barangay_logos.php');
    }
}
