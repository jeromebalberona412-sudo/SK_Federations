<?php

namespace App\Modules\Turnover\Middleware;

use App\Modules\Turnover\Services\FederationTermDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTurnoverTermLock
{
    /**
     * @var list<string>
     */
    private array $allowedRouteNames = [
        'dashboard',
        'turnover.start',
        'turnover.register',
        'turnover.complete',
        'turnover.status',
        'turnover.waiting',
        'logout',
        'logout.fallback',
        'skfed.heartbeat',
    ];

    /**
     * @var list<string>
     */
    private array $allowedPathPrefixes = [
        'modules/turnover/',
        'modules/layout/',
        'modules/dashboard/',
        'shared/',
    ];

    public function __construct(
        private readonly FederationTermDetectionService $termDetectionService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->termDetectionService->mustLockPortalForTurnover($user)) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && in_array($routeName, $this->allowedRouteNames, true)) {
            return $next($request);
        }

        foreach ($this->allowedPathPrefixes as $prefix) {
            if (str_starts_with($request->path(), $prefix)) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your federation term has ended. Please complete the turnover process first.',
            ], 423);
        }

        return redirect()->route('dashboard')->withErrors([
            'turnover' => 'Your federation term has ended. Please complete the turnover process.',
        ]);
    }
}
