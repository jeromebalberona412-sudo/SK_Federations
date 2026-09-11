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

        Route::middleware('web')->group(__DIR__.'/../Routes/deleted_calendar_notes.php');
    }
}
