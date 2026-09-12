<?php

namespace App\Modules\Deleted_Posts\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DeletedPostsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'Deleted_Posts');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/deleted-posts'),
        ], 'deleted-posts-assets');

        $routesFile = is_file(__DIR__.'/../routes/deleted_posts.php')
            ? __DIR__.'/../routes/deleted_posts.php'
            : __DIR__.'/../Routes/deleted_posts.php';

        if (is_file($routesFile)) {
            Route::middleware('web')->group($routesFile);
        }
    }
}
