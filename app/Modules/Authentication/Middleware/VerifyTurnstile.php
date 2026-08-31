<?php

namespace App\Modules\Authentication\Middleware;

use App\Modules\Authentication\Services\TurnstileAttemptGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyTurnstile
{
    public function __construct(
        protected TurnstileAttemptGuard $turnstileGuard,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $fail = $this->turnstileGuard->enforce(
            TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD,
            $request,
        );

        if ($fail !== null) {
            $request->session()->flash('turnstile_required', true);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $fail,
                    'turnstile_required' => true,
                ], 422);
            }

            throw ValidationException::withMessages([
                'cf-turnstile-response' => $fail,
            ])->redirectTo(url()->previous() ?: route('password.request'));
        }

        return $next($request);
    }
}
