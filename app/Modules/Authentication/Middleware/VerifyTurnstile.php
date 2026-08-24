<?php

namespace App\Modules\Authentication\Middleware;

use App\Modules\Authentication\Services\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyTurnstile
{
    public function __construct(
        protected TurnstileService $turnstileService,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->turnstileService->isConfigured()) {
            return $next($request);
        }

        $responseToken = trim((string) $request->input('cf-turnstile-response', ''));

        if ($responseToken === '') {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Please complete the Cloudflare verification first.',
            ]);
        }

        if (! $this->turnstileService->verify($responseToken, $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Cloudflare verification failed. Please try again.',
            ]);
        }

        return $next($request);
    }
}
