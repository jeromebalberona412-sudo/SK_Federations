<?php

namespace App\Modules\AuditLog\Services;

use App\Modules\Shared\Models\User;
use App\Modules\AuditLog\Models\AdminActivityLog;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\AuditLog\Support\AuditLogPresenter;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogService implements AuditLogInterface
{
    /**
     * Log an audit event.
     *
     * @param string $eventType
     * @param User|null $user
     * @param array $metadata
     * @return void
     */
    public function log(string $eventType, ?User $user = null, array $metadata = []): void
    {
        $ipAddress = request()->ip() ?? 'unknown';
        $userAgent = request()->userAgent() ?? 'unknown';
        $metadata = $this->enrichMetadata($eventType, $user, $metadata);

        try {
            $action = is_string($metadata['action'] ?? null) ? $metadata['action'] : null;
            $entityType = is_string($metadata['entity_type'] ?? null) ? $metadata['entity_type'] : null;
            $entityId = isset($metadata['entity_id']) ? (string) $metadata['entity_id'] : null;

            AdminActivityLog::create([
                'tenant_id' => $user?->tenant_id,
                'user_id' => $user?->id,
                'event_type' => $eventType,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => $metadata,
            ]);
        } catch (Throwable $e) {
            $this->writeAuditChannelLog('warning', 'Failed to persist audit event', [
                'event_type' => $eventType,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->writeAuditChannelLog('info', "Audit: {$eventType}", [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'ip_address' => $ipAddress,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function writeAuditChannelLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('audit')->{$level}($message, $context);
        } catch (Throwable) {
            Log::{$level}($message, $context);
        }
    }

    /**
     * Log successful login.
     *
     * @param User $user
     * @return void
     */
    public function logLoginSuccess(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_LOGIN_SUCCESS, $user, [
            'email' => $user->email,
            'action' => 'login',
            'module' => 'authentication',
        ]);
    }

    public function logFirstLogin(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_FIRST_LOGIN, $user, [
            'email' => $user->email,
            'action' => 'first_login',
        ]);
    }

    public function logPasswordSetup(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_PASSWORD_SETUP, $user, [
            'email' => $user->email,
            'action' => 'password_setup',
        ]);
    }

    /**
     * Log failed login attempt.
     *
     * @param string $email
     * @return void
     */
    public function logLoginFailed(string $email): void
    {
        $this->log(AdminActivityLog::EVENT_LOGIN_FAILED, null, [
            'email' => $email,
            'action' => 'login_failed',
        ]);
    }

    /**
     * Log account lockout.
     *
     * @param User $user
     * @return void
     */
    public function logAccountLocked(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_ACCOUNT_LOCKED, $user, [
            'email' => $user->email,
            'lockout_count' => $user->lockout_count,
            'lockout_until' => $user->lockout_until?->toDateTimeString(),
            'action' => 'account_locked',
        ]);
    }

    /**
     * Log successful 2FA challenge.
     *
     * @param User $user
     * @return void
     */
    public function log2FAChallengePassed(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_2FA_CHALLENGE_PASSED, $user, [
            'email' => $user->email,
        ]);
    }

    /**
     * Log failed 2FA challenge.
     *
     * @param User $user
     * @return void
     */
    public function log2FAChallengeFailed(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_2FA_CHALLENGE_FAILED, $user, [
            'email' => $user->email,
        ]);
    }

    /**
     * Log 2FA enabled.
     *
     * @param User $user
     * @return void
     */
    public function log2FAEnabled(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_2FA_ENABLED, $user, [
            'email' => $user->email,
            'confirmed_at' => $user->two_factor_confirmed_at?->toDateTimeString(),
        ]);
    }

    /**
     * Log 2FA disabled.
     *
     * @param User $user
     * @return void
     */
    public function log2FADisabled(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_2FA_DISABLED, $user, [
            'email' => $user->email,
        ]);
    }

    /**
     * Log password change.
     *
     * @param User $user
     * @return void
     */
    public function logPasswordChanged(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_PASSWORD_CHANGED, $user, [
            'email' => $user->email,
            'action' => 'password_changed',
        ]);
    }

    /**
     * Log password reset request.
     *
     * @param string $email
     * @return void
     */
    public function logPasswordResetRequested(string $email): void
    {
        $this->log(AdminActivityLog::EVENT_PASSWORD_RESET_REQUESTED, null, [
            'email' => $email,
            'action' => 'password_reset_requested',
        ]);
    }

    /**
     * Log user logout.
     *
     * @param User $user
     * @return void
     */
    public function logLogout(User $user): void
    {
        $this->log(AdminActivityLog::EVENT_LOGOUT, $user, [
            'email' => $user->email,
            'action' => 'logout',
            'module' => 'authentication',
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function enrichMetadata(string $eventType, ?User $user, array $metadata): array
    {
        if (! isset($metadata['module'])) {
            $metadata['module'] = str_contains($eventType, '.')
                ? explode('.', $eventType, 2)[0]
                : (AdminActivityLog::isSecurityEvent($eventType) ? 'security' : 'general');
        }

        if ($user !== null) {
            $metadata['role'] ??= $user->role;
            $metadata['portal'] ??= AuditLogPresenter::portalFromRole($user->role);
            $metadata['user_name'] ??= $user->name;
            $metadata['barangay_id'] ??= $user->barangay_id;

            if (! isset($metadata['barangay_name']) && $user->relationLoaded('barangay') && $user->barangay) {
                $metadata['barangay_name'] = $user->barangay->name;
            }
        }

        return $metadata;
    }
}
