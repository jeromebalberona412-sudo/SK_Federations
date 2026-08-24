<?php

namespace App\Modules\Archive_Management\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ArchiveManagementServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'archive-management');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/archive-management'),
        ], 'archive-management-assets');

        $routesFile = is_file(__DIR__.'/../routes/archive_management.php')
            ? __DIR__.'/../routes/archive_management.php'
            : __DIR__.'/../Routes/archive_management.php';

        Route::middleware('web')->group($routesFile);
    }
}
