<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Authentication\Models\LoginAttempt;
use App\Modules\Shared\Models\User;

class SuspiciousLoginService
{
    public function detect(User $user, string $ipAddress): array
    {
        $signals = [];

        $lastSuccessQuery = LoginAttempt::query()
            ->where('user_id', $user->getKey());

        if ($lastSuccessQuery->getConnection()->getDriverName() === 'pgsql') {
            $lastSuccessQuery->whereRaw('"successful" = true');
        } else {
            $lastSuccessQuery->where('successful', true);
        }

        $lastSuccess = $lastSuccessQuery
            ->latest('attempted_at')
            ->first();

        if ($lastSuccess !== null && $lastSuccess->ip_address !== $ipAddress) {
            $signals[] = 'ip_change';
        }

        $hour = now()->hour;
        $nightStart = (int) config('sk_fed_auth.suspicious.night_start_hour', 0);
        $nightEnd = (int) config('sk_fed_auth.suspicious.night_end_hour', 4);

        if ($hour >= $nightStart && $hour <= $nightEnd) {
            $signals[] = 'abnormal_login_time';
        }

        $failureThreshold = (int) config('sk_fed_auth.suspicious.failure_threshold', 3);

        $recentFailuresQuery = LoginAttempt::query()
            ->where('email', $user->email)
            ->where('attempted_at', '>=', now()->subMinutes(30));

        if ($recentFailuresQuery->getConnection()->getDriverName() === 'pgsql') {
            $recentFailuresQuery->whereRaw('"successful" = false');
        } else {
            $recentFailuresQuery->where('successful', false);
        }

        $recentFailures = $recentFailuresQuery->count();

        if ($recentFailures >= $failureThreshold) {
            $signals[] = 'repeated_failures';
        }

        return [
            'is_suspicious' => $signals !== [],
            'signals' => $signals,
        ];
    }
}
