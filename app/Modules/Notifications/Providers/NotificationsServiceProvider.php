<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Controllers\NotificationController;
use App\Services\SkFederationsNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SkFederationsNotificationService::class);
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__.'/../Views', 'notifications');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/notifications'),
        ], 'notifications-assets');

        View::composer('layout::header', function ($view): void {
            $user = Auth::user();
            $service = app(SkFederationsNotificationService::class);

            $view->with([
                'notifications' => $service->recentForUser($user, 5),
                'unreadNotificationCount' => $service->unreadCountForUser($user),
            ]);
        });
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/notifications.php');
    }
}
