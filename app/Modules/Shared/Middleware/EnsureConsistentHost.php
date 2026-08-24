<?php

namespace App\Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConsistentHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $requestAuthority = $this->authorityFromRequest($request);
        $originAuthority = $this->authorityFromUrl((string) $request->headers->get('origin', ''));
        $refererAuthority = $this->authorityFromUrl((string) $request->headers->get('referer', ''));

        $isOriginMismatch = $originAuthority !== null && $originAuthority !== $requestAuthority;
        $isRefererMismatch = $refererAuthority !== null && $refererAuthority !== $requestAuthority;

        if (! $isOriginMismatch && ! $isRefererMismatch) {
            return $next($request);
        }

        $message = 'Mixed host access detected. Open and use only one URL (same IP/host and port) for the whole session, then try again.';

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $message,
                'request_host' => $requestAuthority,
                'origin' => $originAuthority,
                'referer' => $refererAuthority,
            ], 409);
        }

        return redirect()
            ->route('login')
            ->with('status', $message)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function authorityFromRequest(Request $request): string
    {
        $host = strtolower((string) $request->getHost());
        $port = $request->getPort();

        if (($request->isSecure() && $port === 443) || (! $request->isSecure() && $port === 80)) {
            return $host;
        }

        return $host.':'.$port;
    }

    private function authorityFromUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $port = parse_url($url, PHP_URL_PORT);

        $normalizedHost = strtolower($host);
        $normalizedPort = is_int($port)
            ? $port
            : ((is_string($scheme) && strtolower($scheme) === 'https') ? 443 : 80);

        if ((is_string($scheme) && strtolower($scheme) === 'https' && $normalizedPort === 443)
            || ((is_string($scheme) ? strtolower($scheme) !== 'https' : true) && $normalizedPort === 80)) {
            return $normalizedHost;
        }

        return $normalizedHost.':'.$normalizedPort;
    }
}
