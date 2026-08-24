<?php

namespace App\Modules\AuditLog\Contracts;

use App\Modules\Shared\Models\User;

interface AuditLogInterface
{
    /**
     * Log an audit event.
     *
     * @param string $eventType
     * @param User|null $user
     * @param array $metadata
     * @return void
     */
    public function log(string $eventType, ?User $user = null, array $metadata = []): void;

    /**
     * Log successful login.
     *
     * @param User $user
     * @return void
     */
    public function logLoginSuccess(User $user): void;

    /**
     * Log first login requiring password setup.
     */
    public function logFirstLogin(User $user): void;

    /**
     * Log completed administrator password setup.
     */
    public function logPasswordSetup(User $user): void;

    /**
     * Log failed login attempt.
     *
     * @param string $email
     * @return void
     */
    public function logLoginFailed(string $email): void;

    /**
     * Log account lockout.
     *
     * @param User $user
     * @return void
     */
    public function logAccountLocked(User $user): void;

    /**
     * Log successful 2FA challenge.
     *
     * @param User $user
     * @return void
     */
    public function log2FAChallengePassed(User $user): void;

    /**
     * Log failed 2FA challenge.
     *
     * @param User $user
     * @return void
     */
    public function log2FAChallengeFailed(User $user): void;

    /**
     * Log 2FA enabled.
     *
     * @param User $user
     * @return void
     */
    public function log2FAEnabled(User $user): void;

    /**
     * Log 2FA disabled.
     *
     * @param User $user
     * @return void
     */
    public function log2FADisabled(User $user): void;

    /**
     * Log password change.
     *
     * @param User $user
     * @return void
     */
    public function logPasswordChanged(User $user): void;

    /**
     * Log password reset request.
     *
     * @param string $email
     * @return void
     */
    public function logPasswordResetRequested(string $email): void;

    /**
     * Log user logout.
     *
     * @param User $user
     * @return void
     */
    public function logLogout(User $user): void;
}
