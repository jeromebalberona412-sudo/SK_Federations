<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Authentication\Models\LoginAttempt;
use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginSecurityService
{
    public function recordAttempt(?User $user, string $email, bool $successful, Request $request, array $metadata = []): void
    {
        $driver = LoginAttempt::query()->getConnection()->getDriverName();
        $successfulValue = $successful;

        if ($driver === 'pgsql') {
            $successfulValue = DB::raw($successful ? 'true' : 'false');
        }

        LoginAttempt::query()->create([
            'user_id' => $user?->getKey(),
            'email' => $email,
            'ip_address' => $request->ip(),
            'successful' => $successfulValue,
            'user_agent' => $request->userAgent(),
            'attempted_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    public function isLocked(User $user): bool
    {
        return $user->isLocked();
    }

    public function applyFailureLockout(?User $user, string $email, Request $request): void
    {
        $windowMinutes = (int) config('sk_fed_auth.rate_limit.window_minutes', 15);
        $maxFailures = (int) config('sk_fed_auth.rate_limit.max_failures_per_window', 5);
        $maxLockMinutes = (int) config('sk_fed_auth.rate_limit.max_lock_minutes', 60);

        $failureCount = LoginAttempt::query()
            ->recentFailedByEmail($email, $windowMinutes)
            ->orWhere(fn ($query) => $query->recentFailedByIp((string) $request->ip(), $windowMinutes))
            ->count();

        if ($user === null || $failureCount < $maxFailures) {
            return;
        }

        $lockMinutes = min($maxLockMinutes, 5 * (2 ** max(0, $failureCount - $maxFailures)));
        $user->incrementLockout($lockMinutes);
    }

    public function clearAfterSuccess(User $user): void
    {
        $user->resetLockout();
    }
}
