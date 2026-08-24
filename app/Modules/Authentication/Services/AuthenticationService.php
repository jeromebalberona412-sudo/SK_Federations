<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Authentication\Jobs\RecordPostLoginActivityJob;
use App\Modules\Authentication\Notifications\NewLocationLoginNotification;
use App\Modules\Shared\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthenticationService
{
    public function __construct(
        protected TenantContextService $tenantContextService,
        protected LoginSecurityService $loginSecurityService,
        protected SuspiciousLoginService $suspiciousLoginService,
        protected AuthAuditLogService $auditLogService,
        protected FeatureFlagService $featureFlagService,
        protected EmailVerificationDeviceService $emailVerificationDeviceService,
        protected TrustedDeviceService $trustedDeviceService,
        protected DeviceFingerprintService $deviceFingerprintService,
        protected BootstrapSkFedAdminService $bootstrapSkFedAdminService,
    ) {}

    public function authenticate(Request $request): ?User
    {
        $email = Str::lower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        // ── Pre-warm feature flags in a single query ───────────────────────
        // Prevents 3 individual DB hits later in the pipeline.
        $this->featureFlagService->preloadAuthFlags();

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user !== null) {
            $user = $this->bootstrapSkFedAdminService->normalizeUserIfBootstrap($user);
        }

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            $this->loginSecurityService->recordAttempt($user, $email, false, $request, ['reason' => 'invalid_credentials']);
            $this->loginSecurityService->applyFailureLockout($user, $email, $request);
            $this->auditLogService->log(
                event: 'login_failed',
                user: $user,
                request: $request,
                metadata: ['reason' => $user === null ? 'unknown_email' : 'bad_password', 'email' => $email],
                outcome: AuthAuditLogService::OUTCOME_FAILED,
                resourceType: 'authentication',
                resourceId: $user?->getKey(),
            );

            return null;
        }

        if ($this->loginSecurityService->isLocked($user)) {
            $this->loginSecurityService->recordAttempt($user, $email, false, $request, ['reason' => 'lockout_active']);
            $this->auditLogService->log(
                event: 'login_blocked_lockout',
                user: $user,
                request: $request,
                outcome: AuthAuditLogService::OUTCOME_BLOCKED,
                resourceType: 'authentication',
                resourceId: $user->getKey(),
            );

            return null;
        }

        $tenantId = $this->tenantContextService->tenantId();

        if ($this->hasEndedTerm($user)) {
            $this->auditLogService->log(
                event: 'login_blocked_term_ended',
                user: $user,
                request: $request,
                metadata: ['reason' => 'term_ended'],
                outcome: AuthAuditLogService::OUTCOME_BLOCKED,
                resourceType: 'authentication',
                resourceId: $user->getKey(),
            );

            throw new HttpResponseException(
                redirect()->route('login')->with('access_denied', [
                    'title' => 'Access Denied',
                    'message' => 'Your SK federation term has already ended. Login access is no longer available for this account.',
                ])
            );
        }

        if ($user->turnover_status === 'archived') {
            $this->auditLogService->log(
                event: 'login_blocked_archived',
                user: $user,
                request: $request,
                outcome: AuthAuditLogService::OUTCOME_BLOCKED,
                resourceType: 'authentication',
                resourceId: $user->getKey(),
            );

            throw new HttpResponseException(
                redirect()->route('login')->with('access_denied', [
                    'title' => 'Account Archived',
                    'message' => 'Your term has ended. Your account has been archived. Thank you for your service.',
                ])
            );
        }

        if ($user->isIncomingTurnoverOfficer()) {
            $this->blockIncomingTurnoverLogin($user, $request);
        }

        if (
            ! $user->canAccessFederationPortal()
            || $tenantId === null
            || (int) ($user->tenant_id ?? 0) !== $tenantId
        ) {
            $this->loginSecurityService->recordAttempt($user, $email, false, $request, ['reason' => 'role_or_tenant_mismatch']);
            $this->auditLogService->log(
                event: 'login_blocked_scope',
                user: $user,
                request: $request,
                outcome: AuthAuditLogService::OUTCOME_BLOCKED,
                resourceType: 'authentication',
                resourceId: $user->getKey(),
            );

            return null;
        }

        $rememberDevice = $request->boolean('remember');
        $isTrustedDevice = $this->trustedDeviceService->isTrusted($user, $request);

        if (! $user->hasVerifiedEmail()) {
            $this->startEmailVerificationWait(
                user: $user,
                email: $email,
                request: $request,
                reason: 'email_unverified',
                message: 'A verification email has been sent. Complete verification to continue.',
                rememberDevice: $rememberDevice,
            );

            return null;
        }

        if ($this->shouldRequireEmailVerification($user, $request, $isTrustedDevice)) {
            $this->startEmailVerificationWait(
                user: $user,
                email: $email,
                request: $request,
                reason: 'email_device_changed',
                message: 'New device detected. Email verification is required again for security.',
                rememberDevice: $rememberDevice,
            );

            return null;
        }

        $this->resolveActiveSessionConflict($user, $request);

        if ($isTrustedDevice) {
            $this->trustedDeviceService->refreshRememberCookieIfPresent($user, $request);
        } elseif ($rememberDevice) {
            $this->trustedDeviceService->rememberDevice($user, $request);
        }

        // ── Merge blocking writes: recordLogin + resetLockout in one UPDATE ─
        $this->recordLoginAndClearLockout($user, (string) $request->ip());

        // ── Dispatch all remaining post-login work to the queue ────────────
        // Suspicious detection, alert notification, login attempt record,
        // audit log, and device upsert all happen off the critical path.
        RecordPostLoginActivityJob::dispatch(
            userId: (int) $user->getKey(),
            email: $email,
            ipAddress: (string) $request->ip(),
            userAgent: (string) ($request->userAgent() ?? ''),
            deviceFingerprint: $this->deviceFingerprintService->fingerprint($request),
            isSuspiciousCheckEnabled: $this->featureFlagService->enabled('features.suspicious_login_detection'),
            isAlertEnabled: $this->featureFlagService->enabled('features.login_alert_notifications'),
            via: 'password',
        );

        return $user;
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    public function completeEmailVerificationLogin(User $user, Request $request, array $pending): void
    {
        Auth::login($user, true);

        // Merge recordLogin + resetLockout into a single UPDATE
        $this->recordLoginAndClearLockout($user, (string) $request->ip());

        $this->claimCurrentSession($user, $request);
        $this->emailVerificationDeviceService->markVerifiedDeviceFromPending($user, $pending);

        try {
            $this->trustedDeviceService->trust($user, $request);
        } catch (\Throwable $exception) {
            report($exception);
        }

        if (! empty($pending['remember_device'])) {
            try {
                $this->trustedDeviceService->rememberDevice(
                    $user,
                    $request,
                    (string) ($pending['fingerprint'] ?? ''),
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        // Dispatch non-blocking post-login work
        RecordPostLoginActivityJob::dispatch(
            userId: (int) $user->getKey(),
            email: (string) $user->email,
            ipAddress: (string) $request->ip(),
            userAgent: (string) ($request->userAgent() ?? ''),
            deviceFingerprint: $this->deviceFingerprintService->fingerprint($request),
            isSuspiciousCheckEnabled: false, // device-verified logins skip suspicious detection
            isAlertEnabled: false,
            via: 'email_verification',
        );
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    public function verificationSessionKey(array $pending): string
    {
        return sha1((string) ($pending['started_at'] ?? '').'|'.(string) ($pending['email'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    public function storeVerificationWatch(array $pending): void
    {
        $sessionKey = $this->verificationSessionKey($pending);
        $expiresAt = Carbon::parse((string) ($pending['expires_at'] ?? now()->addHour()->toIso8601String()));

        Cache::put(
            $this->verificationWatchCacheKey($sessionKey),
            $pending,
            $expiresAt,
        );

        Cache::put(
            $this->verificationWatchUserCacheKey((int) ($pending['user_id'] ?? 0)),
            $pending,
            $expiresAt,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retrieveVerificationWatch(string $sessionKey): ?array
    {
        if ($sessionKey === '') {
            return null;
        }

        $pending = Cache::get($this->verificationWatchCacheKey($sessionKey));

        return is_array($pending) ? $pending : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retrieveVerificationWatchByUserId(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $pending = Cache::get($this->verificationWatchUserCacheKey($userId));

        return is_array($pending) ? $pending : null;
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    public function clearVerificationWatch(array $pending): void
    {
        Cache::forget($this->verificationWatchCacheKey($this->verificationSessionKey($pending)));
        Cache::forget($this->verificationWatchUserCacheKey((int) ($pending['user_id'] ?? 0)));
    }

    public function recordHeartbeat(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return;
        }

        $currentSessionId = $request->session()->getId();
        $activeSessionId = (string) ($user->active_session_id ?? '');

        if ($activeSessionId === '') {
            $this->claimCurrentSession($user, $request);

            return;
        }

        if ($activeSessionId !== $currentSessionId) {
            return;
        }

        if ($this->hasColumn('users', 'last_seen')) {
            $user->forceFill([
                'last_seen' => now(),
                'last_ip' => (string) $request->ip(),
            ])->save();
        }
    }

    public function claimCurrentSession(User $user, Request $request): void
    {
        if (! $this->hasColumn('users', 'active_session_id')) {
            return;
        }

        $updates = [
            'active_session_id' => $request->session()->getId(),
        ];

        if ($this->hasColumn('users', 'last_seen')) {
            $updates['last_seen'] = now();
        }

        if ($this->hasColumn('users', 'active_device')) {
            $updates['active_device'] = $this->userAgent($request);
        }

        if ($this->hasColumn('users', 'last_ip')) {
            $updates['last_ip'] = (string) $request->ip();
        }

        $user->forceFill($updates)->save();
    }

    public function isSessionActive(User $user): bool
    {
        if (empty($user->active_session_id) || $user->last_seen === null) {
            return false;
        }

        $timeoutSeconds = (int) config('sk_fed_auth.single_session.heartbeat_timeout_seconds', 120);

        if ($user->last_seen->copy()->addSeconds($timeoutSeconds)->isPast()) {
            return false;
        }

        if ((string) config('session.driver') !== 'database') {
            return true;
        }

        return DB::table('sessions')
            ->where('id', (string) $user->active_session_id)
            ->where('user_id', $user->getKey())
            ->where('last_activity', '>=', now()->subSeconds($timeoutSeconds)->timestamp)
            ->exists();
    }

    public function clearSessionOwnershipOnLogout(User $user, Request $request): void
    {
        $this->auditLogService->log(
            event: 'logout',
            user: $user,
            request: $request,
            outcome: AuthAuditLogService::OUTCOME_SUCCESS,
            resourceType: 'session',
            resourceId: $request->session()->getId(),
        );

        if ($this->hasColumn('users', 'active_session_id')) {
            $currentSessionId = $request->session()->getId();

            if ((string) $user->active_session_id === $currentSessionId) {
                $user->forceFill(['active_session_id' => null])->save();
            }
        }
    }

    protected function resolveActiveSessionConflict(User $user, Request $request): void
    {
        if (! $this->hasColumn('users', 'active_session_id')) {
            return;
        }

        $activeSessionId = (string) ($user->active_session_id ?? '');

        if ($activeSessionId === '' || $activeSessionId === $request->session()->getId()) {
            return;
        }

        if (! $this->isSessionActive($user)) {
            $this->clearStaleSessionOwnership($user);

            return;
        }

        $this->invalidatePreviousSession($user);
        $this->clearStaleSessionOwnership($user);

        $this->auditLogService->log(
            event: 'login_replaced_active_session',
            user: $user,
            request: $request,
            outcome: AuthAuditLogService::OUTCOME_SUCCESS,
            resourceType: 'authentication',
            resourceId: $user->getKey(),
            metadata: [
                'previous_session_id' => $activeSessionId,
            ],
        );
    }

    protected function clearStaleSessionOwnership(User $user): void
    {
        $updates = ['active_session_id' => null];

        if ($this->hasColumn('users', 'last_seen')) {
            $updates['last_seen'] = null;
        }

        if ($this->hasColumn('users', 'active_device')) {
            $updates['active_device'] = null;
        }

        if ($this->hasColumn('users', 'last_ip')) {
            $updates['last_ip'] = null;
        }

        $user->forceFill($updates)->save();
    }

    protected function invalidatePreviousSession(User $user): void
    {
        if (! $this->hasColumn('users', 'active_session_id')) {
            return;
        }

        $previousSessionId = (string) ($user->active_session_id ?? '');

        if ($previousSessionId === '' || ! Schema::hasTable('sessions')) {
            return;
        }

        try {
            DB::table('sessions')->where('id', $previousSessionId)->delete();
        } catch (\Throwable) {
            // Best effort
        }
    }

    protected function shouldRequireEmailVerification(User $user, Request $request, bool $isTrustedDevice): bool
    {
        if ($isTrustedDevice) {
            return false;
        }

        return $this->featureFlagService->deviceVerificationEnabled();
    }

    /**
     * Rehydrate a verification wait session without sending another email.
     *
     * @return array<string, mixed>
     */
    public function restoreVerificationPending(
        User $user,
        Request $request,
        ?bool $requiresFreshVerification = null,
    ): array {
        $existing = $request->session()->get('sk_fed_email_verification_pending');

        if (is_array($existing) && (int) ($existing['user_id'] ?? 0) === (int) $user->getKey()) {
            $this->storeVerificationWatch($existing);

            return $existing;
        }

        $cached = $this->retrieveVerificationWatchByUserId((int) $user->getKey());

        if (is_array($cached)) {
            $request->session()->put('sk_fed_email_verification_pending', $cached);

            return $cached;
        }

        $waitMinutes = (int) config('sk_fed_auth.verification.wait_minutes', 15);
        $sentAt = Carbon::now();

        $pending = [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'started_at' => $sentAt->toIso8601String(),
            'expires_at' => $sentAt->copy()->addMinutes($waitMinutes)->toIso8601String(),
            'fingerprint' => $this->deviceFingerprintService->fingerprint($request),
            'ip' => (string) $request->ip(),
            'user_agent' => $this->userAgent($request),
            'requires_fresh_verification' => $requiresFreshVerification ?? ! $user->hasVerifiedEmail(),
            'verified_at_snapshot' => $user->email_verified_at?->toIso8601String(),
            'remember_device' => false,
        ];

        $request->session()->put('sk_fed_email_verification_pending', $pending);
        $this->storeVerificationWatch($pending);

        return $pending;
    }

    protected function startEmailVerificationWait(
        User $user,
        string $email,
        Request $request,
        string $reason,
        string $message,
        bool $rememberDevice = false,
    ): void {
        $waitMinutes = (int) config('sk_fed_auth.verification.wait_minutes', 15);
        $sentAt = Carbon::now();

        $pending = [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'started_at' => $sentAt->toIso8601String(),
            'expires_at' => $sentAt->copy()->addMinutes($waitMinutes)->toIso8601String(),
            'fingerprint' => $this->deviceFingerprintService->fingerprint($request),
            'ip' => (string) $request->ip(),
            'user_agent' => $this->userAgent($request),
            'requires_fresh_verification' => $reason === 'email_device_changed',
            'verified_at_snapshot' => $user->email_verified_at?->toIso8601String(),
            'remember_device' => $rememberDevice,
            'email_sent' => false,
            'last_error_message' => null,
        ];

        try {
            $user->sendEmailVerificationNotification();
            $pending['email_sent'] = true;
            $pending['resend_last_sent_at'] = now()->toIso8601String();
            $message = 'A verification email has been sent. Complete verification to continue.';
            $request->session()->forget('sk_fed_verification_delivery_failed');
        } catch (\Throwable $e) {
            Log::error('Email verification notification threw during login', [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'reason' => $reason,
                'exception' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            $pending['email_sent'] = false;
            $pending['last_error_message'] = $e->getMessage();
            $request->session()->put('sk_fed_verification_delivery_failed', true);

            $message = 'Email verification is required. If you did not receive a verification email, please check your spam folder or request a new one.';
        }

        $request->session()->put('sk_fed_email_verification_pending', $pending);
        $this->storeVerificationWatch($pending);

        $this->loginSecurityService->recordAttempt($user, $email, false, $request, ['reason' => $reason]);
        $this->auditLogService->log(
            event: 'login_blocked_'.$reason,
            user: $user,
            request: $request,
            outcome: AuthAuditLogService::OUTCOME_BLOCKED,
            resourceType: 'authentication',
            resourceId: $user->getKey(),
            metadata: [
                'reason' => $reason,
                'email_sent' => $pending['email_sent'],
            ],
        );
        $request->session()->flash('status', $message);
        $request->session()->flash('verification_wait', true);
        $request->session()->flash('verification_email_sent', $pending['email_sent']);
    }

    protected function verificationWatchUserCacheKey(int $userId): string
    {
        return 'sk_fed_verify_user:'.$userId;
    }

    protected function verificationWatchCacheKey(string $sessionKey): string
    {
        return 'sk_fed_verify_watch:'.$sessionKey;
    }

    protected function userAgent(Request $request): string
    {
        return substr((string) ($request->userAgent() ?? 'unknown'), 0, 255);
    }

    protected function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function blockIncomingTurnoverLogin(User $user, Request $request): never
    {
        $awaitingSetup = $user->turnover_status === 'awaiting_setup'
            || $user->account_status === 'turnover_pending';

        $this->auditLogService->log(
            event: $awaitingSetup ? 'login_blocked_turnover_awaiting_setup' : 'login_blocked_turnover_pending_confirmation',
            user: $user,
            request: $request,
            outcome: AuthAuditLogService::OUTCOME_BLOCKED,
            resourceType: 'authentication',
            resourceId: $user->getKey(),
        );

        $message = $awaitingSetup
            ? 'Sorry, login is not available yet. Please complete your account setup using the email link sent to you. If the link expired after 24 hours, use Forgot Password on this page. You may sign in once federation turnover is completed and your new term starts.'
            : 'Sorry, your new term has not started yet. Please wait until the outgoing Federation President and Vice President complete the turnover process. You may log in once your term is activated.';

        throw new HttpResponseException(
            redirect()->route('login')->with('access_denied', [
                'title' => 'Please Wait for Your Term',
                'message' => $message,
            ])
        );
    }

    protected function hasEndedTerm(User $user): bool
    {
        if (! Schema::hasTable('official_terms') || ! Schema::hasTable('official_profiles')) {
            return false;
        }

        $termEnd = DB::table('official_terms')
            ->join('official_profiles', 'official_profiles.id', '=', 'official_terms.official_profile_id')
            ->where('official_profiles.user_id', $user->getKey())
            ->where('official_terms.status', 'ACTIVE')
            ->orderByDesc('official_terms.term_end')
            ->value('official_terms.term_end');

        if ($termEnd === null) {
            $termEnd = DB::table('official_terms')
                ->join('official_profiles', 'official_profiles.id', '=', 'official_terms.official_profile_id')
                ->where('official_profiles.user_id', $user->getKey())
                ->orderByDesc('official_terms.term_end')
                ->value('official_terms.term_end');
        }

        return $termEnd !== null
            && Carbon::parse((string) $termEnd)->lte(now()->startOfDay());
    }

    /**
     * Record successful login and clear any active lockout in a single UPDATE.
     */
    protected function recordLoginAndClearLockout(User $user, string $ipAddress): void
    {
        $updates = [];

        if ($this->hasColumn('users', 'last_login_at')) {
            $updates['last_login_at'] = now();
        }

        if ($this->hasColumn('users', 'last_login_ip')) {
            $updates['last_login_ip'] = $ipAddress;
        }

        if ($this->hasColumn('users', 'lockout_count')) {
            $updates['lockout_count'] = 0;
        }

        if ($this->hasColumn('users', 'lockout_until')) {
            $updates['lockout_until'] = null;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }
}
