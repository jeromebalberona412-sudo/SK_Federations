<?php

namespace App\Modules\KabataanMonitoring\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class KabataanMonitoringServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__ . '/../Views', 'kabataan_monitoring');

        $this->publishes([
            __DIR__ . '/../assets' => public_path('modules/kabataan_monitoring'),
        ], 'kabataan-monitoring-assets');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/kabataan_monitoring.php');
    }
}
