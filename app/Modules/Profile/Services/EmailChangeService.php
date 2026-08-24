<?php

namespace App\Modules\Profile\Services;

use App\Modules\Profile\Notifications\EmailChangeVerificationNotification;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailChangeService
{
    private const TOKEN_TTL_MINUTES = 60;

    private const SET_PASSWORD_TOKEN_TTL_HOURS = 2;

    private const RESEND_COOLDOWN_SECONDS = 60;

    public function hasPendingChange(User $user): bool
    {
        if (! filled($user->pending_email) || ! filled($user->email_change_token)) {
            return false;
        }

        if ($this->isExpired($user)) {
            return false;
        }

        return true;
    }

    public function isExpired(User $user): bool
    {
        return $user->email_change_token_expires_at !== null
            && $user->email_change_token_expires_at->isPast();
    }

    public function clearExpiredPending(User $user): void
    {
        if (! filled($user->pending_email) && ! filled($user->email_change_token)) {
            return;
        }

        if ($this->isExpired($user)) {
            $this->cancel($user);
        }
    }

    /**
     * @return array{plain_token: string}
     */
    public function requestChange(User $user, string $currentEmail, string $newEmail, string $password): array
    {
        $currentEmail = strtolower(trim($currentEmail));
        $newEmail = strtolower(trim($newEmail));

        if (strtolower((string) $user->email) !== $currentEmail) {
            throw ValidationException::withMessages([
                'current_email' => ['Current email does not match your account.'],
            ]);
        }

        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password you entered is incorrect.'],
            ]);
        }

        if ($newEmail === $currentEmail) {
            throw ValidationException::withMessages([
                'new_email' => ['New email must be different from your current email.'],
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'new_email' => ['This email address is already in use.'],
            ]);
        }

        if (User::query()->whereRaw('LOWER(pending_email) = ?', [$newEmail])->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'new_email' => ['This email address is already pending verification on another account.'],
            ]);
        }

        $plainToken = Str::random(64);

        $user->forceFill([
            'pending_email' => $newEmail,
            'email_change_token' => hash('sha256', $plainToken),
            'email_change_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'email_change_verified_at' => null,
            'email_change_last_sent_at' => now(),
        ])->save();

        $this->sendVerificationMail($user, $plainToken);

        return ['plain_token' => $plainToken];
    }

    public function resend(User $user): void
    {
        if (! $this->hasPendingChange($user)) {
            throw ValidationException::withMessages([
                'email' => ['No pending email change request found.'],
            ]);
        }

        if ($user->email_change_last_sent_at?->isAfter(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            $seconds = max(1, self::RESEND_COOLDOWN_SECONDS - (int) $user->email_change_last_sent_at->diffInSeconds(now()));
            throw ValidationException::withMessages([
                'email' => ["Please wait {$seconds} seconds before resending."],
            ]);
        }

        $plainToken = Str::random(64);

        $user->forceFill([
            'email_change_token' => hash('sha256', $plainToken),
            'email_change_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'email_change_last_sent_at' => now(),
        ])->save();

        $this->sendVerificationMail($user, $plainToken);
    }

    public function cancel(User $user): void
    {
        $user->forceFill([
            'pending_email' => null,
            'email_change_token' => null,
            'email_change_token_expires_at' => null,
            'email_change_verified_at' => null,
            'email_change_last_sent_at' => null,
        ])->save();
    }

    /**
     * @return array{user: User, set_password_token: string}
     */
    public function confirm(int $userId, string $plainToken): array
    {
        $user = User::query()->find($userId);

        if ($user === null || ! $this->hasPendingChange($user)) {
            throw ValidationException::withMessages([
                'token' => ['This email change link is invalid or has already been used.'],
            ]);
        }

        if (! hash_equals((string) $user->email_change_token, hash('sha256', $plainToken))) {
            throw ValidationException::withMessages([
                'token' => ['This email change link is invalid.'],
            ]);
        }

        if ($user->email_change_token_expires_at === null || $user->email_change_token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This email change link has expired. Please request a new one.'],
            ]);
        }

        $newEmail = strtolower((string) $user->pending_email);

        if (User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email address is no longer available. Please start a new request.'],
            ]);
        }

        $setPasswordToken = Str::random(64);

        $user->forceFill([
            'email' => $newEmail,
            'pending_email' => null,
            'email_change_verified_at' => now(),
            'email_change_last_sent_at' => null,
            'email_verified_at' => now(),
            'remember_token' => null,
            'email_change_token' => hash('sha256', $setPasswordToken),
            'email_change_token_expires_at' => now()->addHours(self::SET_PASSWORD_TOKEN_TTL_HOURS),
            'must_change_password' => true,
        ])->save();

        return [
            'user' => $user->fresh(),
            'set_password_token' => $setPasswordToken,
        ];
    }

    public function hasPendingPasswordSet(User $user): bool
    {
        return blank($user->pending_email)
            && filled($user->email_change_token)
            && (bool) $user->must_change_password;
    }

    public function validateSetPasswordToken(int $userId, string $plainToken): User
    {
        $user = User::query()->find($userId);

        if ($user === null || ! $this->hasPendingPasswordSet($user)) {
            throw ValidationException::withMessages([
                'token' => ['This password setup link is invalid or has already been used.'],
            ]);
        }

        if (! hash_equals((string) $user->email_change_token, hash('sha256', $plainToken))) {
            throw ValidationException::withMessages([
                'token' => ['This password setup link is invalid.'],
            ]);
        }

        if ($user->email_change_token_expires_at === null || $user->email_change_token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This password setup link has expired. Please sign in and contact support if you need help.'],
            ]);
        }

        return $user;
    }

    public function completePasswordSet(User $user, string $plainPassword): void
    {
        $user->forceFill([
            'password' => $plainPassword,
            'remember_token' => null,
            'email_change_token' => null,
            'email_change_token_expires_at' => null,
            'must_change_password' => false,
        ])->save();
    }

    public function resendCooldownRemaining(User $user): int
    {
        if ($user->email_change_last_sent_at === null) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - (int) $user->email_change_last_sent_at->diffInSeconds(now()));
    }

    protected function sendVerificationMail(User $user, string $plainToken): void
    {
        Notification::route('mail', $user->pending_email)
            ->notify(new EmailChangeVerificationNotification($user, $plainToken));
    }
}
