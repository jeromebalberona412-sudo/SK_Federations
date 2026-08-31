<?php

namespace App\Modules\Authentication\Middleware;

use App\Modules\Authentication\Services\AuthAuditLogService;
use App\Modules\Shared\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    public const SESSION_KEY = 'last_activity_at';

    public function __construct(
        protected AuthAuditLogService $auditLogService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = max(1, (int) config('session.timeout', 120));
        $timeoutSeconds = $timeoutMinutes * 60;
        $now = now()->getTimestamp();
        $lastActivity = $request->session()->get(self::SESSION_KEY);

        if (is_numeric($lastActivity) && ($now - (int) $lastActivity) >= $timeoutSeconds) {
            return $this->expireSession($request);
        }

        if ($lastActivity === null || ! $this->shouldSkipActivityRefresh($request)) {
            $request->session()->put(self::SESSION_KEY, $now);
        }

        return $next($request);
    }

    protected function shouldSkipActivityRefresh(Request $request): bool
    {
        // Heartbeats keep single-session ownership alive; they must not reset inactivity.
        return $request->routeIs('skfed.heartbeat');
    }

    protected function expireSession(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $message = 'Your session has expired due to inactivity. Please log in again.';

        if ($user !== null) {
            try {
                $this->auditLogService->log(
                    event: 'session_expired_inactivity',
                    user: $user,
                    request: $request,
                    metadata: [
                        'route' => $request->route()?->getName(),
                        'path' => '/'.$request->path(),
                    ],
                    outcome: AuthAuditLogService::OUTCOME_SUCCESS,
                    resourceType: 'session',
                );
            } catch (\Throwable $exception) {
                report($exception);
            }

            Log::info('SK Federation user session expired due to inactivity.', [
                'user_id' => $user->getKey(),
                'route' => $request->route()?->getName(),
                'path' => '/'.$request->path(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($this->wantsJsonResponse($request)) {
            return response()->json([
                'authenticated' => false,
                'session_expired' => true,
                'message' => $message,
            ], 401);
        }

        return redirect()->route('login')->withErrors([
            'session' => $message,
        ]);
    }

    protected function wantsJsonResponse(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->wantsJson()
            || str_contains((string) $request->header('Accept', ''), 'application/json');
    }
}
