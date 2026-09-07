<?php

namespace App\Modules\Dashboard\Providers;

use App\Modules\Dashboard\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->ensureDashboardApiRoutes();
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

    protected function ensureDashboardApiRoutes(): void
    {
        Route::middleware([
            'web',
            'auth',
            'session.timeout',
            'verified',
            'single.session',
            'sk_fed.access',
            'trusted.device',
            'prevent.back',
        ])->group(function (): void {
            if (! Route::has('dashboard.purok-distribution')) {
                Route::get('/api/dashboard/purok-distribution', [DashboardController::class, 'purokChartData'])
                    ->name('dashboard.purok-distribution');
            }

            if (! Route::has('dashboard.feed')) {
                Route::get('/api/dashboard/feed', [DashboardController::class, 'feed'])
                    ->name('dashboard.feed');
            }

            if (! Route::has('dashboard.stats')) {
                Route::get('/api/dashboard/stats', [DashboardController::class, 'stats'])
                    ->name('dashboard.stats');
            }
        });
    }
}
