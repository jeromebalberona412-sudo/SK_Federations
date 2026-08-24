<?php

namespace App\Modules\Authentication\Middleware;

use App\Modules\Authentication\Services\TenantContextService;
use App\Modules\Shared\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSkFedAccess
{
    public function __construct(protected TenantContextService $tenantContextService) {}

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('officialProfile');

        if ($user->isIncomingTurnoverOfficer()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $awaitingSetup = $user->turnover_status === 'awaiting_setup'
                || $user->account_status === 'turnover_pending';

            $message = $awaitingSetup
                ? 'Sorry, login is not available yet. Please complete your account setup using the email link sent to you. If the link expired after 24 hours, use Forgot Password on the login page. You may sign in once federation turnover is completed and your new term starts.'
                : 'Sorry, your new term has not started yet. Please wait until the outgoing Federation President and Vice President complete the turnover process. You may log in once your term is activated.';

            return redirect()->route('login')->with('access_denied', [
                'title' => 'Please Wait for Your Term',
                'message' => $message,
            ]);
        }

        $tenantId = $this->tenantContextService->tenantId();
        $tenantMismatch = $tenantId !== null
            && (int) ($user->tenant_id ?? 0) !== $tenantId
            && ! $user->hasFederationLeadershipAccess();

        if ($tenantId === null || ! $user->canAccessFederationPortal() || $tenantMismatch) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $user->turnover_status === 'archived'
                ? 'Your term has ended. Your account has been archived. Thank you for your service.'
                : 'Authentication could not be completed.';

            return redirect()->route('login')->withErrors([
                'auth' => $message,
            ]);
        }

        return $next($request);
    }
}
