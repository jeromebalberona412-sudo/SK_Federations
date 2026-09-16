<?php

namespace App\Modules\Profile\Controllers;

use App\Modules\Profile\Models\Barangay;
use App\Modules\Profile\Models\OfficialProfile;
use App\Modules\Profile\Services\EmailChangeService;
use App\Modules\Profile\Services\PasswordChangeService;
use App\Modules\Profile\Services\ProfileService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\User;
use App\Rules\ValidEmailAddress;
use App\Services\SkFedPageCache;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const PASSWORD_CHANGE_CONFIRMED_SESSION_KEY = 'password_change_confirmed_this_session';

    private const EMAIL_CHANGE_CONFIRMED_SESSION_KEY = 'email_change_confirmed_this_session';

    public function __construct(
        private readonly EmailChangeService $emailChangeService,
        private readonly PasswordChangeService $passwordChangeService,
        private readonly ProfileService $profileService,
    ) {}

    public function index(Request $request): View|ViewContract
    {
        /** @var User $user */
        $user = $request->user()->fresh();

        $officialProfile = null;
        $barangays = collect();
        $barangay = null;

        if (Schema::hasTable('official_profiles')) {
            $officialProfile = OfficialProfile::query()
                ->where('user_id', $user->id)
                ->first();
        }

        if (Schema::hasTable('barangays')) {
            /** @var Collection<int, Barangay> $barangays */
            $barangays = app(SkFedPageCache::class)->remember(
                'profile.barangays',
                fn () => Barangay::query()->select(['id', 'name'])->orderBy('name')->get(),
                [],
                300
            );

            if ($user->barangay_id) {
                $barangay = $barangays->firstWhere('id', $user->barangay_id);
            }
        }

        $profile = $this->profileService->getDisplayData($user, $officialProfile, $barangay);

        return view('profile::profile', [
            'user' => $user,
            'profile' => $profile,
            'officialProfile' => $officialProfile,
            'barangays' => $barangays,
        ]);
    }

    public function showChangeEmail(Request $request): View|RedirectResponse|Response
    {
        $user = $request->user()->fresh();

        $this->emailChangeService->clearExpiredPending($user);
        $user = $user->fresh();

        // Always show the first step. Leaving verify (Back to Profile) cancels pending.
        $snapshot = $this->emailChangeService->cachedUserSnapshot($user);

        return response()
            ->view('profile::change-email', [
                'user' => $user,
                'currentEmail' => $snapshot['email'] ?: (string) $user->email,
            ])
            ->header('Cache-Control', 'private, max-age=60');
    }

    public function requestChangeEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_email' => ['required', 'email', 'max:255'],
            'new_email' => ['required', 'string', 'max:'.ValidEmailAddress::MAX_LENGTH, 'different:current_email', new ValidEmailAddress],
            'password' => ['required', 'string', 'max:64'],
        ]);

        try {
            $this->emailChangeService->requestChange(
                $request->user(),
                $validated['current_email'],
                $validated['new_email'],
                $validated['password'],
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('change-email.verify')
            ->with('status', 'Verification link sent to your new email address.')
            ->with('verification_sent', true);
    }

    public function showChangeEmailVerify(Request $request): View|RedirectResponse|Response
    {
        $user = $request->user()->fresh();

        $this->emailChangeService->clearExpiredPending($user);
        $user = $user->fresh();

        if (! $this->emailChangeService->hasPendingChange($user)) {
            if ($this->emailChangeCompletedInThisSession($request) || $this->wasEmailChangeConfirmed($request, $user)) {
                return redirect()
                    ->route('dashboard')
                    ->with('success', 'Email changed successfully.');
            }

            $request->session()->forget('email_change_verify_active');

            return redirect()->route('change-email');
        }

        $request->session()->put('email_change_verify_active', true);
        $snapshot = $this->emailChangeService->cachedUserSnapshot($user);

        return response()
            ->view('profile::change-email-verify', [
                'user' => $user,
                'currentEmail' => $snapshot['email'] ?: (string) $user->email,
                'pendingEmail' => $snapshot['pending_email'] ?: (string) $user->pending_email,
                'resendCooldown' => max(
                    $this->emailChangeService->resendCooldownRemaining($user),
                    $request->session()->get('verification_sent') ? 60 : 0
                ),
                'freshVerification' => (bool) $request->session()->get('verification_sent'),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function checkChangeEmailVerifyStatus(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();

        if ($this->emailChangeService->hasPendingChange($user)) {
            return response()->json([
                'state' => 'pending',
                'resend_cooldown' => $this->emailChangeService->resendCooldownRemaining($user),
            ]);
        }

        if ($this->emailChangeCompletedInThisSession($request) || $this->wasEmailChangeConfirmed($request, $user)) {
            $request->session()->forget('email_change_verify_active');
            $request->session()->flash('success', 'Email changed successfully.');

            return response()->json([
                'state' => 'confirmed',
                'redirect' => $this->sameOriginRoute('dashboard'),
                'message' => 'Email changed successfully. Taking you to your dashboard...',
            ]);
        }

        return response()->json([
            'state' => 'cancelled',
            'redirect' => $this->sameOriginRoute('change-email'),
            'message' => 'Email change request is no longer active.',
        ]);
    }

    public function resendChangeEmail(Request $request): RedirectResponse
    {
        try {
            $this->emailChangeService->resend($request->user()->fresh());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Verification email resent.');
    }

    public function cancelChangeEmail(Request $request): RedirectResponse
    {
        $this->emailChangeService->cancel($request->user()->fresh());
        $request->session()->forget('email_change_verify_active');

        $toProfile = $request->input('redirect') === 'profile';

        return redirect()
            ->route($toProfile ? 'profile' : 'change-email')
            ->with('status', 'Email change request cancelled.');
    }

    public function confirmChangeEmail(Request $request, int $id, string $token): RedirectResponse
    {
        try {
            $user = $this->emailChangeService->confirm($id, $token);
        } catch (ValidationException $exception) {
            return $this->redirectAfterEmailChangeConfirmFailure($request, $id, $exception);
        }

        return $this->finishEmailChangeFromEmailLink($request, $user);
    }

    public function showChangeEmailSuccess(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('email_change_success')) {
            return redirect()->route('login');
        }

        return view('profile::change-email-success', [
            'newEmail' => (string) $request->session()->get('new_email', ''),
        ]);
    }

    public function showSetPasswordAfterEmailChange(Request $request, int $id, string $token): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('status', 'Email was changed. Please sign in with your new email and current password.');
    }

    public function updateSetPasswordAfterEmailChange(Request $request, int $id, string $token): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('status', 'Email was changed. Please sign in with your new email and current password.');
    }

    public function showChangePasswordVerify(Request $request): View|RedirectResponse
    {
        $user = $request->user()->fresh();

        $this->passwordChangeService->clearExpiredPending($user);
        $user = $user->fresh();

        if (! $this->passwordChangeService->hasPendingChange($user)) {
            if ($this->passwordChangeCompletedInThisSession($request) || $this->wasPasswordChangeConfirmed($request, $user)) {
                return redirect()
                    ->route('dashboard')
                    ->with('success', 'Password changed successfully.');
            }

            $request->session()->forget('password_change_verify_active');

            return redirect()->route('change-password');
        }

        $request->session()->put('password_change_verify_active', true);
        $request->session()->forget('verification_sent');

        return view('profile::change-password-verify', [
            'user' => $user,
            'resendCooldown' => $this->passwordChangeService->resendCooldownRemaining($user),
        ]);
    }

    public function checkChangePasswordVerifyStatus(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();

        if ($this->passwordChangeService->hasPendingChange($user)) {
            return response()->json([
                'state' => 'pending',
                'resend_cooldown' => $this->passwordChangeService->resendCooldownRemaining($user),
            ]);
        }

        if ($this->passwordChangeCompletedInThisSession($request) || $this->wasPasswordChangeConfirmed($request, $user)) {
            $request->session()->forget('password_change_verify_active');
            $request->session()->flash('success', 'Password changed successfully.');

            return response()->json([
                'state' => 'confirmed',
                'redirect' => $this->sameOriginRoute('dashboard'),
                'message' => 'Password changed successfully. Taking you to your dashboard...',
            ]);
        }

        return response()->json([
            'state' => 'cancelled',
            'redirect' => $this->sameOriginRoute('change-password'),
            'message' => 'Password change request is no longer active.',
        ]);
    }

    public function resendChangePassword(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $this->passwordChangeService->resend($request->user()->fresh());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: 'Unable to resend verification email. Please try again.';

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'resend_cooldown' => $this->passwordChangeService->resendCooldownRemaining($request->user()->fresh()),
                ], 422);
            }

            return back()->withErrors($exception->errors());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Verification email resent. Check your inbox.',
                'resend_cooldown' => 60,
            ]);
        }

        return back()->with('status', 'Verification email resent.');
    }

    public function cancelChangePassword(Request $request): RedirectResponse
    {
        $this->passwordChangeService->cancel($request->user()->fresh());
        $request->session()->forget('password_change_verify_active');
        $this->passwordChangeService->forgetRecentlyConfirmed($request->user()->id);

        return redirect()
            ->route('change-password')
            ->with('status', 'Password change request cancelled.');
    }

    public function confirmChangePassword(Request $request, int $id, string $token): RedirectResponse
    {
        try {
            $user = $this->passwordChangeService->confirm($id, $token);
        } catch (ValidationException $exception) {
            return $this->redirectAfterPasswordChangeConfirmFailure($request, $id, $exception);
        }

        return $this->finishPasswordChangeFromEmailLink($request, $user);
    }

    public function showChangePasswordSuccess(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('password_change_success')) {
            return redirect()->route('login');
        }

        return view('profile::change-password-success', [
            'email' => (string) $request->session()->get('password_change_email', ''),
        ]);
    }

    protected function wasPasswordChangeConfirmed(Request $request, User $user): bool
    {
        return $this->passwordChangeService->wasRecentlyConfirmed($user->id);
    }

    protected function passwordChangeCompletedInThisSession(Request $request): bool
    {
        return (bool) $request->session()->get(self::PASSWORD_CHANGE_CONFIRMED_SESSION_KEY, false);
    }

    protected function wasEmailChangeConfirmed(Request $request, User $user): bool
    {
        return $this->emailChangeService->wasRecentlyCompleted($user->id);
    }

    protected function emailChangeCompletedInThisSession(Request $request): bool
    {
        return (bool) $request->session()->get(self::EMAIL_CHANGE_CONFIRMED_SESSION_KEY, false);
    }

    protected function sameOriginRoute(string $name, array $parameters = []): string
    {
        return route($name, $parameters, false);
    }

    protected function finishPasswordChangeFromEmailLink(Request $request, User $user): RedirectResponse
    {
        $authenticatedId = Auth::id();

        if ($authenticatedId !== null && (int) $authenticatedId !== (int) $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } elseif (Auth::check()) {
            $request->session()->put(self::PASSWORD_CHANGE_CONFIRMED_SESSION_KEY, true);
            $request->session()->forget('password_change_verify_active');
        }

        $request->session()->put('password_change_success', true);
        $request->session()->put('password_change_email', $user->email);

        return redirect()->route('change-password.success');
    }

    protected function finishEmailChangeFromEmailLink(Request $request, User $user): RedirectResponse
    {
        $authenticatedId = Auth::id();

        if ($authenticatedId !== null && (int) $authenticatedId !== (int) $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } elseif (Auth::check()) {
            $request->session()->put(self::EMAIL_CHANGE_CONFIRMED_SESSION_KEY, true);
            $request->session()->forget('email_change_verify_active');
        }

        $request->session()->put('email_change_success', true);
        $request->session()->put('new_email', $user->email);

        return redirect()->route('change-email.success');
    }

    protected function redirectAfterPasswordChangeConfirmFailure(
        Request $request,
        int $id,
        ValidationException $exception,
    ): RedirectResponse {
        $message = collect($exception->errors())->flatten()->first()
            ?: 'This password change link is invalid.';

        $target = User::query()->find($id);

        if ($target !== null && $this->passwordChangeService->hasPendingChange($target)) {
            if (Auth::check() && (int) Auth::id() === $id) {
                return redirect()
                    ->route('change-password.verify')
                    ->with('error', $message);
            }

            if (Auth::check()) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', $message);
            }

            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        }

        if ($this->passwordChangeService->wasRecentlyConfirmed($id) && $target !== null) {
            return $this->finishPasswordChangeFromEmailLink($request, $target);
        }

        if (Auth::check() && (int) Auth::id() === $id) {
            return redirect()
                ->route('change-password')
                ->with('error', $message);
        }

        if (Auth::check()) {
            return redirect()
                ->route('dashboard')
                ->with('error', $message);
        }

        return redirect()
            ->route('login')
            ->withErrors($exception->errors());
    }

    protected function redirectAfterEmailChangeConfirmFailure(
        Request $request,
        int $id,
        ValidationException $exception,
    ): RedirectResponse {
        $message = collect($exception->errors())->flatten()->first()
            ?: 'This email change link is invalid.';

        $target = User::query()->find($id);

        if ($target !== null && $this->emailChangeService->hasPendingChange($target)) {
            if (Auth::check() && (int) Auth::id() === $id) {
                return redirect()
                    ->route('change-email.verify')
                    ->with('error', $message);
            }

            if (Auth::check()) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', $message);
            }

            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        }

        if ($target !== null && $this->emailChangeService->wasRecentlyCompleted($id)) {
            return $this->finishEmailChangeFromEmailLink($request, $target);
        }

        if (Auth::check() && (int) Auth::id() === $id) {
            return redirect()
                ->route('change-email')
                ->with('error', $message);
        }

        if (Auth::check()) {
            return redirect()
                ->route('dashboard')
                ->with('error', $message);
        }

        return redirect()
            ->route('login')
            ->withErrors($exception->errors());
    }

    /**
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            'max:'.(int) config('sk_fed_auth.password_reset.password.max_length', 64),
            PasswordRule::min((int) config('sk_fed_auth.password_reset.password.min_length', 8))
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ];
    }
}
