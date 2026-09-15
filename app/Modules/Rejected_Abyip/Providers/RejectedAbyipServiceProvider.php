<?php

namespace App\Modules\Rejected_Abyip\Providers;

use App\Modules\Rejected_Abyip\Console\Commands\PurgeRejectedAbyipCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RejectedAbyipServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'Rejected_Abyip');

        $migrationsPath = __DIR__.'/../Database/Migrations';
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/rejected-abyip'),
        ], 'rejected-abyip-assets');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgeRejectedAbyipCommand::class,
            ]);
        }

        $routesFile = is_file(__DIR__.'/../routes/rejected_abyip.php')
            ? __DIR__.'/../routes/rejected_abyip.php'
            : __DIR__.'/../Routes/rejected_abyip.php';

        if (is_file($routesFile)) {
            Route::middleware('web')->group($routesFile);
        }
    }
}
