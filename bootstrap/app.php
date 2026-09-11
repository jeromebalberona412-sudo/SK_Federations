<?php

use App\Http\Middleware\PreventBackHistory;
use App\Modules\Authentication\Middleware\EnsureSingleSession;
use App\Modules\Authentication\Middleware\EnsureSkFedAccess;
use App\Modules\Authentication\Middleware\EnsureTrustedDevice;
use App\Modules\Authentication\Middleware\SessionTimeout;
use App\Modules\Authentication\Middleware\VerifyTurnstile;
use App\Modules\Shared\Middleware\EnsureConsistentHost;
use App\Modules\Turnover\Middleware\EnsureFederationLeadershipForTurnover;
use App\Modules\Turnover\Middleware\EnsureTurnoverTermLock;
use App\Modules\Turnover\Middleware\EnsureTurnoverWaitingOnly;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Ensure Accounts routes are part of route:cache builds (not only ServiceProvider boot).
            $accountsRoutes = is_file(app_path('Modules/Accounts/routes/accounts.php'))
                ? app_path('Modules/Accounts/routes/accounts.php')
                : app_path('Modules/Accounts/Routes/accounts.php');

            if (is_file($accountsRoutes)) {
                Route::middleware('web')->group($accountsRoutes);
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->prependToGroup('web', EnsureConsistentHost::class);

        $middleware->alias([
            'sk_fed.access' => EnsureSkFedAccess::class,
            'trusted.device' => EnsureTrustedDevice::class,
            'single.session' => EnsureSingleSession::class,
            'session.timeout' => SessionTimeout::class,
            'turnstile' => VerifyTurnstile::class,
            'prevent.back' => PreventBackHistory::class,
            'turnover.waiting' => EnsureTurnoverWaitingOnly::class,
            'turnover.leadership' => EnsureFederationLeadershipForTurnover::class,
            'turnover.term.lock' => EnsureTurnoverTermLock::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $e, $request) {
            $errorMessage = $e->getMessage();

            // Check if it's a connection error
            if (
                str_contains($errorMessage, 'could not translate host name') ||
                str_contains($errorMessage, 'Connection refused') ||
                str_contains($errorMessage, 'Connection timed out') ||
                str_contains($errorMessage, 'No route to host') ||
                str_contains($errorMessage, 'Network is unreachable') ||
                str_contains($errorMessage, 'SQLSTATE[08006]') ||
                str_contains($errorMessage, 'SQLSTATE[HY000]')
            ) {
                return response()->view('errors.network-error', [], 503);
            }
        });
    })->create();
