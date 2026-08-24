<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', \App\Modules\Shared\Middleware\EnsureConsistentHost::class);

        $middleware->alias([
            'sk_fed.access' => \App\Modules\Authentication\Middleware\EnsureSkFedAccess::class,
            'trusted.device' => \App\Modules\Authentication\Middleware\EnsureTrustedDevice::class,
            'single.session' => \App\Modules\Authentication\Middleware\EnsureSingleSession::class,
            'turnstile' => \App\Modules\Authentication\Middleware\VerifyTurnstile::class,
            'prevent.back' => \App\Http\Middleware\PreventBackHistory::class,
            'turnover.waiting' => \App\Modules\Turnover\Middleware\EnsureTurnoverWaitingOnly::class,
            'turnover.leadership' => \App\Modules\Turnover\Middleware\EnsureFederationLeadershipForTurnover::class,
            'turnover.term.lock' => \App\Modules\Turnover\Middleware\EnsureTurnoverTermLock::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
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
