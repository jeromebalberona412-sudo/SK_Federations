<?php

namespace App\Modules\Barangay_ABYIP\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class BarangayAbyipServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Views', 'barangay_abyip');
        $this->loadRoutes();
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/web.php');
    }

    public function register(): void
    {
        //
    }
}
