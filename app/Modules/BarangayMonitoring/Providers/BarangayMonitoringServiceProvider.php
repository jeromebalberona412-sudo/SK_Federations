<?php

namespace App\Modules\BarangayMonitoring\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BarangayMonitoringServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__ . '/../Views', 'barangay_monitoring');

        $this->publishes([
            __DIR__ . '/../assets' => public_path('modules/barangay_monitoring'),
        ], 'barangay-monitoring-assets');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/barangay_monitoring.php');
    }
}
