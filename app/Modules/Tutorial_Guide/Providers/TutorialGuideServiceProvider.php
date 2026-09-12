<?php

namespace App\Modules\Tutorial_Guide\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TutorialGuideServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom($this->resolveViewsPath(), 'tutorial_guide');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/tutorial-guide'),
        ], 'tutorial-guide-assets');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/tutorial_guide.php');
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
