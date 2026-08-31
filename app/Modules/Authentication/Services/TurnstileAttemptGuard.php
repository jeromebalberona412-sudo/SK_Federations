<?php

namespace App\Modules\Authentication\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Progressive Turnstile gate: require verification on first use and again after
 * a configurable streak of failed/abusive attempts. Authoritative state lives
 * in cache (session + IP fingerprint), never in the browser.
 */
class TurnstileAttemptGuard
{
    public const ACTION_SIGNIN = 'signin';

    public const ACTION_FORGOT_PASSWORD = 'forgot_password';

    public function __construct(
        private readonly TurnstileService $turnstile,
    ) {}

    public function isEnabled(): bool
    {
        return $this->turnstile->isEnabled();
    }

    public function threshold(): int
    {
        return max(1, (int) config('services.turnstile.failed_attempts', 3));
    }

    public function ttlSeconds(): int
    {
        return max(60, (int) config('services.turnstile.state_ttl_seconds', 3600));
    }

    /**
     * Whether the next protected request must include a fresh Turnstile token.
     */
    public function isRequired(string $action, Request $request): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $state = $this->getState($action, $request);

        if (! empty($state['require_turnstile'])) {
            return true;
        }

        // First visit / uncleared state always requires an initial verification.
        return empty($state['cleared']);
    }

    /**
     * Enforce Turnstile when required. Returns a user-facing error, or null when OK.
     * Failed Turnstile checks do not modify the authentication/request failure counter.
     */
    public function enforce(string $action, Request $request): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if (! $this->isRequired($action, $request)) {
            return null;
        }

        $token = trim((string) $request->input('cf-turnstile-response', ''));

        if ($token === '') {
            return 'Please complete the security verification before continuing.';
        }

        if (! $this->turnstile->verify($token, $request->ip())) {
            $this->recordTurnstileFailure($action, $request);

            return 'Security verification failed. Please try again.';
        }

        $this->markVerified($action, $request);

        return null;
    }

    /**
     * Record a failed sign-in (wrong credentials / denied access).
     *
     * @return array{failed_attempts:int,turnstile_required:bool,threshold_reached:bool,message:?string}
     */
    public function recordFailure(string $action, Request $request): array
    {
        return $this->bumpAttempts($action, $request);
    }

    /**
     * Record a completed forgot-password request toward the streak.
     *
     * @return array{failed_attempts:int,turnstile_required:bool,threshold_reached:bool,message:?string}
     */
    public function recordRequest(string $action, Request $request): array
    {
        return $this->bumpAttempts($action, $request);
    }

    public function clear(string $action, Request $request): void
    {
        Cache::forget($this->cacheKey($action, $request));
    }

    /**
     * @return array{enabled:bool,required:bool,failed_attempts:int,threshold:int}
     */
    public function status(string $action, Request $request): array
    {
        $state = $this->getState($action, $request);

        return [
            'enabled' => $this->isEnabled(),
            'required' => $this->isRequired($action, $request),
            'failed_attempts' => (int) ($state['failed_attempts'] ?? 0),
            'threshold' => $this->threshold(),
        ];
    }

    /**
     * @return array{failed_attempts:int,turnstile_required:bool,threshold_reached:bool,message:?string}
     */
    private function bumpAttempts(string $action, Request $request): array
    {
        if (! $this->isEnabled()) {
            return [
                'failed_attempts' => 0,
                'turnstile_required' => false,
                'threshold_reached' => false,
                'message' => null,
            ];
        }

        $state = $this->getState($action, $request);
        $state['failed_attempts'] = (int) ($state['failed_attempts'] ?? 0) + 1;

        $thresholdReached = $state['failed_attempts'] >= $this->threshold();
        $message = null;

        if ($thresholdReached) {
            $state['require_turnstile'] = true;
            $state['cleared'] = false;
            $message = 'Too many unsuccessful attempts. Please complete the security verification before trying again.';
        }

        $this->putState($action, $request, $state);

        return [
            'failed_attempts' => $state['failed_attempts'],
            'turnstile_required' => $this->isRequired($action, $request),
            'threshold_reached' => $thresholdReached,
            'message' => $message,
        ];
    }

    private function markVerified(string $action, Request $request): void
    {
        $previous = $this->getState($action, $request);

        $this->putState($action, $request, [
            'failed_attempts' => 0,
            'cleared' => true,
            'require_turnstile' => false,
            'turnstile_failures' => (int) ($previous['turnstile_failures'] ?? 0),
        ]);
    }

    private function recordTurnstileFailure(string $action, Request $request): void
    {
        $state = $this->getState($action, $request);
        $state['turnstile_failures'] = (int) ($state['turnstile_failures'] ?? 0) + 1;
        $this->putState($action, $request, $state);
    }

    /**
     * @return array{failed_attempts?:int,cleared?:bool,require_turnstile?:bool,turnstile_failures?:int}
     */
    private function getState(string $action, Request $request): array
    {
        $raw = Cache::get($this->cacheKey($action, $request), []);

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  array{failed_attempts?:int,cleared?:bool,require_turnstile?:bool,turnstile_failures?:int}  $state
     */
    private function putState(string $action, Request $request, array $state): void
    {
        Cache::put($this->cacheKey($action, $request), $state, $this->ttlSeconds());
    }

    private function cacheKey(string $action, Request $request): string
    {
        $sessionId = '';
        try {
            if ($request->hasSession()) {
                $sessionId = (string) $request->session()->getId();
            }
        } catch (\Throwable) {
            $sessionId = '';
        }

        $fingerprint = hash('sha256', implode('|', [
            strtolower(trim($action)),
            (string) $request->ip(),
            $sessionId !== '' ? $sessionId : 'no-session',
        ]));

        return 'skfed:turnstile_guard:'.$fingerprint;
    }
}
