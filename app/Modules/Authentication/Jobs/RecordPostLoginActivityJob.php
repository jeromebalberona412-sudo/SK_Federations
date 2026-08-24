<?php

namespace App\Modules\Authentication\Jobs;

use App\Modules\Authentication\Notifications\NewLocationLoginNotification;
use App\Modules\Authentication\Services\AuthAuditLogService;
use App\Modules\Authentication\Services\EmailVerificationDeviceService;
use App\Modules\Authentication\Services\FeatureFlagService;
use App\Modules\Authentication\Services\LoginSecurityService;
use App\Modules\Authentication\Services\SuspiciousLoginService;
use App\Modules\Shared\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles all non-blocking post-login work so the login request can return
 * immediately after authentication succeeds.
 *
 * Queued work (does NOT block the login response):
 *  - Suspicious login detection + optional alert notification
 *  - Login attempt record (success)
 *  - Lockout counter reset
 *  - Audit log (login_success)
 *  - Email verified device upsert
 */
class RecordPostLoginActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly string $ipAddress,
        public readonly string $userAgent,
        public readonly string $deviceFingerprint,
        public readonly bool $isSuspiciousCheckEnabled,
        public readonly bool $isAlertEnabled,
        public readonly string $via = 'password',
    ) {}

    public function handle(
        LoginSecurityService $loginSecurityService,
        SuspiciousLoginService $suspiciousLoginService,
        AuthAuditLogService $auditLogService,
        EmailVerificationDeviceService $emailVerificationDeviceService,
        FeatureFlagService $featureFlagService,
    ): void {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        // ── Suspicious login detection & alert notification ────────────────
        if ($this->isSuspiciousCheckEnabled) {
            try {
                $suspicious = $suspiciousLoginService->detect($user, $this->ipAddress);

                if (($suspicious['is_suspicious'] ?? false) && $this->isAlertEnabled) {
                    Log::info('Sending new location login notification', [
                        'user_id' => $this->userId,
                        'email' => $user->email,
                        'ip' => $this->ipAddress,
                        'signals' => $suspicious['signals'] ?? [],
                        'type' => 'new_location_login',
                    ]);

                    $user->notify(new NewLocationLoginNotification(
                        ipAddress: $this->ipAddress,
                        userAgent: $this->userAgent,
                        signals: $suspicious['signals'] ?? [],
                    ));

                    Log::info('New location login notification sent successfully', [
                        'user_id' => $this->userId,
                        'email' => $user->email,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('[RecordPostLoginActivityJob] Suspicious login detection or notification failed.', [
                    'user_id' => $this->userId,
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }
        }

        // ── Login attempt record (success) ─────────────────────────────────
        try {
            // Build a minimal request-like context for the service
            $fakeRequest = new \Illuminate\Http\Request;
            $fakeRequest->server->set('REMOTE_ADDR', $this->ipAddress);
            $fakeRequest->headers->set('User-Agent', $this->userAgent);

            $loginSecurityService->recordAttempt($user, $this->email, true, $fakeRequest, ['reason' => 'success']);
            $loginSecurityService->clearAfterSuccess($user);
        } catch (\Throwable $e) {
            Log::warning('[RecordPostLoginActivityJob] recordAttempt failed.', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }

        // ── Audit log ──────────────────────────────────────────────────────
        try {
            $fakeRequest = new \Illuminate\Http\Request;
            $fakeRequest->server->set('REMOTE_ADDR', $this->ipAddress);
            $fakeRequest->headers->set('User-Agent', $this->userAgent);

            $auditLogService->log(
                event: 'login_success',
                user: $user,
                request: $fakeRequest,
                metadata: ['via' => $this->via],
                outcome: AuthAuditLogService::OUTCOME_SUCCESS,
                resourceType: 'authentication',
                resourceId: $user->getKey(),
            );
        } catch (\Throwable $e) {
            Log::warning('[RecordPostLoginActivityJob] auditLog failed.', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }

        // ── Email verified device upsert ───────────────────────────────────
        try {
            \App\Modules\Authentication\Models\EmailVerifiedDevice::query()->updateOrCreate(
                ['user_id' => $user->getKey()],
                [
                    'fingerprint' => $this->deviceFingerprint,
                    'verified_at' => now(),
                    'ip_address' => $this->ipAddress,
                    'user_agent' => $this->userAgent,
                    'metadata' => ['updated_via' => 'successful_login'],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('[RecordPostLoginActivityJob] emailVerifiedDevice upsert failed.', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
