<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Notifications\AccountResetPasswordNotification;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AccountSetupService
{
    public const EMAIL_SENT_MESSAGE = 'Account setup email resent successfully.';

    public const EMAIL_FAILED_MESSAGE = 'The account setup email could not be sent. Please try again.';

    public const EMAIL_CHANGED_SENT_MESSAGE = 'Email updated successfully. A new account setup email has been sent to the new email address.';

    public const EMAIL_CHANGED_FAILED_MESSAGE = 'Member email was updated, but the account setup email could not be sent. Please try Resend Account Setup Email.';

    public const ALREADY_ACTIVATED_MESSAGE = 'This account is already verified and activated. A setup email cannot be sent.';

    /**
     * After an admin changes the member email: invalidate old tokens and email the NEW address.
     */
    public function sendSetupEmailAfterEmailChange(User $user, string $previousEmail): void
    {
        $user->refresh();

        $currentEmail = strtolower(trim((string) $user->email));
        $oldEmail = strtolower(trim($previousEmail));

        if ($currentEmail === '' || $currentEmail === $oldEmail) {
            return;
        }

        // Always invalidate both addresses before issuing a fresh token for the new email.
        $this->invalidateTokensForEmail($oldEmail);
        $this->invalidateTokensForEmail($currentEmail);

        $this->sendSetupEmailOrFail($user, $user->role);

        Log::info('Account setup email sent after email change.', [
            'user_id' => $user->id,
            'role' => $user->role,
            'operation' => 'email_change_setup',
        ]);
    }

    public const CREATE_SENT_MESSAGE = 'Account created successfully. A password setup email has been sent.';

    /**
     * Account finished activation: email verified and no forced password setup remains.
     */
    public function isAccountAlreadyActivated(User $user): bool
    {
        if ($user->trashed()) {
            return false;
        }

        if (! in_array($user->role, [User::ROLE_SK_OFFICIAL, User::ROLE_SK_FED], true)) {
            return false;
        }

        return $user->email_verified_at !== null
            && ! (bool) ($user->must_change_password ?? false);
    }

    /**
     * Whether this account still needs activation / password setup.
     */
    public function needsAccountSetup(User $user): bool
    {
        if ($user->trashed()) {
            return false;
        }

        if (! in_array($user->role, [User::ROLE_SK_OFFICIAL, User::ROLE_SK_FED], true)) {
            return false;
        }

        if (in_array($user->status, [User::STATUS_INACTIVE, User::STATUS_SUSPENDED], true)) {
            return false;
        }

        // Once verified/activated, never offer or allow resend setup email.
        if ($this->isAccountAlreadyActivated($user)) {
            return false;
        }

        return $user->status === User::STATUS_PENDING_APPROVAL
            || $user->email_verified_at === null
            || (bool) ($user->must_change_password ?? false);
    }

    public function invalidateTokensForEmail(string $email): void
    {
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedEmail === '') {
            return;
        }

        $table = (string) config('auth.passwords.users.table', 'password_reset_tokens');

        try {
            DB::table($table)->where('email', $normalizedEmail)->delete();
        } catch (\Throwable $exception) {
            Log::warning('Account setup email sending failed', [
                'operation' => 'invalidate_tokens',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function invalidateTokensForUser(User $user): void
    {
        $this->invalidateTokensForEmail((string) $user->email);

        if (Schema::hasTable('sk_official_email_verified_devices')) {
            DB::table('sk_official_email_verified_devices')->where('user_id', $user->id)->delete();
        }

        if (Schema::hasTable('sk_fed_email_verified_devices')) {
            DB::table('sk_fed_email_verified_devices')->where('user_id', $user->id)->delete();
        }
    }

    /**
     * Prepare an existing account for a fresh setup after email change.
     */
    public function prepareAccountForEmailChange(User $user, string $previousEmail): void
    {
        $this->invalidateTokensForEmail($previousEmail);
        $this->invalidateTokensForEmail((string) $user->email);

        $driver = config('database.connections.'.config('database.default').'.driver');
        $mustChange = true;

        if ($driver === 'pgsql') {
            $mustChange = DB::raw('true');
        }

        $user->forceFill([
            'email_verified_at' => null,
            'status' => User::STATUS_PENDING_APPROVAL,
            'must_change_password' => $mustChange,
        ])->save();
    }

    /**
     * @return array{0: string, 1: string} audience label + portal base URL
     */
    public function resolveSetupTarget(User $user, ?string $role = null): array
    {
        $role = $role ?? $user->role;

        if ($role === User::ROLE_SK_OFFICIAL) {
            $baseUrl = rtrim(trim((string) config('services.sk_officials_app_url')), '/');
            $federationUrl = rtrim(trim((string) config('app.url')), '/');

            if ($baseUrl === '' || strcasecmp($baseUrl, $federationUrl) === 0) {
                throw ValidationException::withMessages([
                    'email' => 'Password setup email could not be sent because the portal URL is not configured. Set SK_OFFICIALS_APP_URL (and SK_FED_APP_URL if needed) in .env, then run php artisan config:clear.',
                ]);
            }

            return ['SK Official', $baseUrl];
        }

        if ($role === User::ROLE_SK_FED) {
            $baseUrl = rtrim(trim((string) (config('services.sk_fed_app_url') ?: config('app.url'))), '/');

            if ($baseUrl === '') {
                throw ValidationException::withMessages([
                    'email' => 'Password setup email could not be sent because the portal URL is not configured. Set SK_FED_APP_URL in .env, then run php artisan config:clear.',
                ]);
            }

            return ['SK Federation', $baseUrl];
        }

        throw ValidationException::withMessages([
            'email' => 'This account type is not eligible for account setup email.',
        ]);
    }

    /**
     * Generate a new single-use setup token and send the setup email immediately (sendNow).
     */
    public function sendSetupEmail(User $user, ?string $role = null, bool $resetMailTransport = true): void
    {
        $user->refresh();

        [$label, $baseUrl] = $this->resolveSetupTarget($user, $role);

        $this->invalidateTokensForEmail((string) $user->email);

        $token = Password::createToken($user);
        $isOfficialActivation = ($role ?? $user->role) === User::ROLE_SK_OFFICIAL;

        try {
            Notification::sendNow(
                $user,
                new AccountResetPasswordNotification(
                    $token,
                    $baseUrl,
                    $label,
                    $isOfficialActivation,
                )
            );
        } catch (\Throwable $exception) {
            report($exception);
            Log::error('Account setup email sending failed', [
                'operation' => 'send_setup_email',
                'user_id' => $user->id,
                'role' => $role ?? $user->role,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => self::EMAIL_FAILED_MESSAGE,
            ]);
        }

        if ($resetMailTransport && method_exists(Mail::class, 'purgeSymfonyTransport')) {
            Mail::purgeSymfonyTransport();
        }

        Log::info('Account setup email sent.', [
            'user_id' => $user->id,
            'role' => $role ?? $user->role,
            'target' => $baseUrl,
            'operation' => 'send_setup_email',
        ]);
    }

    public function sendSetupEmailOrFail(User $user, ?string $role = null): void
    {
        try {
            $this->sendSetupEmail($user, $role);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            Log::error('Account setup email sending failed', [
                'operation' => 'send_setup_email_or_fail',
                'user_id' => $user->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => self::EMAIL_FAILED_MESSAGE,
            ]);
        }
    }

    /**
     * Queue setup email after the HTTP response so create stays fast, while still
     * sending in-process (no queue worker required).
     */
    public function queueSetupEmailAfterResponse(User $user, ?string $role = null): void
    {
        // Validate target URL immediately so create fails loudly if misconfigured.
        $this->resolveSetupTarget($user, $role);

        $userId = (int) $user->id;
        $resolvedRole = $role ?? $user->role;
        $emailAtCreate = strtolower(trim((string) $user->email));

        app()->terminating(function () use ($userId, $resolvedRole, $emailAtCreate): void {
            try {
                app(self::class)->deliverQueuedSetupEmail($userId, $resolvedRole, $emailAtCreate);
            } catch (\Throwable $exception) {
                report($exception);
                Log::error('Account setup email sending failed', [
                    'operation' => 'queued_setup_email',
                    'user_id' => $userId,
                    'role' => $resolvedRole,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function deliverQueuedSetupEmail(int $userId, ?string $role, string $emailAtCreate): void
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return;
        }

        $currentEmail = strtolower(trim((string) $user->email));
        $expectedEmail = strtolower(trim($emailAtCreate));

        // If admin already changed the email, the email-change flow owns sending.
        if ($currentEmail === '' || $expectedEmail === '' || $currentEmail !== $expectedEmail) {
            Log::info('Skipped deferred account setup email because account email changed after create.', [
                'user_id' => $userId,
                'operation' => 'queued_setup_email_skipped',
            ]);

            return;
        }

        $this->sendSetupEmail($user, $role);
    }

    public function resendSetupEmail(User $user, User $admin): void
    {
        $user->refresh();

        if ($this->isAccountAlreadyActivated($user) || ! $this->needsAccountSetup($user)) {
            throw ValidationException::withMessages([
                'account' => self::ALREADY_ACTIVATED_MESSAGE,
            ]);
        }

        $this->sendSetupEmailOrFail($user, $user->role);

        Log::info('Account setup email resent.', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'operation' => 'resend_setup_email',
        ]);
    }
}
