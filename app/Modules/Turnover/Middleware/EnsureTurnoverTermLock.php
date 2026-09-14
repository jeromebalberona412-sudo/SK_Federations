<?php

namespace App\Modules\Turnover\Middleware;

use App\Modules\Turnover\Services\FederationTermDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTurnoverTermLock
{
    /**
     * Routes allowed while mandatory turnover is active.
     * Only Account Turnover + logout/session keep-alive.
     *
     * @var list<string>
     */
    private array $allowedRouteNames = [
        'turnover.index',
        'turnover.start',
        'turnover.register',
        'turnover.complete',
        'turnover.status',
        'turnover.waiting',
        'turnover.remind-later',
        'turnover.batch-template',
        'logout',
        'logout.fallback',
        'skfed.heartbeat',
        'skfed.session.continue',
    ];

    /**
     * Exact paths allowed while mandatory turnover is active.
     *
     * @var list<string>
     */
    private array $allowedExactPaths = [
        'logout',
    ];

    /**
     * Static asset path prefixes that must remain reachable under lock.
     *
     * @var list<string>
     */
    private array $allowedPathPrefixes = [
        'turnover',
        'modules/turnover/',
        'modules/layout/',
        'shared/',
        'build/',
        'storage/',
    ];

    public function __construct(
        private readonly FederationTermDetectionService $termDetectionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        try {
            $mustLock = $this->termDetectionService->mustLockPortalForTurnover($user);
        } catch (\Throwable $e) {
            report($e);

            return $next($request);
        }

        if (! $mustLock) {
            return $next($request);
        }

        if ($this->isAllowedDuringMandatoryTurnover($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Your federation term has ended. Please complete Account Turnover first. Other pages are locked until turnover is finished.',
                'turnover_required' => true,
                'redirect' => route('turnover.index'),
            ], 423);
        }

        return redirect()->route('turnover.index')->with('turnover_lock_notice', [
            'title' => 'Account Turnover Required',
            'message' => 'Your SK Federation term has ended. You can only access Account Turnover (or log out) until the turnover process is completed.',
        ]);
    }

    private function isAllowedDuringMandatoryTurnover(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName !== null && in_array($routeName, $this->allowedRouteNames, true)) {
            return true;
        }

        $path = trim($request->path(), '/');

        if (in_array($path, $this->allowedExactPaths, true)) {
            return true;
        }

        foreach ($this->allowedPathPrefixes as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
