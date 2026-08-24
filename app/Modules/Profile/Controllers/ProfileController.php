<?php

namespace App\Modules\Profile\Controllers;

use App\Modules\Profile\Models\Barangay;
use App\Modules\Profile\Models\OfficialProfile;
use App\Modules\Profile\Services\EmailChangeService;
use App\Modules\Profile\Services\PasswordChangeService;
use App\Modules\Profile\Services\ProfileService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\User;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly EmailChangeService $emailChangeService,
        private readonly PasswordChangeService $passwordChangeService,
        private readonly ProfileService $profileService,
    ) {
    }

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
            $barangays = Barangay::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get();

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

    public function showChangeEmail(Request $request): View|RedirectResponse
    {
        $user = $request->user()->fresh();

        $this->emailChangeService->clearExpiredPending($user);
        $user = $user->fresh();

        if ($this->emailChangeService->hasPendingChange($user)) {
            return redirect()->route('change-email.verify');
        }

        return view('profile::change-email', [
            'user' => $user,
        ]);
    }

    public function requestChangeEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_email' => ['required', 'email', 'max:255'],
            'new_email' => ['required', 'email', 'max:255', 'different:current_email'],
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

    public function showChangeEmailVerify(Request $request): View|RedirectResponse
    {
        $user = $request->user()->fresh();

        $this->emailChangeService->clearExpiredPending($user);
        $user = $user->fresh();

        if (! $this->emailChangeService->hasPendingChange($user)) {
            return redirect()->route('change-email');
        }

        return view('profile::change-email-verify', [
            'user' => $user,
            'resendCooldown' => max(
                $this->emailChangeService->resendCooldownRemaining($user),
                $request->session()->get('verification_sent') ? 60 : 0
            ),
            'freshVerification' => (bool) $request->session()->get('verification_sent'),
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

        return redirect()
            ->route('change-email')
            ->with('status', 'Email change request cancelled.');
    }

    public function confirmChangeEmail(Request $request, int $id, string $token): RedirectResponse
    {
        try {
            $result = $this->emailChangeService->confirm($id, $token);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        }

        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('change-email.set-password', [
                'id' => $result['user']->id,
                'token' => $result['set_password_token'],
            ])
            ->with('status', 'Email changed to '.$result['user']->email.'. Set a new password to finish.');
    }

    public function showSetPasswordAfterEmailChange(Request $request, int $id, string $token): View|RedirectResponse
    {
        try {
            $user = $this->emailChangeService->validateSetPasswordToken($id, $token);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        }

        return view('profile::set-password', [
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function updateSetPasswordAfterEmailChange(Request $request, int $id, string $token): RedirectResponse
    {
        try {
            $user = $this->emailChangeService->validateSetPasswordToken($id, $token);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        }

        $validated = $request->validate([
            'password' => $this->passwordRules(),
        ]);

        $this->emailChangeService->completePasswordSet($user, (string) $validated['password']);

        return redirect()
            ->route('login')
            ->with('status', 'Password set successfully. Sign in with your new email and password.');
    }

    public function showChangePasswordVerify(Request $request): View|RedirectResponse
    {
        $user = $request->user()->fresh();

        $this->passwordChangeService->clearExpiredPending($user);
        $user = $user->fresh();

        if (! $this->passwordChangeService->hasPendingChange($user)) {
            if ($this->wasPasswordChangeConfirmed($request, $user)) {
                return $this->finishPasswordChangeLogout($request, $user);
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

        if ($this->wasPasswordChangeConfirmed($request, $user)) {
            $request->session()->forget('password_change_verify_active');
            $this->passwordChangeService->forgetRecentlyConfirmed($user->id);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'state' => 'confirmed',
                'redirect' => route('login'),
                'message' => 'Password changed successfully. Please sign in with your new password.',
            ]);
        }

        return response()->json([
            'state' => 'cancelled',
            'redirect' => route('change-password'),
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
            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        }

        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Password changed successfully for '.$user->email.'. Please sign in with your new password.');
    }

    protected function wasPasswordChangeConfirmed(Request $request, User $user): bool
    {
        return $this->passwordChangeService->wasRecentlyConfirmed($user->id);
    }

    protected function finishPasswordChangeLogout(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget('password_change_verify_active');
        $this->passwordChangeService->forgetRecentlyConfirmed($user->id);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Password changed successfully. Please sign in with your new password.');
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
