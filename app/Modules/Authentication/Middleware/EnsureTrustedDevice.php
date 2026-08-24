<?php

namespace App\Modules\Authentication\Middleware;

use App\Modules\Authentication\Services\AuthenticationService;
use App\Modules\Authentication\Services\FeatureFlagService;
use App\Modules\Authentication\Services\TrustedDeviceService;
use App\Modules\Shared\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedDevice
{
    public function __construct(
        protected FeatureFlagService $featureFlagService,
        protected TrustedDeviceService $trustedDeviceService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->deviceVerificationEnabled()) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->isSkFed() || $user->hasFederationLeadershipAccess()) {
            return $next($request);
        }

        if ($request->routeIs(
            'verification.notice',
            'skfed.verification.wait',
            'skfed.verification.wait.status',
            'skfed.verification.resend',
            'skfed.verification.verify',
            'skfed.verification.success',
            'skfed.verification.cancel',
        )) {
            return $next($request);
        }

        if ($this->trustedDeviceService->isTrusted($user, $request)) {
            return $next($request);
        }

        app(AuthenticationService::class)->restoreVerificationPending($user, $request, true);

        Auth::logout();

        return redirect()
            ->route('skfed.verification.wait')
            ->with('status', 'We sent a verification link to your email. Please verify to continue on this device.');
    }

    protected function deviceVerificationEnabled(): bool
    {
        return $this->featureFlagService->deviceVerificationEnabled()
            || Schema::hasTable('sk_fed_trusted_devices');
    }
}
