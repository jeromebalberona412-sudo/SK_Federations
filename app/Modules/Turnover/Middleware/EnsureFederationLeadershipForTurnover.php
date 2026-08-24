<?php

namespace App\Modules\Turnover\Middleware;

use App\Modules\Turnover\Services\FederationTermDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFederationLeadershipForTurnover
{
    public function __construct(
        private readonly FederationTermDetectionService $termDetectionService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->termDetectionService->isFederationLeadershipOfficer($user)) {
            abort(403, 'Only the Federation President or Vice President can access turnover management.');
        }

        return $next($request);
    }
}
