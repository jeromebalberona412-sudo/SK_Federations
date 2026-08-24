<?php

namespace App\Modules\Authentication\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $dashboard = route('dashboard');

        if ($request->wantsJson()) {
            return new JsonResponse([
                'two_factor' => false,
                'redirect' => $dashboard,
            ], 200);
        }

        return redirect()->to($dashboard);
    }
}
