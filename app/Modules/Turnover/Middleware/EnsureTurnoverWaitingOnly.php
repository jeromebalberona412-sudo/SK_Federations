<?php

namespace App\Modules\Turnover\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTurnoverWaitingOnly
{
    /**
     * @var list<string>
     */
    private array $allowedRouteNames = [
        'turnover.waiting',
        'logout',
        'logout.fallback',
        'skfed.heartbeat',
        'password.change',
        'password.change.update',
        'change-password',
        'change-password.verify',
        'change-password.verify.status',
        'change-password.resend',
        'change-password.cancel',
    ];

    /**
     * @var list<string>
     */
    private array $allowedPathPrefixes = [
        '/modules/turnover/',
        '/modules/authentication/',
        '/modules/layout/',
        '/shared/',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array($user->turnover_status, ['pending_confirmation', 'awaiting_setup'], true)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && in_array($routeName, $this->allowedRouteNames, true)) {
            return $next($request);
        }

        foreach ($this->allowedPathPrefixes as $prefix) {
            if (str_starts_with($request->path(), ltrim($prefix, '/'))) {
                return $next($request);
            }
        }

        if ($routeName !== 'turnover.waiting') {
            return redirect()->route('turnover.waiting');
        }

        return $next($request);
    }
}
