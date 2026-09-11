<?php

namespace App\Modules\Deleted_Calendar_Notes\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DeletedCalendarNotesServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'Deleted_Calendar_Notes');

        $routesFile = is_file(__DIR__.'/../routes/deleted_calendar_notes.php')
            ? __DIR__.'/../routes/deleted_calendar_notes.php'
            : __DIR__.'/../Routes/deleted_calendar_notes.php';

        if (is_file($routesFile)) {
            Route::middleware('web')->group($routesFile);
        }
    }
}
