<?php

namespace App\Modules\Turnover\Providers;

use App\Modules\Dashboard\Services\DashboardStatsService;
use App\Modules\Turnover\Services\FederationTermDetectionService;
use App\Modules\Turnover\Services\TurnoverBatchTemplateService;
use App\Modules\Turnover\Services\TurnoverCompletionService;
use App\Modules\Turnover\Services\TurnoverInvitationService;
use App\Modules\Turnover\Services\TurnoverRegistrationService;
use App\Modules\Turnover\Services\TurnoverService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Modules\Turnover\Listeners\HandleTurnoverPasswordSetup;
use App\Modules\Turnover\Policies\FederationTurnoverPolicy;

class TurnoverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FederationTermDetectionService::class);
        $this->app->singleton(TurnoverService::class);
        $this->app->singleton(TurnoverRegistrationService::class);
        $this->app->singleton(TurnoverInvitationService::class);
        $this->app->singleton(TurnoverCompletionService::class);
        $this->app->singleton(TurnoverBatchTemplateService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'turnover');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/turnover'),
        ], 'turnover-assets');

        Gate::policy(\App\Modules\Turnover\Models\FederationTurnover::class, FederationTurnoverPolicy::class);

        Event::listen(PasswordReset::class, HandleTurnoverPasswordSetup::class);

        View::composer('layout::app', function ($view): void {
            $user = Auth::user();

            if ($user === null) {
                $view->with('turnoverModal', ['show' => false]);

                return;
            }

            $user->loadMissing('officialProfile');

            $turnoverService = app(TurnoverService::class);
            $context = $turnoverService->dashboardContext($user);
            $activeTurnover = $context['active_turnover'];

            if ($activeTurnover) {
                $activeTurnover->load('registrations');
            }

            $view->with([
                'turnoverModal' => [
                    'show' => (bool) ($context['show_modal'] ?? false),
                    'portal_locked' => (bool) ($context['portal_locked'] ?? false),
                    'context' => $context,
                    'active_turnover' => $activeTurnover,
                    'progress' => $context['progress'] ?? [],
                    'barangays' => app(DashboardStatsService::class)->getBarangays((int) $user->tenant_id),
                    'cssVersion' => @filemtime(app_path('Modules/Turnover/assets/css/turnover.css')) ?: time(),
                    'jsVersion' => @filemtime(app_path('Modules/Turnover/assets/js/turnover.js')) ?: time(),
                ],
            ]);
        });

        Route::middleware('web')
            ->group(__DIR__.'/../Routes/turnover.php');

        $this->app->booted(function (): void {
            Route::pushMiddlewareToGroup('web', \App\Modules\Turnover\Middleware\EnsureTurnoverTermLock::class);
        });
    }
}
