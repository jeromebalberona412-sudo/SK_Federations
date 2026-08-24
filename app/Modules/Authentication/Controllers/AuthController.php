<?php

namespace App\Modules\Authentication\Controllers;

use App\Modules\Authentication\Services\AuthenticationService;
use App\Modules\Authentication\Services\EmailVerificationDeviceService;
use App\Modules\Authentication\Services\PasswordResetService;
use App\Modules\Authentication\Services\TenantContextService;
use App\Modules\Profile\Services\PasswordChangeService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        protected AuthenticationService $authenticationService,
        protected TenantContextService $tenantContextService,
        protected PasswordResetService $passwordResetService,
        protected EmailVerificationDeviceService $emailVerificationDeviceService,
        protected PasswordChangeService $passwordChangeService,
    ) {}

    public function showLogin(): View
    {
        return view('authentication::login');
    }

    public function showVerifyNotice(Request $request): View|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('skfed.verification.wait');
    }

    public function showVerificationWait(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User|null $user */
            $user = $request->user()?->fresh();

            if ($user !== null && $user->hasVerifiedEmail()) {
                $this->clearVerificationSession($request);

                return redirect()->route('dashboard');
            }

            if ($user !== null) {
                Auth::logout();
                $this->authenticationService->restoreVerificationPending($user, $request);
            }
        }

        $pending = $this->resolveVerificationPending($request);

        if ($pending === null) {
            return redirect()->route('login')->withErrors([
                'verification' => 'No verification session is currently pending.',
            ]);
        }

        $expiresAt = Carbon::parse((string) ($pending['expires_at'] ?? now()->toIso8601String()));

        if ($expiresAt->isPast()) {
            $this->clearVerificationSession($request, $pending);

            return redirect()->route('login')->withErrors([
                'verification' => 'Verification session expired. Please sign in again.',
            ]);
        }

        if ($this->canCompleteVerifiedLogin($pending)) {
            $user = User::query()->find((int) ($pending['user_id'] ?? 0))?->fresh();

            if ($user !== null) {
                $this->authenticationService->completeEmailVerificationLogin($user, $request, $pending);
                $this->clearVerificationSession($request, $pending);

                return redirect()->route('dashboard');
            }
        }

        $showNotification = (bool) session('resend_started');

        if (! $showNotification && ! ($pending['device_notified'] ?? false)) {
            $showNotification = true;
            $pending['device_notified'] = true;
            $request->session()->put('sk_fed_email_verification_pending', $pending);
        }

        $resendStarted = (bool) session('resend_started');
        $deliveryFailed = (bool) $request->session()->get('sk_fed_verification_delivery_failed', false);
        $initialEmailSent = (bool) ($pending['email_sent'] ?? false);

        if ($resendStarted) {
            $notificationBody = 'Verification email resent. Check your inbox.';
        } elseif ($initialEmailSent) {
            $notificationBody = 'Verification email sent. Check your inbox.';
        } elseif ($deliveryFailed) {
            $notificationBody = 'We could not reach your email provider right now. Your verification session is still valid — please request a new email in a moment or check spam.';
        } else {
            $notificationBody = 'Verification email sent. Check your inbox.';
        }

        return view('authentication::verify-wait', [
            'email' => (string) ($pending['email'] ?? ''),
            'expiresAtIso' => $expiresAt->toIso8601String(),
            'userId' => (int) ($pending['user_id'] ?? 0),
            'sessionKey' => sha1((string) ($pending['started_at'] ?? '').'|'.(string) ($pending['email'] ?? '')),
            'resendCooldown' => $resendStarted
                ? $this->emailVerificationDeviceService->resendCooldownRemaining($pending)
                : 0,
            'resendStarted' => $resendStarted,
            'showNotification' => $showNotification,
            'notificationBody' => $notificationBody,
            'deliveryFailed' => $deliveryFailed,
            'emailSent' => $initialEmailSent || $resendStarted,
        ]);
    }

    public function checkVerificationStatus(Request $request): JsonResponse
    {
        if (Auth::check()) {
            /** @var User|null $user */
            $user = $request->user()?->fresh();

            if ($user !== null && $user->hasVerifiedEmail()) {
                $this->clearVerificationSession($request);

                return response()->json([
                    'state' => 'verified',
                    'redirect' => route('dashboard'),
                ]);
            }

            if ($user !== null) {
                Auth::logout();
                $this->authenticationService->restoreVerificationPending($user, $request);
            }
        }

        $pending = $this->resolveVerificationPending($request);

        if ($pending === null) {
            return response()->json([
                'state' => 'pending',
                'message' => 'Waiting for email verification...',
            ]);
        }

        $expiresAt = Carbon::parse((string) ($pending['expires_at'] ?? now()->toIso8601String()));

        if ($expiresAt->isPast()) {
            $this->clearVerificationSession($request, $pending);

            return response()->json([
                'state' => 'expired',
                'message' => 'Verification session expired.',
            ], 410);
        }

        $user = User::query()->find((int) ($pending['user_id'] ?? 0))?->fresh();

        if ($user === null) {
            $this->clearVerificationSession($request, $pending);

            return response()->json([
                'state' => 'expired',
                'message' => 'User not found for this verification session.',
            ], 404);
        }

        if (! $this->canCompleteVerifiedLogin($pending, $user)) {
            return response()->json([
                'state' => 'pending',
                'expires_at' => $expiresAt->toIso8601String(),
                'seconds_remaining' => max(0, now()->diffInSeconds($expiresAt, false)),
                'resend_cooldown' => $this->emailVerificationDeviceService->resendCooldownRemaining($pending),
            ]);
        }

        $this->authenticationService->completeEmailVerificationLogin($user, $request, $pending);
        $this->clearVerificationSession($request, $pending);

        return response()->json([
            'state' => 'verified',
            'redirect' => route('dashboard'),
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $this->authenticationService->recordHeartbeat($request);

        return response()->json([
            'ok' => true,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function resendVerification(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'session_key' => ['nullable', 'string', 'max:64'],
        ]);

        if ($request->filled('session_key')) {
            $request->query->set('session_key', (string) $validated['session_key']);
        }

        if (Auth::check()) {
            /** @var User|null $user */
            $user = $request->user()?->fresh();

            if ($user !== null && $user->hasVerifiedEmail()) {
                $this->clearVerificationSession($request);

                if ($request->expectsJson()) {
                    return response()->json([
                        'ok' => true,
                        'state' => 'verified',
                        'message' => 'Email is already verified. Redirecting to dashboard...',
                        'redirect' => route('dashboard'),
                    ]);
                }

                return redirect()->route('dashboard');
            }

            if ($user !== null) {
                Auth::logout();
                $this->authenticationService->restoreVerificationPending($user, $request);
            }
        }

        $pending = $this->resolveVerificationPending($request);

        if ($pending === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No verification session is currently pending.',
                ], 404);
            }

            return redirect()->route('login')->withErrors([
                'verification' => 'No verification session is currently pending.',
            ]);
        }

        $remaining = $this->emailVerificationDeviceService->resendCooldownRemaining($pending);

        if ($remaining > 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => "Please wait {$remaining} seconds before resending.",
                    'resend_cooldown' => $remaining,
                ], 429);
            }

            return redirect()
                ->route('skfed.verification.wait')
                ->withErrors([
                    'email' => "Please wait {$remaining} seconds before resending.",
                ]);
        }

        $user = User::query()->find((int) ($pending['user_id'] ?? 0));

        if ($user === null || strtolower((string) $user->email) !== strtolower((string) $validated['email'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Unable to resend verification for this session.',
                ], 422);
            }

            return redirect()
                ->route('skfed.verification.wait')
                ->withErrors([
                    'email' => 'Unable to resend verification for this session.',
                ]);
        }

        if ($this->canCompleteVerifiedLogin($pending, $user)) {
            $this->authenticationService->completeEmailVerificationLogin($user, $request, $pending);
            $this->clearVerificationSession($request, $pending);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'state' => 'verified',
                    'message' => 'Email is already verified. Redirecting to dashboard...',
                    'redirect' => route('dashboard'),
                ]);
            }

            return redirect()->route('dashboard');
        }

        try {
            $user->sendEmailVerificationNotification();
            $pending['resend_last_sent_at'] = now()->toIso8601String();
            $pending['email_sent'] = true;
            $request->session()->put('sk_fed_email_verification_pending', $pending);
            $request->session()->forget('sk_fed_verification_delivery_failed');

            $cooldownSeconds = $this->emailVerificationDeviceService->resendCooldownRemaining($pending);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Verification email resent.',
                    'resend_cooldown' => max(1, $cooldownSeconds),
                ]);
            }

            return redirect()
                ->route('skfed.verification.wait')
                ->with('status', 'Verification email resent.')
                ->with('resend_started', true);
        } catch (\Throwable $exception) {
            Log::error('Email verification resend threw exception', [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'exception' => $exception->getMessage(),
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            $pending['email_sent'] = false;
            $pending['last_error_message'] = $exception->getMessage();
            $request->session()->put('sk_fed_email_verification_pending', $pending);
            $request->session()->put('sk_fed_verification_delivery_failed', true);

            $errorMessage = 'Unable to send the verification email right now. Please try again later.';
            $cooldownSeconds = 10;

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $errorMessage,
                    'resend_cooldown' => $cooldownSeconds,
                    'delivery_failed' => true,
                ], 503);
            }

            return redirect()
                ->route('skfed.verification.wait')
                ->withErrors(['email' => $errorMessage])
                ->with('resend_started', false);
        }
    }

    public function showVerificationSuccess(Request $request): View|RedirectResponse
    {
        if (Auth::check() && $request->user()?->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('authentication::verify-success', [
            'redirectUrl' => route('login'),
        ]);
    }

    public function cancelVerificationWait(Request $request): RedirectResponse
    {
        $this->clearVerificationSession($request);

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        if (! URL::hasValidSignature($request)) {
            return redirect()->route('login')->withErrors([
                'verification' => 'The verification link is invalid or expired.',
            ]);
        }

        $user = User::query()->find($id);

        if ($user === null || ! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect()->route('login')->withErrors([
                'verification' => 'The verification link is invalid or expired.',
            ]);
        }

        $wasVerified = $user->hasVerifiedEmail();

        if (! $wasVerified) {
            $user->markEmailAsVerified();
        } else {
            $currentVerifiedAt = $user->email_verified_at;
            $refreshedVerifiedAt = now();

            if ($currentVerifiedAt !== null && $refreshedVerifiedAt->lessThanOrEqualTo($currentVerifiedAt)) {
                $refreshedVerifiedAt = $currentVerifiedAt->copy()->addSecond();
            }

            $user->forceFill(['email_verified_at' => $refreshedVerifiedAt])->save();
        }

        event(new Verified($user));

        $pending = $request->session()->get('sk_fed_email_verification_pending');

        if (! is_array($pending) || (int) ($pending['user_id'] ?? 0) !== (int) $user->getKey()) {
            $pending = ['remember_device' => false];
        }

        $this->authenticationService->completeEmailVerificationLogin($user, $request, $pending);
        $this->clearVerificationSession($request, is_array($pending) ? $pending : null);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            $this->authenticationService->clearSessionOwnershipOnLogout($user, $request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgotPassword(): View
    {
        return view('authentication::forgot-password');
    }

    /**
     * Show the /forgot-password/verify-email page.
     *
     * State is persisted in the session as 'sk_fed_fp_verify' so that
     * refreshing the page stays on this view instead of bouncing back
     * to /forgot-password.
     *
     * Session key 'sk_fed_fp_verify' shape:
     *   email            string  — address the link was sent to
     *   sent_at          string  — ISO-8601 of when the link was last sent
     *   resend_available_at string — ISO-8601 of when resend is allowed again
     *   expires_at       string  — ISO-8601 after which the whole session is stale
     */
    public function showForgotPasswordVerifyEmail(Request $request): View|RedirectResponse
    {
        // Accept state from either the session flash (first arrival) or the
        // persistent session key (subsequent refreshes).
        if (session('password_reset_sent')) {
            $email  = (string) session('password_reset_email', '');
            $sentAt = now();

            $state = [
                'email'               => $email,
                'sent_at'             => $sentAt->toIso8601String(),
                'resend_available_at' => $sentAt->copy()->addSeconds(60)->toIso8601String(),
                'expires_at'          => $sentAt->copy()->addHours(2)->toIso8601String(),
            ];

            $request->session()->put('sk_fed_fp_verify', $state);
        }

        $state = $request->session()->get('sk_fed_fp_verify');

        if (! is_array($state) || empty($state['email'])) {
            return redirect()->route('password.request');
        }

        // If the overall session has expired redirect back to start
        $expiresAt = Carbon::parse((string) ($state['expires_at'] ?? now()->toIso8601String()));
        if ($expiresAt->isPast()) {
            $request->session()->forget('sk_fed_fp_verify');
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Your password reset session has expired. Please start again.']);
        }

        $resendAvailableAt = Carbon::parse((string) ($state['resend_available_at'] ?? now()->toIso8601String()));

        return view('authentication::fp-verify-email', [
            'email'              => (string) $state['email'],
            'resendAvailableAt'  => $resendAvailableAt->toIso8601String(),
            'resendCooldownSecs' => max(0, (int) now()->diffInSeconds($resendAvailableAt, false)),
        ]);
    }

    /**
     * POST /forgot-password/resend
     *
     * Re-sends the password reset link and enforces the backend cooldown.
     * Returns JSON so the dedicated JS can handle the response without a
     * full page reload.
     */
    public function resendForgotPasswordEmail(Request $request): JsonResponse
    {
        $state = $request->session()->get('sk_fed_fp_verify');

        if (! is_array($state) || empty($state['email'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'No active password reset session. Please start again.',
            ], 404);
        }

        $expiresAt = Carbon::parse((string) ($state['expires_at'] ?? now()->toIso8601String()));
        if ($expiresAt->isPast()) {
            $request->session()->forget('sk_fed_fp_verify');
            return response()->json([
                'ok'      => false,
                'message' => 'Your password reset session has expired. Please start again.',
                'expired' => true,
            ], 410);
        }

        $resendAvailableAt = Carbon::parse((string) ($state['resend_available_at'] ?? now()->toIso8601String()));
        $remainingSecs     = (int) now()->diffInSeconds($resendAvailableAt, false);

        if ($remainingSecs > 0) {
            return response()->json([
                'ok'                 => false,
                'message'            => "Please wait {$remainingSecs} seconds before resending.",
                'resend_available_at' => $resendAvailableAt->toIso8601String(),
                'cooldown_remaining' => $remainingSecs,
            ], 429);
        }

        // Send the link
        try {
            $this->passwordResetService->sendResetLink($request, (string) $state['email']);
        } catch (ValidationException $exception) {
            return response()->json([
                'ok'      => false,
                'message' => $exception->errors()['email'][0] ?? 'Unable to send reset link.',
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok'      => false,
                'message' => 'Unable to send the reset link right now. Please try again.',
            ], 500);
        }

        $newResendAvailableAt = now()->addSeconds(60);

        // Persist updated cooldown
        $state['resend_available_at'] = $newResendAvailableAt->toIso8601String();
        $state['sent_at']             = now()->toIso8601String();
        $request->session()->put('sk_fed_fp_verify', $state);

        return response()->json([
            'ok'                 => true,
            'message'            => 'A new password reset link has been sent to your email.',
            'resend_available_at' => $newResendAvailableAt->toIso8601String(),
            'cooldown_remaining' => 60,
        ]);
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        if (config('fortify.lowercase_usernames') && $request->has('email')) {
            $request->merge([
                'email' => Str::lower((string) $request->input('email')),
            ]);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:100'],
        ]);

        try {
            $this->passwordResetService->sendResetLink($request, (string) $validated['email']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('password.verify-email')
            ->with('status', 'A password reset link has been sent to your email address.')
            ->with('password_reset_sent', true)
            ->with('password_reset_email', (string) $validated['email']);
    }

    public function showResetPassword(Request $request, string $token): View|RedirectResponse
    {
        $email = Str::lower((string) $request->query('email', ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'The password reset link is invalid or incomplete.',
            ]);
        }

        return view('authentication::reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        if (config('fortify.lowercase_usernames') && $request->has('email')) {
            $request->merge([
                'email' => Str::lower((string) $request->input('email')),
            ]);
        }

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:100'],
            'password' => [
                'required',
                'string',
                'max:'.(int) config('sk_fed_auth.password_reset.password.max_length', 64),
                'confirmed',
                PasswordRule::min((int) config('sk_fed_auth.password_reset.password.min_length', 8))
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $this->passwordResetService->resetPassword($request, $validated);

        return redirect()->route('password.reset.success')->with('status', 'Your password has been updated successfully. Please log in again.');
    }

    public function showPasswordResetSuccess(): View
    {
        return view('authentication::password-reset-success');
    }

    public function showChangePassword(Request $request): View|RedirectResponse
    {
        $user = $request->user()->fresh();

        $this->passwordChangeService->clearExpiredPending($user);
        $user = $user->fresh();

        if ($this->passwordChangeService->hasPendingChange($user)) {
            return redirect()->route('change-password.verify');
        }

        return view('profile::change-password', [
            'user' => $user,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user()->fresh();

        $this->passwordChangeService->clearExpiredPending($user);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'max:'.(int) config('sk_fed_auth.password_reset.password.max_length', 64),
                PasswordRule::min((int) config('sk_fed_auth.password_reset.password.min_length', 8))
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        if (strcasecmp((string) $validated['email'], (string) $user->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => ['The email address does not match your account.'],
            ]);
        }

        try {
            $this->passwordChangeService->requestChange($user, (string) $validated['password']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('change-password.verify')
            ->with('status', 'Verification link sent to your email address.')
            ->with('verification_sent', true);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveVerificationPending(Request $request): ?array
    {
        $pending = $request->session()->get('sk_fed_email_verification_pending');

        if (is_array($pending)) {
            return $pending;
        }

        $sessionKey = (string) $request->query('session_key', '');
        $userId = (int) $request->query('user_id', 0);

        if ($sessionKey !== '') {
            $pending = $this->authenticationService->retrieveVerificationWatch($sessionKey);

            if (is_array($pending)) {
                return $this->restoreVerificationPending($request, $pending);
            }
        }

        if ($userId > 0) {
            $pending = $this->authenticationService->retrieveVerificationWatchByUserId($userId);

            if (is_array($pending)) {
                return $this->restoreVerificationPending($request, $pending);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>
     */
    protected function restoreVerificationPending(Request $request, array $pending): array
    {
        $request->session()->put('sk_fed_email_verification_pending', $pending);

        return $pending;
    }

    /**
     * @param  array<string, mixed>|null  $pending
     */
    protected function clearVerificationSession(Request $request, ?array $pending = null): void
    {
        $pending ??= $request->session()->get('sk_fed_email_verification_pending');

        if (is_array($pending)) {
            $this->authenticationService->clearVerificationWatch($pending);
        }

        $request->session()->forget('sk_fed_email_verification_pending');
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    protected function canCompleteVerifiedLogin(array $pending, ?User $user = null): bool
    {
        $user ??= User::query()->find((int) ($pending['user_id'] ?? 0))?->fresh();

        if ($user === null || ! $user->hasVerifiedEmail() || $user->email_verified_at === null) {
            return false;
        }

        $startedAt = Carbon::parse((string) ($pending['started_at'] ?? now()->toIso8601String()));

        return $user->email_verified_at->greaterThanOrEqualTo($startedAt->copy()->subSeconds(5));
    }
}
