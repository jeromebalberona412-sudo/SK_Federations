<?php

namespace App\Modules\Layout\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LayoutServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom($this->resolveViewsPath(), 'layout');
        $this->loadRoutes();

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/layout'),
        ], 'layout-assets');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/layout.php');
    }

    private function resolveViewsPath(): string
    {
        foreach (['Views', 'views'] as $directory) {
            $path = __DIR__."/../{$directory}";

            if (is_dir($path)) {
                return $path;
            }
        }

        return __DIR__.'/../Views';
    }
}
