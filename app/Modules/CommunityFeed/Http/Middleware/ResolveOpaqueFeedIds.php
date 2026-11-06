<?php

namespace App\Modules\CommunityFeed\Http\Middleware;

use App\Support\OpaqueUrlId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOpaqueFeedIds
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        foreach (['id', 'comment'] as $parameter) {
            $value = $route?->parameter($parameter);
            if (is_string($value)) {
                abort_if(ctype_digit($value), 404);
                $route->setParameter($parameter, OpaqueUrlId::decode($value));
            }
        }

        return $next($request);
    }
}
