<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Authentication\Models\LoginAttempt;
use App\Modules\Shared\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LoginSecurityService
{
    private ?bool $tableAvailable = null;

    public function recordAttempt(?User $user, string $email, bool $successful, Request $request, array $metadata = []): void
    {
        if (! $this->tableExists()) {
            return;
        }

        try {
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
        } catch (QueryException $e) {
            $this->rememberMissingTable($e);
            Log::warning('Unable to record login attempt; continuing authentication.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function isLocked(User $user): bool
    {
        return $user->isLocked();
    }

    public function applyFailureLockout(?User $user, string $email, Request $request): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $windowMinutes = (int) config('sk_fed_auth.rate_limit.window_minutes', 15);
        $maxFailures = (int) config('sk_fed_auth.rate_limit.max_failures_per_window', 5);
        $maxLockMinutes = (int) config('sk_fed_auth.rate_limit.max_lock_minutes', 60);

        try {
            $failureCount = LoginAttempt::query()
                ->recentFailedByEmail($email, $windowMinutes)
                ->orWhere(fn ($query) => $query->recentFailedByIp((string) $request->ip(), $windowMinutes))
                ->count();
        } catch (QueryException $e) {
            $this->rememberMissingTable($e);
            Log::warning('Unable to count login failures; skipping lockout.', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

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

    private function tableExists(): bool
    {
        if ($this->tableAvailable !== null) {
            return $this->tableAvailable;
        }

        try {
            $this->tableAvailable = Schema::hasTable('sk_fed_login_attempts');
        } catch (Throwable $e) {
            report($e);
            $this->tableAvailable = false;
        }

        return $this->tableAvailable;
    }

    private function rememberMissingTable(QueryException $e): void
    {
        $message = $e->getMessage();

        if (str_contains($message, 'sk_fed_login_attempts') || str_contains($message, '42P01')) {
            $this->tableAvailable = false;
        }
    }
}
