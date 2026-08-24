<?php

namespace App\Modules\CommunityFeed\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CommunityFeedServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__ . '/../Views', 'community_feed');

        $this->publishes([
            __DIR__ . '/../assets' => public_path('modules/community_feed'),
        ], 'community-feed-assets');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/community_feed.php');
    }
}
