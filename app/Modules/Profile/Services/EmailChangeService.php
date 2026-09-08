<?php

namespace App\Modules\Profile\Services;

use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Profile\Notifications\EmailChangeVerificationNotification;
use App\Modules\Shared\Models\User;
use App\Services\EmailValidationService;
use App\Services\InvalidEmailService;
use App\Services\SkFedPageCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailChangeService
{
    private const TOKEN_TTL_MINUTES = 60;

    private const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly SkFedPageCache $pageCache,
        private readonly EmailValidationService $emailValidation,
        private readonly InvalidEmailService $invalidEmails,
        private readonly AuditLogInterface $auditLog,
    ) {
    }

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
     * @return array{id: int, email: string, pending_email: string|null}
     */
    public function cachedUserSnapshot(User $user): array
    {
        return $this->pageCache->remember(
            'change-email.user',
            function () use ($user) {
                $fresh = $user->fresh() ?? $user;

                return [
                    'id' => (int) $fresh->id,
                    'email' => (string) $fresh->email,
                    'pending_email' => $fresh->pending_email ? (string) $fresh->pending_email : null,
                ];
            },
            ['user_id' => $user->id],
            120
        );
    }

    /**
     * @return array{plain_token: string}
     */
    public function requestChange(User $user, string $currentEmail, string $newEmail, string $password): array
    {
        $currentEmail = $this->emailValidation->normalize($currentEmail);
        $newEmail = $this->emailValidation->assertCanSend($newEmail, true, $user, 'new_email');

        if ($this->emailValidation->normalize((string) $user->email) !== $currentEmail) {
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

        $plainToken = Str::random(64);

        $user->forceFill([
            'pending_email' => $newEmail,
            'email_change_token' => hash('sha256', $plainToken),
            'email_change_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'email_change_verified_at' => null,
            'email_change_last_sent_at' => now(),
        ])->save();

        $this->bumpUserCache();
        $this->sendVerificationMail($user, $plainToken);

        $this->auditLog->log('email_change_requested', $user, [
            'current_email' => $currentEmail,
            'new_email' => $newEmail,
        ]);

        return ['plain_token' => $plainToken];
    }

    public function resend(User $user): void
    {
        if (! $this->hasPendingChange($user)) {
            throw ValidationException::withMessages([
                'email' => ['No pending email change request found.'],
            ]);
        }

        $pendingEmail = $this->emailValidation->assertCanSend(
            (string) $user->pending_email,
            true,
            $user,
            'email'
        );

        if ($user->email_change_last_sent_at?->isAfter(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            $seconds = max(1, self::RESEND_COOLDOWN_SECONDS - (int) $user->email_change_last_sent_at->diffInSeconds(now()));
            throw ValidationException::withMessages([
                'email' => ["Please wait {$seconds} seconds before resending."],
            ]);
        }

        $plainToken = Str::random(64);

        $user->forceFill([
            'pending_email' => $pendingEmail,
            'email_change_token' => hash('sha256', $plainToken),
            'email_change_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'email_change_last_sent_at' => now(),
        ])->save();

        $this->bumpUserCache();
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

        $this->bumpUserCache();
    }

    public function confirm(int $userId, string $plainToken): User
    {
        $user = User::query()->find($userId);

        if ($user === null || ! $this->hasPendingChange($user)) {
            throw ValidationException::withMessages([
                'token' => ['This email change link is invalid or has already been used.'],
            ]);
        }

        if (! hash_equals((string) $user->email_change_token, hash('sha256', $plainToken))) {
            $this->auditLog->log('email_change_failed', $user, ['reason' => 'invalid_token']);
            throw ValidationException::withMessages([
                'token' => ['This email change link is invalid.'],
            ]);
        }

        if ($user->email_change_token_expires_at === null || $user->email_change_token_expires_at->isPast()) {
            $this->auditLog->log('email_change_failed', $user, ['reason' => 'expired_token']);
            throw ValidationException::withMessages([
                'token' => ['This email change link has expired. Please request a new one.'],
            ]);
        }

        $newEmail = $this->emailValidation->normalize((string) $user->pending_email);

        try {
            $this->emailValidation->assertCanSend($newEmail, true, $user, 'email');
        } catch (ValidationException $exception) {
            $this->cancel($user);
            throw $exception;
        }

        return DB::transaction(function () use ($user, $newEmail): User {
            $payload = [
                'email' => $newEmail,
                'pending_email' => null,
                'email_change_verified_at' => now(),
                'email_change_last_sent_at' => null,
                'email_verified_at' => now(),
                'remember_token' => null,
                'email_change_token' => null,
                'email_change_token_expires_at' => null,
            ];

            if (Schema::hasColumn('users', 'must_change_password')) {
                $payload['must_change_password'] = false;
            }

            $user->forceFill($payload)->save();
            $this->invalidEmails->markVerified($newEmail);
            $this->bumpUserCache();

            $fresh = $user->fresh() ?? $user;
            $this->auditLog->log('email_change_verified', $fresh, [
                'new_email' => $newEmail,
            ]);

            return $fresh;
        });
    }

    public function resendCooldownRemaining(User $user): int
    {
        if ($user->email_change_last_sent_at === null) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - (int) $user->email_change_last_sent_at->diffInSeconds(now()));
    }

    protected function bumpUserCache(): void
    {
        $this->pageCache->bump('change-email.user');
        $this->pageCache->bump('profile.barangays');
    }

    protected function sendVerificationMail(User $user, string $plainToken): void
    {
        $recipient = $this->emailValidation->normalize((string) $user->pending_email);

        try {
            $this->invalidEmails->attemptMailDelivery($recipient, function () use ($user, $plainToken, $recipient) {
                Notification::route('mail', $recipient)
                    ->notify(new EmailChangeVerificationNotification($user, $plainToken));
            }, 'new_email');
        } catch (ValidationException $exception) {
            $this->auditLog->log('email_delivery_permanent_failure', $user, [
                'email' => $recipient,
            ]);
            $this->cancel($user);
            throw $exception;
        }
    }
}
