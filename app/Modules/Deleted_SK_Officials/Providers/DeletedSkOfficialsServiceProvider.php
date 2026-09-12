<?php

namespace App\Modules\Deleted_SK_Officials\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DeletedSkOfficialsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'Deleted_SK_Officials');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/deleted-sk-officials'),
        ], 'deleted-sk-officials-assets');

        $routesFile = is_file(__DIR__.'/../routes/deleted_sk_officials.php')
            ? __DIR__.'/../routes/deleted_sk_officials.php'
            : __DIR__.'/../Routes/deleted_sk_officials.php';

        if (is_file($routesFile)) {
            Route::middleware('web')->group($routesFile);
        }
    }
}
