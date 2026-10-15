<?php

namespace App\Modules\Authentication\Http\Responses;

use App\Modules\Authentication\Middleware\SessionTimeout;
use App\Modules\Turnover\Services\FederationTermDetectionService;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $request->session()->put(SessionTimeout::SESSION_KEY, now()->getTimestamp());

        $redirect = route('dashboard');

        try {
            $user = $request->user();
            if ($user !== null) {
                if (! $user->hasVerifiedEmail()) {
                    $redirect = route('skfed.verification.wait');
                } elseif (app(FederationTermDetectionService::class)->mustLockPortalForTurnover($user)) {
                    $redirect = route('turnover.index');
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($request->wantsJson()) {
            return new JsonResponse([
                'two_factor' => false,
                'redirect' => $redirect,
            ], 200);
        }

        return redirect()->to($redirect);
    }
}
