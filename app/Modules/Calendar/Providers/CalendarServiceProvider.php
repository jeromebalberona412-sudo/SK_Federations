<?php

namespace App\Modules\Calendar\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CalendarServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__ . '/../Views', 'Calendar');

        // Load routes with proper middleware
        Route::middleware('web')
            ->group(function () {
                require __DIR__ . '/../Routes/calendar.php';
            });

        // Publish assets
        $this->publishes([
            __DIR__ . '/../assets/css' => public_path('modules/calendar/css'),
            __DIR__ . '/../assets/js' => public_path('modules/calendar/js'),
        ], 'calendar-assets');
    }
}
