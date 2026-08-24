<?php

namespace App\Modules\Profile\Services;

use App\Modules\Profile\Notifications\PasswordChangeVerificationNotification;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordChangeService
{
    private const TOKEN_TTL_MINUTES = 60;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const CONFIRMED_CACHE_TTL_MINUTES = 30;

    public function hasPendingChange(User $user): bool
    {
        if (! filled($user->pending_password) || ! filled($user->password_change_token)) {
            return false;
        }

        if ($this->isExpired($user)) {
            return false;
        }

        return true;
    }

    public function isExpired(User $user): bool
    {
        return $user->password_change_token_expires_at !== null
            && $user->password_change_token_expires_at->isPast();
    }

    public function clearExpiredPending(User $user): void
    {
        if (! filled($user->pending_password) && ! filled($user->password_change_token)) {
            return;
        }

        if ($this->isExpired($user)) {
            $this->cancel($user);
        }
    }

    public function requestChange(User $user, string $newPassword): void
    {
        if (Hash::check($newPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Your new password must be different from your current password.'],
            ]);
        }

        $this->forgetRecentlyConfirmed($user->id);

        $plainToken = Str::random(64);

        $user->forceFill([
            'pending_password' => Hash::make($newPassword),
            'password_change_token' => hash('sha256', $plainToken),
            'password_change_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'password_change_last_sent_at' => now(),
        ])->save();

        $user->notify(new PasswordChangeVerificationNotification($plainToken));
    }

    public function resend(User $user): void
    {
        if (! $this->hasPendingChange($user)) {
            throw ValidationException::withMessages([
                'password' => ['No pending password change request found.'],
            ]);
        }

        if ($user->password_change_last_sent_at?->isAfter(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            $seconds = max(1, self::RESEND_COOLDOWN_SECONDS - (int) $user->password_change_last_sent_at->diffInSeconds(now()));
            throw ValidationException::withMessages([
                'password' => ["Please wait {$seconds} seconds before resending."],
            ]);
        }

        $plainToken = Str::random(64);

        $user->forceFill([
            'password_change_token' => hash('sha256', $plainToken),
            'password_change_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'password_change_last_sent_at' => now(),
        ])->save();

        $user->notify(new PasswordChangeVerificationNotification($plainToken));
    }

    public function cancel(User $user): void
    {
        $this->forgetRecentlyConfirmed($user->id);

        $user->forceFill([
            'pending_password' => null,
            'password_change_token' => null,
            'password_change_token_expires_at' => null,
            'password_change_last_sent_at' => null,
        ])->save();
    }

    public function confirm(int $userId, string $plainToken): User
    {
        $user = User::query()->find($userId);

        if ($user === null || ! $this->hasPendingChange($user)) {
            throw ValidationException::withMessages([
                'token' => ['This password change link is invalid or has already been used.'],
            ]);
        }

        if (! hash_equals((string) $user->password_change_token, hash('sha256', $plainToken))) {
            throw ValidationException::withMessages([
                'token' => ['This password change link is invalid.'],
            ]);
        }

        if ($user->password_change_token_expires_at === null || $user->password_change_token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This password change link has expired. Please request a new one.'],
            ]);
        }

        $pendingPassword = (string) $user->pending_password;

        $user->forceFill([
            'pending_password' => null,
            'password_change_token' => null,
            'password_change_token_expires_at' => null,
            'password_change_last_sent_at' => null,
            'remember_token' => null,
        ])->save();

        User::query()
            ->whereKey($user->id)
            ->update(['password' => $pendingPassword]);

        if (Schema::hasColumn('users', 'must_change_password')) {
            User::query()
                ->whereKey($user->id)
                ->update(['must_change_password' => false]);
        }

        $this->markRecentlyConfirmed($user->id);

        return $user->fresh();
    }

    public function markRecentlyConfirmed(int $userId): void
    {
        Cache::put(
            $this->confirmedCacheKey($userId),
            true,
            now()->addMinutes(self::CONFIRMED_CACHE_TTL_MINUTES),
        );
    }

    public function wasRecentlyConfirmed(int $userId): bool
    {
        return (bool) Cache::get($this->confirmedCacheKey($userId), false);
    }

    public function forgetRecentlyConfirmed(int $userId): void
    {
        Cache::forget($this->confirmedCacheKey($userId));
    }

    protected function confirmedCacheKey(int $userId): string
    {
        return "sk_fed_password_change_confirmed:{$userId}";
    }

    public function resendCooldownRemaining(User $user): int
    {
        if ($user->password_change_last_sent_at === null) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - (int) $user->password_change_last_sent_at->diffInSeconds(now()));
    }
}
